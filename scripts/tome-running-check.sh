#!/bin/sh

SCRIPT_PATH=$(dirname "$0")
SCRIPT_NAME=tome-run.sh
#SCRIPT_PID=$$

PS_AUX=$(ps aux)
ALREADY_RUNNING=$(echo "$PS_AUX" | grep $SCRIPT_NAME | wc -l)
if [ "$ALREADY_RUNNING" -gt "0" ]; then

    # Due to potential race-conditions brought up in USAGOV-2436, we will wait, and check again just to confirm
    PS_AUX2=$(ps aux)
    ALREADY_RUNNING2=$(echo "$PS_AUX2" | grep $SCRIPT_NAME | wc -l)
    if [ "$ALREADY_RUNNING2" -gt "0" ]; then

        exit 0
    fi
fi
exit 1
