#!/bin/sh

RETRY_SEMAPHORE_FILE=/tmp/tome-log/retry-on-next-run

ES_HOME_HTML_FILE=/var/www/html/es/index.html

if [ -f $ES_HOME_HTML_FILE ]; then 
  ES_HOME_HTML_SIZE=$(stat -c%s "$ES_HOME_HTML_FILE")
else
  ES_HOME_HTML_SIZE=0
fi

if [ $ES_HOME_HTML_SIZE -lt 1000 ]; then
  echo "*** ES index.html is way too small ($ES_HOME_HTML_SIZE bytes) ***"
  # Delete the known-bad file; it may be re-created correctly on the next run. 
  rm $ES_HOME_HTML_FILE
  touch $RETRY_SEMAPHORE_FILE
  TOME_PUSH_NEW_CONTENT=0
fi

