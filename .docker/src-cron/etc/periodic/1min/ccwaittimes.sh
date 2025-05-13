#!/usr/bin/env bash

SPACE=$1

if [ x$CALL_CENTER_RUN = x ]; then
    exit 0;
fi

source ~/.profile $SPACE callwait &> /dev/null

TASKNAME=$(basename $0)
TASKPID=$$

source $TASKLOCK_SCRIPT_ROOT/lock-singleton-task $TASKNAME $TASKPID

/opt/callcenter/call-center-update $SPACE

source $TASKLOCK_SCRIPT_ROOT/unlock-singleton-task $TASKNAME $TASKPID
