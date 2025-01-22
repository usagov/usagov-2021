#!/usr/bin/env bash

SPACE=$1
if [ x$SPACE = x ]; then
   SPACE=$(echo $VCAP_APPLICATION | jq -r '.space_name')
else
   shift
fi

if [ x$CFEVENT_RUN = x ]; then
    exit 0;
fi

source ~/.profile $SPACE event &> /dev/null

TASKNAME=$(basename $0)
TASKPID=$$

source $TASKLOCK_SCRIPT_ROOT/lock-singleton-task $TASKNAME $TASKPID

/opt/cfevents/capture-latest-events $SPACE

source $TASKLOCK_SCRIPT_ROOT/unlock-singleton-task $TASKNAME $TASKPID
