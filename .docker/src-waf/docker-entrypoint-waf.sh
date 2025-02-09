#!/bin/bash -e

# where do we go to find the cms
if [ -z "$CMS_PROXY" ]; then
  export CMS_PROXY="cms-usagov.apps.internal"
fi;

# where do we go to find the static site
if [ -z "$S3_PROXY" ]; then
  S3_BUCKET=$(echo "$VCAP_SERVICES" | grep '"bucket":' | sed 's/.*"bucket": "\(.*\)",/\1/')
  S3_REGION=$(echo "$VCAP_SERVICES" | grep '"region":' | sed 's/.*"region": "\(.*\)",/\1/')
  export S3_PROXY="$S3_BUCKET.s3-fips.$S3_REGION.amazonaws.com"
fi;

/validate-cidr
BAD_ADDRESS_PRESENT=$?

if [ $BAD_ADDRESS_PRESENT != 0 ]; then
  echo "WARNING: One or more invalid IP addresses were found in IP_ALLOWED and/or IPV6_ALLOWED
  These addresses have been removed from the allow list, and the system will continue to bootstrap normally
  however those addresses will not be present in the allowed list, so this should be investigated ASAP.
  Please note that the invalid IP addresses should appear in the CI/CD build logs."
fi

export IPS_ALLOWED_WWW="$IPS_ALLOWED"
export IPS_ALLOWED_CMS="$IPS_ALLOWED"

# check if no-ip-restriction and add an explicit 'allow all'
if [ "$IP_ALLOW_ALL_WWW" == "1" ]; then
  export IPS_ALLOWED_WWW=$'\n\tallow all;'"$IPS_ALLOWED_WWW";
fi
if [ "$IP_ALLOW_ALL_CMS" == "1" ]; then
  export IPS_ALLOWED_CMS=$'\n\tallow all;'"$IPS_ALLOWED_CMS";
fi

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')
# this overwrites the files in place, so be careful mounting in docker
echo "Inserting environment variables into nginx config templates ... "
for FILE in /etc/nginx/*/*.conf.tmpl /etc/nginx/*.conf.tmpl; do
    if [ -f "$FILE" ]; then
        OUTFILE=${FILE%.tmpl}
        echo " generating $OUTFILE"
        envsubst "$ENV_VARIABLES" < "$FILE" > "$OUTFILE"
    fi
done

# Update the list of IPs blocked via domain lookup:
/etc/nginx/dynamic/deny_domain_by_ip.sh --no-reload

. /opt/modsecurity/activate-rules.sh

# Run crond
exec /usr/sbin/crond -c /etc/crontabs &

exec /cert-watcher.sh &

exec "$@"
