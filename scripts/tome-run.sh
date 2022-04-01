#!/bin/sh

SCRIPT_PATH=$(dirname "$0")

URI=${1:-https://beta.usa.gov}
FORCE=${2:-0}

YMD=$(date +"%Y/%m/%d")
YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
APP_SPACE=${APP_SPACE:-local}
# Use a unique dir for each run - just in case more than one of this is running

TOMELOGFILE=$YMD/$APP_SPACE-$YMDHM.log
TOMELOGDIR=/tmp/tome-log/$YMD
TOMELOG=$TOMELOGDIR/$APP_SPACE-$YMDHM.log

# check nodes and blocks for any content changes in the last 30 minutes
export CONTENT_UPDATED=$(drush sql:query "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800))) ) as x")
if [ "$CONTENT_UPDATED" != "0" ] || [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]]; then

  mkdir -p $TOMELOGDIR
  touch $TOMELOG

  echo "Found site changes: running static site build: $TOMELOG"
  $SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee $TOMELOG
  $SCRIPT_PATH/tome-sync.sh $TOMELOGFILE
else
  echo "No change to block or node content in the last 30 minutes: no need for static site build"
fi
