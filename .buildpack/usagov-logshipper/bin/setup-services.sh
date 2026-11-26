#!/bin/bash

# Checks for the following in the currently targeted space and creates them if
# they are not present:
# - cg-logshipper-creds (user-provided service)
# - newrelic-creds (user-provided service)
# - log-storage (s3 service; one bucket per space)
#
# These are documented in https://github.com/GSA-TTS/cg-logshipper/blob/main/README.md#deploying
#
# Only missing services are created, so this is safe to run on every deployment.

set -e
set -o pipefail

# Seconds to wait for the brokered s3 instance to finish provisioning.
SERVICE_WAIT_TIMEOUT=${SERVICE_WAIT_TIMEOUT:-600}

CURRENT_SPACE=$(cf target 2>/dev/null | awk '/^space:/ {print $2}' || true)
if [ -z "$CURRENT_SPACE" ]; then
    echo "ERROR: no CF space is targeted. Log in and 'cf target -s <space>' first."
    exit 1
fi
if [ -n "$SPACE" ] && [ "$SPACE" != "$CURRENT_SPACE" ]; then
    echo "ERROR: SPACE is '$SPACE' but the targeted CF space is '$CURRENT_SPACE'."
    exit 1
fi
# An expired session fails the same way a missing service does, and misreading
# that would create duplicates, so confirm the session is live before testing
# for anything.
if ! cf space "$CURRENT_SPACE" --guid > /dev/null 2>&1; then
    echo "ERROR: cannot query space '$CURRENT_SPACE'. Log in again."
    exit 1
fi
echo "Configuring log-shipper services in space '$CURRENT_SPACE'"

# Test the exit code rather than matching cf's "FAILED" output: that string is a
# cf6-ism and is printed for any failure, not just a missing service. Same idiom
# as existsCFService in bin/deploy/includes.
service_exists() { # service name
    cf service "$1" > /dev/null 2>&1
}

# The s3 broker provisions asynchronously. A cf push that binds an instance
# which is still being created fails, so wait for it to settle.
wait_for_service() { # service name
    local svc=$1
    local waited=0
    local state

    while [ "$waited" -lt "$SERVICE_WAIT_TIMEOUT" ]; do
        state=$(cf curl "/v3/service_instances?names=${svc}" |
            jq -r '.resources[0].last_operation.state // "unknown"')
        case "$state" in
            succeeded)
                echo "$svc is ready"
                return 0
                ;;
            failed)
                echo "ERROR: provisioning of $svc failed"
                cf service "$svc" || true
                return 1
                ;;
        esac
        echo "Waiting for $svc (last operation: $state, ${waited}s elapsed)"
        sleep 10
        waited=$((waited + 10))
    done

    echo "ERROR: timed out after ${SERVICE_WAIT_TIMEOUT}s waiting for $svc to provision"
    return 1
}

NEED_CREDS=0
NEED_NEWRELIC=0
NEED_STORAGE=0
service_exists cg-logshipper-creds || NEED_CREDS=1
service_exists newrelic-creds      || NEED_NEWRELIC=1
service_exists log-storage         || NEED_STORAGE=1

VAR_MISSING=0

# Only require variables if we need to create services that don't exist
if [ $NEED_CREDS -eq 1 ]; then
    if [ -z "$LOGSHIPPER_HTTP_USER" ]; then
        echo "  LOGSHIPPER_HTTP_USER variable is absent (needed to create cg-logshipper-creds service)"
        VAR_MISSING=1
    fi
    if [ -z "$LOGSHIPPER_HTTP_PASS" ]; then
        echo "  LOGSHIPPER_HTTP_PASS variable is absent (needed to create cg-logshipper-creds service)"
        VAR_MISSING=1
    fi
fi

if [ $NEED_NEWRELIC -eq 1 ]; then
    if [ -z "$NEW_RELIC_LICENSE_KEY" ]; then
        echo "  NEW_RELIC_LICENSE_KEY variable is absent (needed to create newrelic-creds service)"
        VAR_MISSING=1
    fi
fi

if [ $VAR_MISSING -eq 1 ]; then
    echo "Required variable(s) missing for service creation, exiting"
    exit $VAR_MISSING
fi

if [ $NEED_CREDS -eq 1 ]; then
    echo "Creating cg-logshipper-creds service"
    cf create-user-provided-service cg-logshipper-creds -p "{\"HTTP_USER\": \"$LOGSHIPPER_HTTP_USER\", \"HTTP_PASS\": \"$LOGSHIPPER_HTTP_PASS\"}" -t "logshipper-creds"
fi

if [ $NEED_NEWRELIC -eq 1 ]; then
    echo "Creating newrelic-creds service"
    cf create-user-provided-service newrelic-creds -p "{\"NEW_RELIC_LICENSE_KEY\": \"$NEW_RELIC_LICENSE_KEY\", \"NEW_RELIC_LOGS_ENDPOINT\": \"https://gov-log-api.newrelic.com/log/v1\"}" -t "newrelic-creds"
fi

if [ $NEED_STORAGE -eq 1 ]; then
    echo "Creating log-storage service"
    cf create-service s3 basic log-storage -t "logshipper-s3"
fi

# Wait unconditionally: an earlier interrupted run can leave the bucket half
# provisioned, in which case it exists but still cannot be bound.
wait_for_service log-storage
