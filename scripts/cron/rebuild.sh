#!/bin/sh

DEPLOY_SPACE=$1

if [ x"$DEPLOY_SPACE" = x ]; then
    echo Please provide the deployment space as first argument
    exit 1
fi
shift

APP=cron

CONTAINERTAG=${1}

if [ -z "$CONTAINERTAG" ]
then
      echo "Must specify a container tag for the build (e.g. latest)"
      exit 1;
fi;

# Grab the starting space and org where the command was run
startorg=$(   cf target | grep org:   | awk '{ print $2 }')
startspace=$( cf target | grep space: | awk '{ print $2 }')

# Drop them off where we found them
function popspace() {
    echo "Popspace: ${startorg}/${startspace}"
    cf target -o "$startorg" -s "$startspace" > /dev/null 2>&1
}

trap popspace err

cf t -s $DEPLOY_SPACE

FULL_REBUILD=$1
if [ x$FULL_REBUILD = "x--full" ]; then
    cf delete-service ${APP}-service-account -f
    cf delete ${APP} -f
fi

bin/cloudgov/container-build-${APP} $CONTAINERTAG
bin/cloudgov/container-push-${APP} $CONTAINERTAG

bin/cloudgov/deploy-${APP} $DEPLOY_SPACE $CONTAINERTAG

cf restage ${APP}

popspace
