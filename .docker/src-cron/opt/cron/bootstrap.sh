#!/bin/ash
#set -euo pipefail
set -uo pipefail

echo "Deployment: cron container bootstrap starting"

if [ ! -f /container_start_timestamp ]; then
  touch /container_start_timestamp
  chmod a+r /container_start_timestamp
  echo "$(date +'%s')" > /container_start_timestamp
fi

echo "Deployment: cron container bootstrap complete"
