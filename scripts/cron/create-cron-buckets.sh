#!/bin/sh

# Helper script to create the buckets used by cron and associated tasks
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
  echo "Configuring $storage_service"
  if existsCFService $storage_service &> /dev/null; then
    echo "$storage_service already created"
  else
    if [ $storage_service = $CALLWAIT_STORAGE_SERVICE ]; then
      $echo cf create-service s3 basic-public $storage_service
    else
      $echo cf create-service s3 basic $storage_service
    fi
    if existsCFService $storage_service &> /dev/null; then
      echo "$storage_service successfully created"
    else
      echo "ERROR: $storage_service creation failed (expected w/ --dryrun)"
      exit 1
    fi
  fi
done
