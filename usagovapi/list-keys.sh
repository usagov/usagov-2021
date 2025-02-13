#!/bin/bash
set -e  # Exit on any error

# this function is a convenient symantic wrapper around a one-liner
service_exists()
{
  cf service "$1" >/dev/null 2>&1
}

KEY_DOMAIN=${1:-} # The base url of the api.
KEY_NAME=${2:-} # Arbitrary name for the key.
KEY_VALUE=${3:-} # The api key value.

{
  if service_exists "key-storage" ; then
    KEY_STORE=$(cf curl /v3/service_instances/$(cf service key-storage --guid)/credentials)

    # KEY_STORE_ENTRIES=$(echo "$KEY_STORE" | jq -c 'to_entries[]')
    DOMAINS=$(echo "$KEY_STORE" | jq -c 'keys')

    while read -r DOMAIN; do
      DOMAINOUT=$(echo "$DOMAIN" | jq -r '.[]')
      echo "🌐 Domain: $DOMAINOUT"
      VALUE=$(jq -r ".$DOMAIN" <<< "$KEY_STORE")
      KEYS=$(jq -c 'to_entries[]' <<< "$VALUE")
      while read -r KEY; do
        KEY_NAME=$(jq -r '.key' <<< "$KEY")
        KEY_VALUE=$(jq -r '.value' <<< "$KEY")
        echo "   🔑 $KEY_NAME: $KEY_VALUE"
      done <<< "$KEYS"
    done <<< "$DOMAINS"

  else
    echo "❌ Key Storage doesn't exist.  Something has gone wrong with deployment.  Please check and redeploy."
    exit 1
  fi
}