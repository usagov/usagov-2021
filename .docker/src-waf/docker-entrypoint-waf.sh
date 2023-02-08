#!/bin/bash -e

valid_cidr() {
  local CIDR="$1"

  # Parse "a.b.c.d/n" into five separate variables
  IFS="./" read -r ip1 ip2 ip3 ip4 N <<< "$CIDR"

  # Convert IP address from quad notation to integer
  local ip=$(($ip1 * 256 ** 3 + $ip2 * 256 ** 2 + $ip3 * 256 + $ip4))

  # Remove upper bits and check that all $N lower bits are 0
  if [ $(($ip % 2**(32-$N))) = 0 ]; then
    return 0 # CIDR OK!
  else
    return 1 # CIDR NOT OK!
  fi
}

valid_ip() {
  # Set up local variables
  local ip=$1
  local IFS=.; local -a a=($ip)
  # Start with a regex format test
  if [[ ! $ip =~ ^[0-9]+(\.[0-9]+){3}(\/[0-9]+)?$ ]]; then
    return 1
  fi
  # Test values of quads
  local quad
  for quad in {0..3}; do
    if [[ "${a[$quad]}" -gt 255 ]]; then
      return 1
    fi
  done
  return 0
}

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

# which ips are whitelisted
export IPS_ALLOWED=""
if [ ! -z "$IP_ALLOWED" ]; then
   ### Some of us like to be able to add comments. Strip those out now:
   IPS_NO_COMMENTS=$(echo "$IP_ALLOWED" | sed -r 's/^[ \t]*#.*$//g')
   ### discard all characters except 0-9, the period, comma and the semicolon
   ### this allows a variety of (valid) common formats to be safely used as input
   IPS=$(echo $IPS_NO_COMMENTS | sed -r 's/[^0-9.,;\/]//g' | tr ',' ';' | tr ';' ' ')
   for ip in $IPS; do
     if valid_ip $ip; then
       export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
     else
       if valid_cidr $ip; then
         export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
       fi
     fi
   done;
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


# SPACE_NAME=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')
# WWW_HOST_GUESS="beta-${SPACE_NAME}.usa.gov"
# CMS_HOST_GUESS="cms-${SPACE_NAME}.usa.gov"
# if [ "$SPACE_NAME" == "prod" ]; then
#   WWW_HOST_GUESS="beta.usa.gov"
#   CMS_HOST_GUESS="cms.usa.gov"
# elif [ "$SPACE_NAME" == "stage" ]; then
#   WWW_HOST_GUESS="beta-stage.usa.gov"
#   CMS_HOST_GUESS="cms-stage.usa.gov"
# elif [ "$SPACE_NAME" == "dev" ]; then
#   WWW_HOST_GUESS="beta-dev.usa.gov"
#   CMS_HOST_GUESS="cms-dev.usa.gov"
# fi

# WWW_HOST=${WWW_HOST:-$WWW_HOST_GUESS}
# CMS_HOST=${CMS_HOST:-$CMS_HOST_GUESS}
# export WWW_HOST
# export CMS_HOST

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

#ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')
#FILES="/etc/nginx/nginx.conf /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/logging.conf /etc/modsecurity.d/modsecurity-override.conf /etc/nginx/snippets/ip-restrict.conf /etc/nginx/snippets/ip-restrict-cms.conf /etc/nginx/snippets/ip-restrict-www.conf /etc/nginx/snippets/ssl.conf /etc/nginx/snippets/proxy-to-app.conf"
# this overwrites the files in place, so be careful mounting in docker
# for FILE in $FILES; do
#     if [ -f "$FILE" ]; then
#         envsubst "$ENV_VARIABLES" <"$FILE" | sponge "$FILE"
#     fi
# done

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
