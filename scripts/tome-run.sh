#!/bin/ash

SCRIPT_PATH=$(dirname "$0")

URI=${1:-https://beta.usa.gov}

YMD=$(date +"%Y/%m/%d")
HM=$(date +"%H_%M")

# check nodes and blocks for any content changes in the last 30 minutes
export CONTENT_UPDATED=$(drush sql:query "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800))) ) as x")
if [ "$CONTENT_UPDATED" != "0" ]; then

  TOMELOGDIR=/tmp/tome-log/tome_run_$YMD
  TOMELOG=$TOMELOGDIR/$HM.log

  mkdir -p $TOMELOGDIR
  touch $TOMELOG

  echo "Found site changes: running static site build: $TOMELOG"
  $SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee $TOMELOG
  $SCRIPT_PATH/tome-sync.sh $TOMELOG
else
  echo "No change to block or node content in the last 30 minutes: no need for static site build"
fi
