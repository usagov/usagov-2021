#!/bin/sh

SCRIPT_PATH=$(dirname "$0")
SCRIPT_NAME=$(basename "$0")
SCRIPT_PID=$$

# Set flag to indicate we're inside a Tome process
# This prevents is_tome_running() from detecting itself during automatic backups
export INSIDE_TOME_PROCESS=1

URI=${1:-https://www.usa.gov}
FORCE=${2:-0}
RETRY_SEMAPHORE_FILE=/tmp/tome-log/retry-on-next-run

YMD=$(date +"%Y/%m/%d")
YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")
TR_START_TIME=$(date -u +"%s")

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

S3_EXTRA_PARAMS=""
if [ "${APP_SPACE}" = "local" ]; then
  S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
fi

# Use a unique dir for each run - just in case more than one of this is running
TOMELOGPATH=/tmp/tome-log/
TOMELOGFILE=$YMD/$APP_SPACE-$YMDHMS.log
TOMELOG=$TOMELOGPATH$TOMELOGFILE

mkdir -p /tmp/tome-log/$YMD
touch $TOMELOG

ssg_now() {
  date -u +"%s"
}

ssg_metric_line() {
  phase=$1
  status=$2
  ts=$(ssg_now)
  shift 2

  printf 'SSG_METRIC script=%s phase=%s status=%s ts=%s' "$SCRIPT_NAME" "$phase" "$status" "$ts"
  for field in "$@"; do
    printf ' %s' "$field"
  done
  printf '\n'
}

ssg_metric() {
  ssg_metric_line "$@" | tee -a "$TOMELOG"
}

ssg_metric_end() {
  phase=$1
  start=$2
  status=$3
  now=$(ssg_now)
  duration=$((now - start))
  shift 3

  ssg_metric "$phase" "$status" "duration_s=$duration" "$@"
}

RUN_START=$(ssg_now)
ssg_metric "tome_run" "start" "app_space=$APP_SPACE" "force=$FORCE" "uri=$URI"

PREFLIGHT_START=$(ssg_now)
ssg_metric "preflight" "start"

PREFLIGHT_JSON=$(drush usagov:ssg-preflight)
PREFLIGHT_SUCCESS=$?
if [ "$PREFLIGHT_SUCCESS" != "0" ]; then
  ssg_metric_end "preflight" "$PREFLIGHT_START" "failed" "exit_code=$PREFLIGHT_SUCCESS"
  echo "Static site generation preflight failed." | tee -a $TOMELOG
  ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=1" "reason=preflight_failed"
  exit 1
fi

export NO_RUN=$(echo "$PREFLIGHT_JSON" | jq -r '.tome_run_disabled // ""')
export MAINT_MODE_STATE=$(echo "$PREFLIGHT_JSON" | jq -r '.maintenance_mode // ""')
export CONTENT_UPDATED=$(echo "$PREFLIGHT_JSON" | jq -r '.content_updated // 0')

# Don't even start if this flag is set:
if [ "$NO_RUN" != '' ]; then
    ssg_metric_end "preflight" "$PREFLIGHT_START" "blocked" "reason=tome_disabled"
    echo "Tome run is disabled. Exiting." | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation is disabled"
    ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=2" "reason=tome_disabled"
    exit 2
fi

# Also, don't start if we're in maintenance mode:
if [ x$MAINT_MODE_STATE == x1 ]; then
    ssg_metric_end "preflight" "$PREFLIGHT_START" "blocked" "reason=maintenance_mode"
    echo "Maintenance mode is enabled. Exiting." | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Maintenance mode is enabled; static site will not generate"
    ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=2" "reason=maintenance_mode"
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
    ssg_metric_end "preflight" "$PREFLIGHT_START" "blocked" "reason=already_running"
    ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=2" "reason=already_running"
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

# If "no-force" is given, then override CONTAINER_UPDATED to 0 because /container_start_timestamp always gets reset with a new docker process on our locals
if [[ "$FORCE" =~ ^\-{0,2}no-f\(orce\)?$ ]]; then
  export CONTAINER_UPDATED=0
fi

export RETRY_SEMAPHORE_EXISTS=0
if [ -f $RETRY_SEMAPHORE_FILE ]; then
  export RETRY_SEMAPHORE_EXISTS=1
  rm $RETRY_SEMAPHORE_FILE
fi

ssg_metric_end "preflight" "$PREFLIGHT_START" "end" "already_running=$ALREADY_RUNNING" "container_updated=$CONTAINER_UPDATED" "retry_semaphore_exists=$RETRY_SEMAPHORE_EXISTS"

CHANGE_DETECTION_START=$(ssg_now)
ssg_metric "change_detection" "start" "source=preflight_payload"
ssg_metric_end "change_detection" "$CHANGE_DETECTION_START" "end" "content_updated=$CONTENT_UPDATED" "container_updated=$CONTAINER_UPDATED" "retry_semaphore_exists=$RETRY_SEMAPHORE_EXISTS" "force=$FORCE"

if [ "$CONTENT_UPDATED" != "0" ] || [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]] || [ "$CONTAINER_UPDATED" != "0" ] || [ "$RETRY_SEMAPHORE_EXISTS" != "0" ] ; then

  echo "Running static site build: content-updated($CONTENT_UPDATED) container-updated($CONTAINER_UPDATED) forced($FORCED) $TOMELOG" | tee -a $TOMELOG

  set -o pipefail  # Need to capture tome-static failure on next line.
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation Started"

  TOME_GENERATION_START=$(ssg_now)
  ssg_metric "tome_generation" "start" "process_count=${TOME_PROCESS_COUNT:-4}"
  $SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee -a $TOMELOG
  TOME_SUCCESS=$?
  ssg_metric_end "tome_generation" "$TOME_GENERATION_START" "end" "exit_code=$TOME_SUCCESS"

  if [ "$TOME_SUCCESS" == "0" ]; then
    SYNC_START=$(ssg_now)
    ssg_metric "sync" "start" "log_file=$TOMELOGFILE"
    $SCRIPT_PATH/tome-sync.sh $TOMELOGFILE $YMDHMS $FORCE
    SYNC_SUCCESS=$?
    ssg_metric_end "sync" "$SYNC_START" "end" "exit_code=$SYNC_SUCCESS"
    if [ "$SYNC_SUCCESS" != "0" ]; then
      echo "Static site sync failed with status $SYNC_SUCCESS." | tee -a $TOMELOG
      ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=1" "reason=sync_failed"
      exit 1
    fi
  else
    FAILURE_CLEANUP_START=$(ssg_now)
    ssg_metric "failure_cleanup" "start" "tome_exit_code=$TOME_SUCCESS"
    echo "Tome static build failed with status $TOME_SUCCESS - not pushing to S3" | tee -a $TOMELOG
    echo "Deleting Tome files to prevent inconsistency in next run" | tee -a $TOMELOG
    rm -rf /var/www/html/* | tee -a $TOMELOG
    if [ -f "$TOMELOG" ]; then
      echo "Saving logs of this run to S3" | tee -a $TOMELOG
      aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors $S3_EXTRA_PARAMS
    fi
    GEN_FAIL_TIME=$(date +"%s")
    $SCRIPT_PATH/tome-status-indicator-update.sh "$GEN_FAIL_TIME" "Static Site Generation Failed"
    ssg_metric_end "failure_cleanup" "$FAILURE_CLEANUP_START" "end"
    ssg_metric_end "tome_run" "$RUN_START" "exit" "exit_code=1" "reason=tome_generation_failed"
    exit 1
  fi
  echo "Removing logs that are not from today, we don't need them and they are saved in S3/NR" | tee -a $TOMELOG
  LOG_CLEANUP_START=$(ssg_now)
  ssg_metric "log_cleanup" "start"
  TOMELOGDATEPATH=$TOMELOGPATH$(date +"%Y/%m/")
  for d in $TOMELOGDATEPATH*/ ; do
    TOMELOGDATEPATHTODAY=$TOMELOGDATEPATH$(date +"%d/")
    if [ $d != $TOMELOGDATEPATHTODAY ]; then
      echo "Removing log files not from today: $d" | tee -a $TOMELOG
      rm -rf $d
    fi
  done
  ssg_metric_end "log_cleanup" "$LOG_CLEANUP_START" "end"
else
  NO_OP_START=$(ssg_now)
  ssg_metric "no_op_exit" "start" "content_updated=$CONTENT_UPDATED" "container_updated=$CONTAINER_UPDATED" "retry_semaphore_exists=$RETRY_SEMAPHORE_EXISTS" "force=$FORCE"
  echo "No change to any node, block, or taxonomy, content in the last 30 minutes: no need for static site build" | tee -a $TOMELOG
  ssg_metric_end "no_op_exit" "$NO_OP_START" "end"
fi

ssg_metric_end "tome_run" "$RUN_START" "end" "exit_code=0"
