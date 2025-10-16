#!/bin/bash
# Adds a log drain for the specified space and binds it to each app
# EXCEPT for environment-specific "log-shipper" apps

set -o pipefail

SPACE=${SPACE:-"dev"}

SERVICE_EXISTS=`cf service log-shipper-drain-${SPACE} --guid`

if [ "$SERVICE_EXISTS" = "FAILED" ]; then
    # Check if we have credentials for creating new drain services
    if [ -z "$LOGSHIPPER_HTTP_USER" ] || [ -z "$LOGSHIPPER_HTTP_PASS" ]; then
        echo "Warning: LOGSHIPPER_HTTP_USER and LOGSHIPPER_HTTP_PASS not set"
        echo "Cannot create log-shipper-drain-${SPACE} service without credentials"
        echo "If services already exist, this can be ignored"
    else
        echo "Creating log-shipper-drain-${SPACE} service"
        cf create-user-provided-service log-shipper-drain-${SPACE} -l "https://${LOGSHIPPER_HTTP_USER}:${LOGSHIPPER_HTTP_PASS}@usagov-${SPACE}-logshipper.app.cloud.gov/?drain-type=all"
    fi
else
    echo "Service log-shipper-drain-${SPACE} already exists."
fi


applist=$(cf apps | tail -n +4 | awk '{print $1}')

for app in $applist; do
    if [[ ! "$app" = "log-shipper-${SPACE}" ]]; then
	echo "Binding log-shipper-drain-${SPACE} service to $app"
        cf bind-service $app log-shipper-drain-${SPACE}
    fi
done
