#!/bin/sh

source /etc/profile

# just testing?
if [ x$1 == x"--dryrun" ]; then
  export echo=echo
  shift
fi

maxWaitMinutes=$1
if [ x$maxWaitMinutes != x ]; then
   shift
fi

setMaintMode=$1

fail=0

if [ x$maxWaitMinutes = x ]; then
    maxWaitMinutes=25
else

    case $maxWaitMinutes in *[!0123456789]*|0?*|"")
        fail=1
    esac

    if [ $maxWaitMinutes -gt 30 ]; then
        fail=1
    fi

fi

if [ x$setMaintMode = x ]; then
   setMaintMode=0
else
   if [ $setMaintMode != "maintenance" ]; then
      fail=1
    fi
fi

if [ $fail -ne 0 ]; then
    echo
    echo Usage $0 [maxWaitMinutes] [maintenance]
    echo "Where maxWaitMinutes must be an integer less than 30, or empty (maxWaitMinutes defaults to 25)".
    echo and
    echo where maintenance is "maintenance" or empty.  If maintenance is set, the Drupal maintenance mode will be enabled.
    echo
    exit 1
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
       $echo /var/www/scripts/tome-disabled-toggle.sh 1

       if [ x$setMaintMode = x"maintenance" ]; then
       echo "Running: /var/www/scripts/toggle-maintenance-mode.sh 1"
       $echo /var/www/scripts/maintenance-mode-toggle.sh 1
       fi
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
