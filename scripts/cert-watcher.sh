#!/bin/sh

while true ; do
    echo "C2C certificate update ..."

    # Capture the .crt as .pem files for ca-certificates
    sed -ne '/-----BEGIN CERTIFICATE-----/,/-----END CERTIFICATE-----/p' /etc/cf-assets/envoy_config/sds-c2c-cert-and-key.yaml \
      | sed -e 's/^[ \t]*//' \
      > /tmp/sds-c2c-certs

    # count how many certs were pulled
    certcount=$(grep -c -e "-----BEGIN CERTIFICATE-----" /tmp/sds-c2c-certs)

    # pull each cert individually
    for index in $(seq 1 "$certcount"); do
      awk "/-----BEGIN CERTIFICATE-----/{i++}i==$index" /tmp/sds-c2c-certs > "/usr/local/share/ca-certificates/sds-c2c-$index".crt
    done
    rm /tmp/sds-c2c-certs

    # load these certs
    update-ca-certificates 2>&1 > /dev/null || echo ""

    # Do this again when the cert file is modified
    inotifywait -q -e modify /etc/cf-assets/envoy_config/sds-c2c-cert-and-key.yaml
done
