#!/bin/ash
#set -euo pipefail
set -uo pipefail

if [ ! -f /container_start_timestamp ]; then
  touch /container_start_timestamp
  chmod a+r /container_start_timestamp
  echo "$(date +'%s')" > /container_start_timestamp
fi

echo "Deployment: bootstrap starting"

# Add the cloud foundry certificates for communication with other apps in cloud.gov.
# cert-watcher.sh does this too, but we want it to happen before
# any php processes start, and especially before the newrelic-daemon starts.
if [ -d "${CF_SYSTEM_CERT_PATH:-}" ]; then
   cp ${CF_SYSTEM_CERT_PATH:-}/*  /usr/local/share/ca-certificates/
fi
/usr/sbin/update-ca-certificates
