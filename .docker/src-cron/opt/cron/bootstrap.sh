#!/bin/ash
#set -euo pipefail
set -uo pipefail

echo "Deployment: cron container bootstrap starting"

if [ ! -f /container_start_timestamp ]; then
  touch /container_start_timestamp
  chmod a+r /container_start_timestamp
  echo "$(date +'%s')" > /container_start_timestamp
fi

# SFTWR_AUDIT: emit software versions for monthly security audit log search
SPACE=$(echo "${VCAP_APPLICATION:-{}}" | jq -r '.space_name // "unknown"')
APP_NAME=$(echo "${VCAP_APPLICATION:-{}}" | jq -r '.name // "unknown"')
OS_VERSION=$(grep PRETTY_NAME /etc/os-release 2>/dev/null | cut -d'"' -f2 || echo "unknown")
PYTHON_V=$(python3 --version 2>/dev/null | awk '{print $2}' || echo "unknown")
AWSCLI_V=$(aws --version 2>/dev/null | awk '{print $1}' | cut -d'/' -f2 || echo "unknown")
BOTO3_V=$(/opt/venv/bin/pip show boto3 2>/dev/null | grep '^Version:' | awk '{print $2}' || echo "unknown")
CF_V=$(cf --version 2>/dev/null | awk '{print $3}' || echo "unknown")
echo "SFTWR_AUDIT: app=${APP_NAME} space=${SPACE} os=\"${OS_VERSION}\" python=${PYTHON_V} aws_cli=${AWSCLI_V} boto3=${BOTO3_V} cf_cli=${CF_V}"

echo "Deployment: cron container bootstrap complete"
