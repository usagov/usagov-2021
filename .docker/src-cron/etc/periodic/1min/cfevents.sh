#!/usr/bin/env bash

SPACE=$1
if [ x$SPACE = x ]; then
  SPACE=$(echo $VCAP_APPLICATION | jq -r '.space_name')
fi

source /opt/cron/cf-task-check

/opt/cron/task-check 1 cfevents
RETVAL=$?

case $RETVAL in
3)
  echo Incorrect usage error for task-check
  exit 1
  ;;
2)
 exit 0 ### skipping run - removed stale lock
  ;;
1)
  exit 0 ### skipping run - previous run still in progress
  ;;
0)

  . ~/.profile $SPACE &> /dev/null
  TASKRUNNING=$(runtask cfevents "/opt/cfevents/capture-latest-events $SPACE")

  while [ x"$TASKRUNNING" != x ]; do
    echo checking cfevents status
    TASKRUNNING=$(cktasklocks cfevents)
    echo "TASKRUNNING: $TASKRUNNING"
    sleep 10
  done
  /opt/cron/clean-task cfevents
  ;;
*)
  echo "Unknown error: $RETVAL"
  exit $RETVAL
  ;;
esac
