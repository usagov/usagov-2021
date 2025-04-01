#!/usr/bin/env bash

echo "Attempting lightweight cron"
# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "cron-secrets") | .credentials')
CRON_KEY=$(echo $SECRETS | jq -r '.CRON_KEY')

# only the 1st instance within cloud formation should actually do anything on cron
if [ "${CF_INSTANCE_INDEX:-''}" == "0" ]; then

  # Use unique uri per environment - default to prod
  if [ "${APP_SPACE}" = "dev" ]; then
    URI="http://cms-dev.usa.gov"
  elif [ "${APP_SPACE}" = "dr" ]; then
    URI="http://cms-dr.usa.gov"
  elif [ "${APP_SPACE}" = "stage" ]; then
    URI="http://cms-stage.usa.gov"
  elif [ "${APP_SPACE}" = "local" ]; then
    URI="http://localhost"
  else
    URI="http://www.usa.gov"
  fi

  echo "Running lightweight cron on $URI/scheduler/cron/$CRON_KEY"
  curl -k "$URI/scheduler/cron/$CRON_KEY"

fi
