#!/bin/bash

empty_vcap={}
SPACE=$(echo "${VCAP_APPLICATION:-${empty_vcap}}" | jq -r '.space_name // "unknown"')
APP_NAME=$(echo "${VCAP_APPLICATION:-${empty_vcap}}" | jq -r '.name // "unknown"')
OS_VERSION=$(grep PRETTY_NAME /etc/os-release 2>/dev/null | cut -d'"' -f2 || echo "unknown")
CADDY_V=$(./caddy version 2>/dev/null | awk 'NR==1 {gsub(/^v/, "", $1); print $1}' || echo "unknown")

echo "SFTWR_AUDIT: app=${APP_NAME} space=${SPACE} os=\"${OS_VERSION}\" caddy=${CADDY_V}"

# c2c_cert_watcher() {
#     while true ; do
#         echo "C2C certificate info..."
#         (cd /tmp && ~/bin/c2c-certinfo)

#         # Capture the .crt and .key as .pem files for Caddy
#         sed -ne '/-----BEGIN CERTIFICATE-----/,/-----END CERTIFICATE-----/p' /etc/cf-assets/envoy_config/sds-c2c-cert-and-key.yaml | sed -e 's/^[ \t]*//' > cert.pem
#         sed -ne '/-----BEGIN RSA PRIVATE KEY-----/,/-----END RSA PRIVATE KEY-----/p' /etc/cf-assets/envoy_config/sds-c2c-cert-and-key.yaml | sed -e 's/^[ \t]*//' > key.pem

#         # If Caddy's already up, tell it to reload its config
#         # NOTE: We expect this to fail (the first time, when Caddy isn't running yet)!
#         ./caddy reload > /dev/null 2>&1 || true

#         # Do this again when the cert file is modified
#         inotifywait -q -e modify /etc/cf-assets/envoy_config/sds-c2c-cert-and-key.yaml
#     done
# }

# Fork the certificate-watcher into the background
# c2c_cert_watcher &

# Wait until there's a .key file (which will appear once the loop has run at least once)
# while [ ! -f key.pem ] ; do
#    echo "Waiting for the c2c key to be present..."
#    sleep 1
# done

exec ./caddy run --config Caddyfile
