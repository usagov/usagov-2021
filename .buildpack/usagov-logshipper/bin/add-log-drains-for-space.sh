#!/bin/bash
# Creates the space's log drain service if it is missing, then binds it to every
# app in the space EXCEPT that space's own log-shipper.
#
# A syslog drain binding does not take effect until the app restarts, so apps
# bound here for the first time are rolled. Apps that were already bound are
# left alone, which makes the steady-state run a no-op.

set -e
set -o pipefail

CURRENT_SPACE=$(cf target 2>/dev/null | awk '/^space:/ {print $2}' || true)
if [ -z "$CURRENT_SPACE" ]; then
    echo "ERROR: no CF space is targeted. Log in and 'cf target -s <space>' first."
    exit 1
fi
SPACE=${SPACE:-$CURRENT_SPACE}
if [ "$SPACE" != "$CURRENT_SPACE" ]; then
    echo "ERROR: SPACE is '$SPACE' but the targeted CF space is '$CURRENT_SPACE'."
    exit 1
fi

# An expired session fails the same way a missing service does, and misreading
# that would create a duplicate drain, so confirm the session is live first.
SPACE_GUID=$(cf space "$SPACE" --guid 2>/dev/null || true)
if [ -z "$SPACE_GUID" ]; then
    echo "ERROR: cannot query space '$SPACE'. Log in again."
    exit 1
fi

DRAIN_SERVICE=log-shipper-drain-${SPACE}
LOGSHIPPER_APP=log-shipper-${SPACE}

# Test the exit code rather than matching cf's "FAILED" output: that string is a
# cf6-ism and is printed for any failure, not just a missing service.
service_exists() { # service name
    cf service "$1" > /dev/null 2>&1
}

is_bound() { # app name, service name
    local count
    count=$(cf curl "/v3/service_credential_bindings?service_instance_names=$2&app_names=$1" |
        jq -r '.pagination.total_results // 0')
    [ "$count" -gt 0 ]
}

if service_exists "$DRAIN_SERVICE"; then
    echo "Service $DRAIN_SERVICE already exists."
else
    if [ -z "$LOGSHIPPER_HTTP_USER" ] || [ -z "$LOGSHIPPER_HTTP_PASS" ]; then
        echo "ERROR: LOGSHIPPER_HTTP_USER and LOGSHIPPER_HTTP_PASS are required to"
        echo "       create $DRAIN_SERVICE; its drain URL embeds them."
        exit 1
    fi
    echo "Creating $DRAIN_SERVICE service"
    cf create-user-provided-service "$DRAIN_SERVICE" -l "https://${LOGSHIPPER_HTTP_USER}:${LOGSHIPPER_HTTP_PASS}@usagov-${SPACE}-logshipper.app.cloud.gov/?drain-type=all"
fi

APPS_JSON=$(cf curl "/v3/apps?space_guids=${SPACE_GUID}&per_page=200")
APPS_TOTAL=$(echo "$APPS_JSON" | jq -r '.pagination.total_results')
APPS_LISTED=$(echo "$APPS_JSON" | jq -r '.resources | length')
if [ "$APPS_TOTAL" -gt "$APPS_LISTED" ]; then
    echo "ERROR: space $SPACE has $APPS_TOTAL apps but only $APPS_LISTED were listed."
    echo "       Raise per_page so no app silently misses its drain binding."
    exit 1
fi

restart_list=""

while IFS=$'\t' read -r app state; do
    [ -n "$app" ] || continue

    if [ "$app" = "$LOGSHIPPER_APP" ]; then
        echo "Skipping $app: it is the drain's destination"
        continue
    fi

    if is_bound "$app" "$DRAIN_SERVICE"; then
        echo "$app is already bound to $DRAIN_SERVICE"
        continue
    fi

    echo "Binding $DRAIN_SERVICE service to $app"
    cf bind-service "$app" "$DRAIN_SERVICE"

    if [ "$state" = "STARTED" ]; then
        restart_list="$restart_list $app"
    else
        echo "Not restarting $app: it is $state"
    fi
done < <(echo "$APPS_JSON" | jq -r '.resources[] | "\(.name)\t\(.state)"')

# New bindings do nothing until the app restarts.
for app in $restart_list; do
    echo "Restarting $app to pick up $DRAIN_SERVICE"
    cf restart "$app" --no-wait --strategy rolling
done
