#!/bin/sh

SCRIPT_PATH=$(dirname "$0")
SCRIPT_NAME=tome-run.sh
#SCRIPT_PID=$$

# we should expect to see our process running: so we would expect a count of 1
PS_AUX=$(ps aux)
ALREADY_RUNNING=$(echo "$PS_AUX" | grep $SCRIPT_NAME | wc -l)
if [ "$ALREADY_RUNNING" -gt "0" ]; then
    exit 0
fi
exit 1
