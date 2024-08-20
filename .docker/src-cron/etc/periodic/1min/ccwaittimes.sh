#!/usr/bin/env bash

. ~/.profile &> /dev/null

TASKNAME=$(basename $0)
TASKPID=$$

source $TASKLOCK_SCRIPT_ROOT/lock-singleton-task $TASKNAME $TASKPID

/opt/callcenter/call-center-update

source $TASKLOCK_SCRIPT_ROOT/unlock-singleton-task $TASKNAME $TASKPID
