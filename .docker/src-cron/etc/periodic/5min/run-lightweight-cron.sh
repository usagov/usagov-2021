#!/usr/bin/env bash

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "cron-secrets") | .credentials')
CRON_KEY=$(echo $SECRETS | jq -r '.CRON_KEY')

# only the 1st instance within cloud formation should actually do anything on cron
if [ "${CF_INSTANCE_INDEX:-''}" == "0" ]; then

  # Use unique uri per environment - default to prod
  if [ "${APP_SPACE}" = "local" ]; then
    URI="http://cms-usagov.docker.local"
  else
    URI="http://cms-${APP_SPACE}-usagov.apps.internal"
  fi

  curl -s -o /tmp/scheduler_output "$URI/scheduler/cron/$CRON_KEY"

fi
