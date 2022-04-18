#!/bin/sh

SCRIPT_PATH=$(dirname "$0")

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

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
APP_SPACE=${APP_SPACE:-local}

# Use a unique dir for each run - just in case more than one of this is running
TOMELOGFILE=$YMD/$APP_SPACE-$YMDHMS.log
TOMELOG=/tmp/tome-log/$TOMELOGFILE

# check nodes and blocks for any content changes in the last 30 minutes
export CONTENT_UPDATED=$(drush sql:query "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800))) ) as x")
if [ "$CONTENT_UPDATED" != "0" ] || [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]] || [ $(cat /proc/uptime | grep -o '^[0-9]\+') -gt 1800 ]; then

  mkdir -p /tmp/tome-log/$YMD
  touch $TOMELOG

  echo "Found site changes: running static site build: $TOMELOG"
  $SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee $TOMELOG
  TOME_SUCCESS=$?
  if [ "$TOME_SUCCESS" == "0" ]; then
    $SCRIPT_PATH/tome-sync.sh $TOMELOGFILE $YMDHMS
  else
    echo "Tome static build failed - not pushing to S3" | tee $TOMELOG
    if [ -f "$TOMELOG" ]; then
      --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl
      #aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors
      echo "s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors"
    fi
    exit 1
  fi
else
  echo "No change to block or node content in the last 30 minutes: no need for static site build"
fi
