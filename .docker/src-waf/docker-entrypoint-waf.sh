#!/bin/bash -e

valid_cidr() {
  local CIDR="$1"

  # Parse "a.b.c.d/n" into five separate variables
  IFS="./" read -r ip1 ip2 ip3 ip4 N <<< "$CIDR"

  if [ -n "$ip1" -a -n "$ip2" -a -n "$ip3" -a -n "$ip4" -a -n "$N" ]; then
    # Convert IP address from quad notation to integer
    local ip=$(($ip1 * 256 ** 3 + $ip2 * 256 ** 2 + $ip3 * 256 + $ip4))

    # The following exponent calculation cannot handle negative numbers - weed them out
    if [[ $((32 - $N)) -lt 0 ]]; then
      return 1 # CIDR NOT OK!
    fi
    # Remove upper bits and check that all $N lower bits are 0
    if [ $(($ip % 2**(32-$N))) = 0 ]; then
      return 0 # CIDR OK!
    else
      return 1 # CIDR NOT OK!
    fi
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

valid_ipv6() {
  # Set up local variables
  local ip=$1
  # local IFS=.; local -a a=($ip)
  if [[ ! $ip =~ ([a-fA-F0-9]{0,4}:){7}[a-fA-F0-9]{1,4}$ ]]; then
    return 1
  fi
  return 0
}

# This will let some invalid CIDRs through, but it will catch many errors.
valid_ipv6_cidr() {
  local CIDR="$1"
  # Split it on the slash (if we don't have a / it's not a CIDR)
  IFS="/" read -r ip_parts N <<< "$CIDR"

  if [[ ! $ip_parts =~ ^([a-fA-F0-9]{0,4}:){1,7}$ ]]; then
    #Error display moved
    #echo "${ip_parts} not valid ipv6 addr parts"
    return 1
  fi
  # N should be numeric (it should also be a power of two, but we're not checking that right now)
  if [[ $N =~ ^[0-9]+$ ]]; then
    return 0 # CIDR OK!
  else
    return 1 # CIDR NOT OK!
  fi
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

# Encountered any bad addresses?
BAD_ADDRESS_PRESENT=0

# Count addresses checked
IPV4_ADDRESS_COUNT=0
IPV6_ADDRESS_COUNT=0

# which ips are whitelisted - ipv4 only
export IPS_ALLOWED=""
if [ ! -z "$IP_ALLOWED" ]; then
   ### Some of us like to be able to add comments. Strip those out now:
   IPS_NO_COMMENTS=$(echo "$IP_ALLOWED" | sed -r 's/[ \t]*#.*$//g')
   ### discard all characters except 0-9, the period, comma and the semicolon
   ### this allows a variety of (valid) common formats to be safely used as input
   IPS=$(echo $IPS_NO_COMMENTS | sed -r 's/[^0-9.,;\/]//g' | tr ',' ';' | tr ';' ' ')
   for ip in $IPS; do
     ((++IPV4_ADDRESS_COUNT))
     if valid_ip $ip; then
       export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
     elif valid_cidr $ip; then
       export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
     else
       echo "IP VALIDATION WARNING: Encountered invalid IPV4 address: $ip"
       BAD_ADDRESS_PRESENT=1
     fi
   done;
fi

# which ips are whitelisted - ipv6 only
if [ ! -z "$IPV6_ALLOWED" ]; then
   ### Some of us like to be able to add comments. Strip those out now:
   IPV6S_NO_COMMENTS=$(echo "$IPV6_ALLOWED" | sed -r 's/[ \t]*#.*$//g')
   IPV6S=$(echo $IPV6S_NO_COMMENTS | sed -r 's/[^0-9A-Za-z:\/ ]//g' | tr ',' ';' | tr ';' ' ')
   for ip in $IPV6S; do
     ((++IPV6_ADDRESS_COUNT))
     if valid_ipv6 $ip; then
       export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
     elif valid_ipv6_cidr $ip; then
       export IPS_ALLOWED=$'\n\tallow '$ip';'"$IPS_ALLOWED";
     else
       echo "IP VALIDATION WARNING: Encountered invalid IPV6 address: $ip"
       BAD_ADDRESS_PRESENT=1
     fi
   done;
fi

export IPS_ALLOWED

IP_ADDRESS_COUNT=$((IPV4_ADDRESS_COUNT+IPV6_ADDRESS_COUNT))
echo "INFO: $IP_ADDRESS_COUNT IP addresses checked"

if [ $BAD_ADDRESS_PRESENT != 0 ]; then
  echo "WARNING: One or more invalid IP addresses were found in IP_ALLOWED and/or IPV6_ALLOWED
  These addresses have been removed from the allow list, and the system will continue to bootstrap normally
  however those addresses will not be present in the allowed list, so this should be investigated ASAP."
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

# SFTWR_AUDIT: emit software versions for monthly security audit log search
OS_VERSION=$(grep PRETTY_NAME /etc/os-release 2>/dev/null | cut -d'"' -f2 || echo "unknown")
NGINX_V=$(/usr/sbin/nginx -v 2>&1 | grep -oP 'nginx/\K[0-9.]+' || echo "unknown")
LIBXML2_V=$(/usr/local/bin/xml2-config --version 2>/dev/null || echo "unknown")
WAF_SPACE=$(echo "${VCAP_APPLICATION:-{}}" | grep -oP '"space_name":"\K[^"]+' || echo "unknown")
WAF_APP=$(echo "${VCAP_APPLICATION:-{}}" | grep -oP '"name":"\K[^"]+' | head -1 || echo "unknown")
echo "SFTWR_AUDIT: app=${WAF_APP} space=${WAF_SPACE} os=\"${OS_VERSION}\" nginx=${NGINX_V} modsecurity=${MODSECURITY_ENGINE_VERSION} crs=${MODSECURITY_CRS_VERSION} zlib=${ZLIB_VERSION} libxml2=${LIBXML2_V}"

exec "$@"
