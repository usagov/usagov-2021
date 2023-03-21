#!/bin/sh

SCRIPT_PATH=$(dirname "$0")
SCRIPT_NAME=$(basename "$0")
SCRIPT_PID=$$

URI=${1:-https://beta.usa.gov}
FORCE=${2:-0}

YMD=$(date +"%Y/%m/%d")
YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')
if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
  export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint');
fi

S3_EXTRA_PARAMS=""
if [ "${APP_SPACE}" = "local" ]; then
  S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
fi

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
APP_SPACE=${APP_SPACE:-local}

# Use a unique dir for each run - just in case more than one of this is running
TOMELOGFILE=$YMD/$APP_SPACE-$YMDHMS.log
TOMELOG=/tmp/tome-log/$TOMELOGFILE

mkdir -p /tmp/tome-log/$YMD
touch $TOMELOG

# Don't even start if this flag is set:
export NO_RUN=$(drush sget usagov.tome_run_disabled)
if [ "$NO_RUN" != '' ]; then
    echo "Tome run is disabled. Exiting." | tee -a $TOMELOG
    exit 2
fi

# we should expect to see our process running: so we would expect a count of 1
echo "Check if Tome is already running ... " | tee -a $TOMELOG
PS_AUX=$(ps aux)
ALREADY_RUNNING=$(echo "$PS_AUX" | grep $SCRIPT_NAME | grep -v $SCRIPT_PID | wc -l)
if [ "$ALREADY_RUNNING" -gt "0" ]; then
  if [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]]; then
    echo "Another Tome is already running. Forcing another run anyway." | tee -a $TOMELOG
  else
    echo "Another Tome is already running. Exiting." | tee -a $TOMELOG
    exit 2
  fi
else
 echo "No other Tome is running. Proceeding on our own." | tee -a $TOMELOG
fi

export CONTAINER_UPDATED=0
if [ -f /container_start_timestamp ]; then
  start_time=$(cat /container_start_timestamp);
  run_time=$(date +"%s")
  if [ -n "$start_time" ] && [ $(($run_time - $start_time)) -lt 1800 ]; then
    export CONTAINER_UPDATED=1
  fi
fi

# check nodes and blocks for any content changes in the last 30 minutes
export CONTENT_UPDATED=$(drush sql:query "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800))) ) as x")
if [ "$CONTENT_UPDATED" != "0" ] || [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]] || [ "$CONTAINER_UPDATED" != "0" ]; then

  echo "Running static site build: content-updated($CONTENT_UPDATED) container-updated($CONTAINER_UPDATED) forced($FORCED) $TOMELOG" | tee -a $TOMELOG
  $SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee -a $TOMELOG
  TOME_SUCCESS=$?
  if [ "$TOME_SUCCESS" == "0" ]; then
    # Use a unique dir for each run - just in case more than one of this is running
    RENDER_DIR=/tmp/tome/$YMDHMS
    ANALYTICS_DIR=$(realpath ../website-analytics)
    echo "Copying $ANALYTICS_DIR to $RENDER_DIR" | tee -a $TOMELOG
    cp -R "$ANALYTICS_DIR" "$RENDER_DIR"
    $SCRIPT_PATH/tome-sync.sh $TOMELOGFILE $YMDHMS $FORCE
  else
    echo "Tome static build failed with status $TOME_SUCCESS - not pushing to S3" | tee -a $TOMELOG
    if [ -f "$TOMELOG" ]; then
      echo "Saving logs of this run to S3" | tee -a $TOMELOG
      aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors $S3_EXTRA_PARAMS
    fi
    exit 1
  fi
else
  echo "No change to block or node content in the last 30 minutes: no need for static site build" | tee -a $TOMELOG
fi
