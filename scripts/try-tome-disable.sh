#!/bin/sh

#SCRIPT_NAME=tome-run.sh
SCRIPT_NAME=ssh-agent

function isCmdRunning() {
    scriptName=$1
    sleepInterval=$2
    attemptCount=$3
    if [ x$attemptCount != x ]; then
        attemptCount="($attemptCount): "
    fi
    PS_AUX=$(ps aux)
    ALREADY_RUNNING=$(echo "$PS_AUX" | grep $scriptName | wc -l)
    if [ "$ALREADY_RUNNING" -gt "0" ]; then
        echo "$attemptCount$scriptName x $ALREADY_RUNNING.  Sleeping $sleepInterval"
        sleep $sleepInterval
        return 0
    fi
    return 1
}

i=1
until [ $i -gt 8 ]; do
    case $i in
    1) sleepTime=5 ;;
    2) sleepTime=7 ;;
    3) sleepTime=180 ;;
    4) sleepTime=5 ;;
    5) sleepTime=7 ;;
    6) sleepTime=1200 ;;
    7) sleepTime=5 ;;
    8) sleepTime=7 ;;
    esac

    if ! isCmdRunning $SCRIPT_NAME $sleepTime $i; then
       echo "Running: /var/www/scripts/tome-disabled-toggle.sh 1"
       /var/www/scripts/tome-disabled-toggle.sh 1
       exit
    fi
    ((i++))
done

exit 1
