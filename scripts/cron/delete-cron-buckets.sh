#!/bin/sh

# Helper script to delete the buckets used by cron and associated tasks
# Used for testing during local or dr development

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi
INCLUDES=$SCRIPT_DIR/../../bin/deploy/includes
if [ -f $INCLUDES ]; then
  . $INCLUDES
else
  echo Cannot find $INCLUDES
  exit 1
fi

# just testing?
if [ x$1 = x"--dryrun" ]; then
  export echo=echo
  shift
fi

SPACE=${1:-please-provide-space-name-as-first-argument}
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')
assertCurSpace $SPACE
shift

APPNAME=cron

STATE_STORAGE_SERVICE=${APPNAME}-state-storage
EVENT_STORAGE_SERVICE=${APPNAME}-event-storage
CALLWAIT_STORAGE_SERVICE=${APPNAME}-callwait-storage

for storage_service in $STATE_STORAGE_SERVICE $EVENT_STORAGE_SERVICE $CALLWAIT_STORAGE_SERVICE; do
  if existsCFService $storage_service &> /dev/null; then
    echo "Clearing bucket contents for $storage_service"
    bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $SPACE $storage_service
    echo "Deleting $storage_service"
    $echo cf delete-service $storage_service -f
  else
      echo "Storage service $storage_service not found"
  fi
done
