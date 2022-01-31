#!/bin/ash

SCRIPT_PATH=$(dirname "$0")

URI=${1:-https://beta.usa.gov}

TOMELOGDIR=/tmp/tome-log
TOMELOG=$TOMELOGDIR/tome_run_$(date +"%Y/%m/%d/%H_%M").log

mkdir -p $TOMELOGDIR
touch $TOMELOG

# check nodes and blocks for any content changes in the last 30 minutes
export CONTENT_UPDATED=$(drush sql:query "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800))) ) as x")
if [ "$CONTENT_UPDATED" != "0" ]; then
  $SCRIPT_PATH/tome-build.sh $URI 2>&1 | tee $TOMELOG
  $SCRIPT_PATH/tome-sync.sh $TOMELOG
fi;

echo "Full Log : $TOMELOG";
