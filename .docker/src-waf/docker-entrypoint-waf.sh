#!/bin/bash -e

# where do we go to find the cms
if [ -z "$CMS_PROXY" ]; then
  export CMS_PROXY="usagov-cms.apps.internal"
fi;

# where do we go to find the static site
if [ -z "$S3_PROXY" ]; then
  S3_BUCKET=$(echo "$VCAP_SERVICES" | grep '"bucket":' | sed 's/.*"bucket": "\(.*\)",/\1/')
  S3_REGION=$(echo "$VCAP_SERVICES" | grep '"region":' | sed 's/.*"region": "\(.*\)",/\1/')
  export S3_PROXY="$S3_BUCKET.s3-fips.$S3_REGION.amazonaws.com"
fi;

# which ips are whitelisted
export IPS_ALLOWED="$IP_ALLOWED"
# if [ ! -z "$IP_ALLOWED" ]; then
#   read -r -a array <<< $IP_ALLOWED;
#   for element in "${array[@]}"; do
#       if [ ! -z "$element" ]; then
#         export IPS_ALLOWED=$'\n\tallow '$element';'$IPS_ALLOWED;
#       fi;
#   done;
# fi;

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')

FILES="/etc/nginx/nginx.conf /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/logging.conf /etc/modsecurity.d/modsecurity-override.conf /etc/nginx/snippets/ip-restrict.conf /etc/nginx/snippets/ssl.conf"

# this overwrites the files in place, so be careful mounting in docker
for FILE in $FILES; do
    if [ -f "$FILE" ]; then
        envsubst "$ENV_VARIABLES" <"$FILE" | sponge "$FILE"
    fi
done

. /opt/modsecurity/activate-rules.sh

exec "$@"
