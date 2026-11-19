#!/bin/bash

# Pushes the space's log shipper (log-shipper-<space>) from a pinned checkout of
# cg-logshipper plus the USAGov config in project_conf.
#
# Usage: deploy-logshipper.sh <containertag> [space]
#
# The space defaults to whichever space is currently targeted; passing one that
# doesn't match the target is an error rather than a cross-environment deploy.

set -e
set -o pipefail

# The cg-logshipper commit this deployment is pinned to.
CG_LOGSHIPPER_COMMIT=9b00429

# Run from the project directory no matter where we were invoked from.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

CONTAINERTAG=${1:-"/no pipeline number/"}

CURRENT_SPACE=$(cf target 2>/dev/null | awk '/^space:/ {print $2}' || true)
if [ -z "$CURRENT_SPACE" ]; then
    echo "ERROR: no CF space is targeted. Log in and 'cf target -s <space>' first."
    exit 1
fi
SPACE=${2:-$CURRENT_SPACE}
if [ "$SPACE" != "$CURRENT_SPACE" ]; then
    echo "ERROR: asked to deploy to '$SPACE' but the targeted CF space is '$CURRENT_SPACE'."
    exit 1
fi

# Fail before the clone and config rewrite rather than at cf push.
if ! cf space "$SPACE" --guid > /dev/null 2>&1; then
    echo "ERROR: cannot query space '$SPACE'. Log in again."
    exit 1
fi

APP_NAME=log-shipper-${SPACE}
APP_ROUTE=usagov-${SPACE}-logshipper.app.cloud.gov

echo "Deploying $APP_NAME to space '$SPACE' at $APP_ROUTE"

# Get our branch and commit hash for the status file:
USAGOV_BRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || echo "")
USAGOV_COMMIT=$(git log -1 --pretty=format:"%H")

# Always clear out the old cg-logshipper directory. We need to do this
# if we've changed the branch/tag we want to deploy or if we've removed some files
# from project_conf; it's easiest to just do it every time.
if [ -d "cg-logshipper" ]; then
   rm -rf cg-logshipper
fi

# Clone cg-logshipper and check out the pinned commit. Cloned over https: the
# repo is public, and CircleCI's checkout key only authorizes this repo.
git clone --quiet https://github.com/GSA-TTS/cg-logshipper.git
git -C cg-logshipper checkout --quiet "$CG_LOGSHIPPER_COMMIT"
CG_LOGSHIPPER_SHA=$(git -C cg-logshipper log -1 --pretty=format:"%H")

# Copy in our own custom config
cp -rp project_conf cg-logshipper

cd cg-logshipper

# Increase the acceptable body size for POST bodies; 8K was too small.
sed -i.bak \
    -e "s|client_body_buffer_size 8K;|client_body_buffer_size 16K;|" \
    -e "s|client_max_body_size 8K;|client_max_body_size 16K;|" \
    ./nginx.conf

# Modify the fluentbit config to make outputs environment-specific and add proxy support.
# Only the New Relic output gets a Proxy: s3 is reached directly, so the log
# bucket never becomes egress-proxy traffic.
sed -i.bak \
    -e "s|s3_key_format /fluent-bit-logs/%Y/%m/%d/%H/%M/%S|s3_key_format /fluent-bit-logs/${SPACE}/%Y/%m/%d/%H/%M/%S|" \
    -e "s|\[OUTPUT\]|\[OUTPUT\]\n    # Environment-specific output for ${SPACE}|" \
    -e "/Name newrelic/,/endpoint/ s|endpoint \${NEW_RELIC_LOGS_ENDPOINT}|endpoint \${NEW_RELIC_LOGS_ENDPOINT}\n    Proxy \${PROXYROUTE}|" \
    ./fluentbit.conf

# Don't ship the sed backups to CF
rm -f ./*.bak

# Set environment variables for the Lua script and Fluent Bit to use
echo "export USAGOV_ENVIRONMENT=${SPACE}" >> .profile
echo "export CF_SPACE=${SPACE}" >> .profile
echo "export LOGSHIPPER_VERSION=environment-specific" >> .profile

# Add environment-specific configuration to the New Relic output
cat >> ./fluentbit.conf << EOF

# Environment-specific metadata for New Relic logs
[FILTER]
    name modify
    match *
    add environment ${SPACE}
    add usagov.environment ${SPACE}
    add usagov.logshipper.space ${SPACE}
    add logshipper_version environment-specific
    add logshipper_deployment_type integrated-pipeline
EOF

# Write a status file we can inspect if needed:
echo "USAGov log-shipper deployment version:" >> ./DEPLOYED_VERSION.txt
echo "    built:" $(date) >> ./DEPLOYED_VERSION.txt
echo "    usagov-logshipper branch:" $USAGOV_BRANCH >> ./DEPLOYED_VERSION.txt
echo "    usagov-logshipper commit:" $USAGOV_COMMIT >> ./DEPLOYED_VERSION.txt
echo "    cg-logshipper commit:" $CG_LOGSHIPPER_SHA >> ./DEPLOYED_VERSION.txt
echo "    containertag:" $CONTAINERTAG >> ./DEPLOYED_VERSION.txt
echo "    environment:" $SPACE >> ./DEPLOYED_VERSION.txt
echo "    deployment_type: environment-specific (not tools)" >> ./DEPLOYED_VERSION.txt

# Create a temporary manifest with the environment-specific app name
# Replace the original app name (likely "fluentbit-drain") with our environment-specific name
sed -e "s/fluentbit-drain/${APP_NAME}/g" \
    -e "s/log-shipper-((envname))/${APP_NAME}/g" \
    manifest.yml > manifest-${SPACE}.yml

# The drain URL points at a stable hostname, so pin the route in the manifest
# rather than pushing with --random-route and mapping the real one afterwards.
# A routes: key upstream would mean the pinned commit moved under us.
if grep -q "^[[:space:]]*routes:" manifest-${SPACE}.yml; then
    echo "ERROR: cg-logshipper's manifest now defines its own routes."
    echo "       Re-check CG_LOGSHIPPER_COMMIT (${CG_LOGSHIPPER_COMMIT}) before deploying."
    exit 1
fi
printf '\n  routes:\n    - route: %s\n' "$APP_ROUTE" >> manifest-${SPACE}.yml

# And push the app from the cg-logshipper directory
# Use environment-specific app name through modified manifest
cf push -f manifest-${SPACE}.yml --instances 1 --strategy rolling
