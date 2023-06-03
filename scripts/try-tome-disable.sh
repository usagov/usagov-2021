#!/bin/sh

source /etc/profile

maxWaitMinutes=$1
if [ x$maxWaitMinutes = x ]; then
    maxWaitMinutes=25
else
    fail=0

    case $maxWaitMinutes in *[!0123456789]*|0?*|"")
        fail=1
    esac

    if [ $maxWaitMinutes -gt 30 ]; then
        fail=1
    fi

    if [ $fail -ne 0 ]; then
        echo
        echo Usage $0 [maxWaitMinutes]
        echo Where maxWaitMinutes must be an integer less than 30, or empty.
        echo maxWaitMinuts defaults to 25
        exit 1
    fi
fi

SCRIPT_NAME=tome-run.sh
#SCRIPT_NAME=just-sit-here.sh

function isCmdRunning() {
    scriptName=$1

    PS_AUX=$(ps aux)
    ALREADY_RUNNING=$(echo "$PS_AUX" | grep $scriptName | wc -l)
    if [ "$ALREADY_RUNNING" -gt "0" ]; then
        return 0
    fi
    return 1
}

startSeconds=$(date +'%s')  ### seconds since epoch
diffSeconds=0
diffMinutes=0

sleepTime=15

until [ $diffMinutes -gt $maxWaitMinutes ]; do
    if ! isCmdRunning $SCRIPT_NAME; then
       echo "Running: /var/www/scripts/tome-disabled-toggle.sh 1"
       #####/var/www/scripts/tome-disabled-toggle.sh 1
       exit 0
    else
        echo "$SCRIPT_NAME is already running.  Sleeping $sleepTime (waited ${diffSeconds}s / ${diffMinutes}m of ${maxWaitMinutes}m)"
        sleep $sleepTime
    fi
    i=$((i+1))

    currSeconds=$(date +'%s')
    diffSeconds=$((currSeconds - startSeconds))
    diffMinutes=$((diffSeconds / 60))
done

exit 1
