#!/bin/sh

SPACE=$1

if [ x$SPACE = x ]; then
   SPACE=$(echo $VCAP_APPLICATION | jq -r '.space_name')
fi

. ~/.profile $SPACE &> /dev/null

cf run-task cfevents --name cfevents-instance --command "/opt/cfevents/capture-latest-events $SPACE"
