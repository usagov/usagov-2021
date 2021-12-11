#!/bin/sh -e

if [ -z "$CMS_PROXY" ]; then
  export CMS_PROXY="usagov-cms.apps.internal"
fi;

if [ -z "$S3_PROXY" ]; then
  S3_BUCKET=$(echo "$VCAP_SERVICES" | grep '"bucket":' | sed 's/.*"bucket": "\(.*\)",/\1/')
  S3_REGION=$(echo "$VCAP_SERVICES" | grep '"region":' | sed 's/.*"region": "\(.*\)",/\1/')
  export S3_PROXY="$S3_BUCKET.s3-fips.$S3_REGION.amazonaws.com"
fi;

export IPS_ALLOWED="#;"
if [ ! -z "$IP_ALLOWED" ]; then
  IFS=', ' read -r -a array <<< "$IP_ALLOWED";
  for element in "${array[@]}"; do
      IPS_ALLOWED="\n\tallow $element;$IPS_ALLOWED";
  done;
fi;

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')

FILES="/etc/nginx/nginx.conf /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/logging.conf /etc/modsecurity.d/modsecurity-override.conf /etc/nginx/snippets/ip-restrict.conf /etc/nginx/snippets/cf-proxy.conf"

for FILE in $FILES; do
    if [ -f "$FILE" ]; then
        envsubst "$ENV_VARIABLES" <"$FILE" | sponge "$FILE"
    fi
done

. /opt/modsecurity/activate-rules.sh

# ls -al /etc/nginx/conf.d/
# cat /etc/nginx/conf.d/default.conf

exec "$@"
