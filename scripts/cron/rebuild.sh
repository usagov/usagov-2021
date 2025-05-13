#!/bin/sh

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
if [ -f $SCRIPT_DIR/../../bin/deploy/includes ]; then
  . $SCRIPT_DIR/../../bin/deploy/includes
else
   echo Cannot find $SCRIPT_DIR/../../bin/deploy/includes
   exit 1
fi

SPACE=${1:-please-provide-space-name-as-first-argument}
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')
assertCurSpace $SPACE
shift

DOCKERUSER=${DOCKERUSER:-gsatts}
DOCKERREPO=${DOCKERREPO:-usagov-2021}

echo "$DOCKERHUB_ACCESS_TOKEN" | docker login --username $DOCKERHUB_USERNAME --password-stdin

APPNAME=cron

CONTAINERTAG=${1}

if [ -z "$CONTAINERTAG" ]
then
      echo "Must specify a container tag for the build (e.g. latest)"
      exit 1;
fi;
shift

# Grab the starting space and org where the command was run
startorg=$(   cf target | grep org:   | awk '{ print $2 }')
startspace=$( cf target | grep space: | awk '{ print $2 }')

# Drop them off where we found them
function popspace() {
    echo "Popspace: ${startorg}/${startspace}"
    cf target -o "$startorg" -s "$startspace" > /dev/null 2>&1
}

trap popspace err

cf t -s $SPACE

# launch the app
echo "Deploying ${DOCKERUSER}/${DOCKERREPO}:${APPNAME}"

FULL_REBUILD=$1
if [ x$FULL_REBUILD = "x--full" ]; then
    STATE_STORAGE_SERVICE=${APPNAME}-state-storage
    EVENT_STORAGE_SERVICE=${APPNAME}-event-storage
    CALLWAIT_STORAGE_SERVICE=${APPNAME}-callwait-storage

    for storage_service in $STATE_STORAGE_SERVICE $EVENT_STORAGE_SERVICE $CALLWAIT_STORAGE_SERVICE; do
        if existsCFService $storage_service &> /dev/null; then
            echo "Clearing bucket contents for $storage_service"
            bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $SPACE $storage_service
            echo "Deleting $storage_service"
            cf delete-service $storage_service -f
        else
            echo "Storage service $storage_service not found"
        fi
    done
    cf delete-service ${APPNAME}-service-account -f
    cf delete ${APPNAME} -f
fi

bin/cloudgov/container-build-${APPNAME} $CONTAINERTAG
bin/cloudgov/container-push-${APPNAME} $CONTAINERTAG

bin/cloudgov/deploy-${APPNAME} $SPACE $CONTAINERTAG
cf restage ${APPNAME}

popspace
