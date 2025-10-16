#!/bin/bash

# Checks for the following and creates them if they are not present:
# - cg-logshipper-creds (user-provided service)
# - newrelic-creds (user-provided service)
# - log-storage (s3 service)
#
# These are documented in https://github.com/GSA-TTS/cg-logshipper/blob/main/README.md#deploying

# Check if services already exist before requiring environment variables
CREDS_EXISTS=`cf service cg-logshipper-creds --guid 2>/dev/null`
NEWRELIC_EXISTS=`cf service newrelic-creds --guid 2>/dev/null`

VAR_MISSING=0

# Only require variables if we need to create services that don't exist
if [ "$CREDS_EXISTS" = "FAILED" ]; then
    if [ -z "$LOGSHIPPER_HTTP_USER" ]; then
        echo "  LOGSHIPPER_HTTP_USER variable is absent (needed to create cg-logshipper-creds service)"
        VAR_MISSING=1
    fi
    if [ -z "$LOGSHIPPER_HTTP_PASS" ]; then
        echo "  LOGSHIPPER_HTTP_PASS variable is absent (needed to create cg-logshipper-creds service)"
        VAR_MISSING=1
    fi
fi

if [ "$NEWRELIC_EXISTS" = "FAILED" ]; then
    if [ -z "$NEW_RELIC_LICENSE_KEY" ]; then
        echo "  NEW_RELIC_LICENSE_KEY variable is absent (needed to create newrelic-creds service)"
        VAR_MISSING=1
    fi
fi

if [ $VAR_MISSING -eq 1 ]; then
    echo "Required variable(s) missing for service creation, exiting"
    exit $VAR_MISSING
fi


SERVICE_EXISTS=`cf service cg-logshipper-creds --guid`

if [ "$SERVICE_EXISTS" = "FAILED" ]; then
    echo "Creating cg-logshipper-creds service"
    cf create-user-provided-service cg-logshipper-creds -p "{\"HTTP_USER\": \"$LOGSHIPPER_HTTP_USER\", \"HTTP_PASS\": \"$LOGSHIPPER_HTTP_PASS\"}" -t "logshipper-creds"
fi

SERVICE_EXISTS=`cf service newrelic-creds --guid`

if [ "$SERVICE_EXISTS" = "FAILED" ]; then
    echo "Creating newrelic-creds service"
    cf create-user-provided-service newrelic-creds -p "{\"NEW_RELIC_LICENSE_KEY\": \"$NEW_RELIC_LICENSE_KEY\", \"NEW_RELIC_LOGS_ENDPOINT\": \"https://gov-log-api.newrelic.com/log/v1\"}" -t "newrelic-creds"
fi

SERVICE_EXISTS=`cf service log-storage --guid`

if [ "$SERVICE_EXISTS" = "FAILED" ]; then
    echo "Creating log-storage service"
    cf create-service s3 basic log-storage -t "logshipper-s3"
fi
