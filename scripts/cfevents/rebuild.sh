#!/bin/sh

# This is just a helper script for local build/test

DEPLOY_SPACE=$1

if [ x"$DEPLOY_SPACE" = x ]; then
    echo Please provide the deployment space as first argument
    exit 1
fi
shift

APP=cfevents

# Grab the starting space and org where the command was run
startorg=$(   cf target | grep org:   | awk '{ print $2 }')
startspace=$( cf target | grep space: | awk '{ print $2 }')

# Drop them off where we found them
function popspace() {
    echo "Popspace: ${startorg}/${startspace}"
    cf target -o "$startorg" -s "$startspace" > /dev/null 2>&1
}
# trap popspace exit

trap popspace err

cf t -s $DEPLOY_SPACE

FULL_REBUILD=$1
if [ x$FULL_REBUILD = "x--full" ]; then
    cf delete-service ${APP}-service-account -f
    cf delete ${APP} -f
fi

bin/cloudgov/container-build-${APP}
bin/cloudgov/container-push-${APP}

bin/cloudgov/deploy-${APP} $DEPLOY_SPACE

cf restage ${APP}

popspace
