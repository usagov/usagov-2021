#!/usr/bin/env bash

# Deploy services and app for cron app.  Service creation should be idempotent.

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
if [ -f $SCRIPT_DIR/../../deploy/includes ]; then
  . $SCRIPT_DIR/../../deploy/includes
else
   echo Cannot find $SCRIPT_DIR/../../deploy/includes
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
ORG=$(getOrg)

export SERVICE_NAME="cfevents"
export APPINSTANCES=1

if existsCFService ${SERVICE_NAME}-service-account; then
  echo "User service ${SERVICE_NAME}-service-account already exists - good!"
else
  $SCRIPT_DIR/create-service-account $SPACE $SERVICE_NAME
fi

if [ $SERVICE_NAME = 'cfevent' ]; then
  if existsCFService ${SERVICE_NAME}-service-account; then
    SERVICE_KEY=$(cf service-key ${SERVICE_NAME}-service-account ${SERVICE_NAME}-service-key | tail -n +3)
    SERVICE_USER=$( echo ${SERVICE_KEY} | jq -r '.credentials.username')
    $echo cf set-org-role ${SERVICE_USER} $ORG OrgAuditor
    ### target our roles a bit:
    if [ $SPACE = "dr" ]; then
      for cfspace in dr dev stage prod tools shared-egress; do
        $echo cf set-space-role ${SERVICE_USER} $ORG $cfspace SpaceAuditor
      done
    else
      $echo cf set-space-role ${SERVICE_USER} $ORG $SPACE SpaceAuditor
    fi
    echo cf unset-space-role ${SERVICE_USER} $ORG $SPACE SpaceDeveloper
  else
    echo could not create Service Account for ${SERVICE_NAME} in ${SPACE}
    exit 1
  fi
fi
