#!/bin/bash

SCRIPT_PATH=$(dirname "$0")

# default to generating prod-compatible site
URI=${1:-https://beta.usa.gov}

TOMELOGPATH=/tmp/tome-static-log/$(date +"%Y/%m/%d")
TOMELOG=$TOMELOGPATH/$(date +"%H_%M").log

mkdir -p $TOMELOGPATH
touch $TOMELOG

# check nodes+blocks for any content changes in the last 30 minutes (1800 seconds)
# this is expected to run every 15 miinutes so x2 window should cover any case where a single build attempt fails
CONTENT_UPDATED=$(drush sql:query --strict=0 "SELECT SUM(c) FROM ( \
  ( \
    SELECT \
      count(*) as c \
    FROM node_field_data \
    WHERE changed > (UNIX_TIMESTAMP(now())-(1800)) \
  ) UNION ( \
    SELECT count(*) as c \
    FROM block_content_field_data \
    WHERE changed > (UNIX_TIMESTAMP(now())-(1800)) \
  ) \
) as x");
re='^[0-9]+$'
if [ "$CONTENT_UPDATED" != "0" ] && [[ $CONTENT_UPDATED =~ $re ]]; then
  echo "Found a content change to a Block or a Node in the last 30 minutes: running static site build"
  echo "Writing log to $TOMELOG";
  echo "$SCRIPT_PATH/tome-static.sh $URI 2>&1 | tee $TOMELOG"
  echo "$SCRIPT_PATH/tome-sync.sh $TOMELOG"
else
  echo "No change to block or node content in the last 30 minutes: no need for static site build"
fi

echo "Full Log : $TOMELOG"
