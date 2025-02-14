#!/bin/bash
set -e  # Exit on any error

# this function is a convenient symantic wrapper around a one-liner
service_exists()
{
  cf service "$1" >/dev/null 2>&1
}

{
  if service_exists "key-storage" ; then
    KEY_STORE=$(cf curl /v3/service_instances/$(cf service key-storage --guid)/credentials)

    DOMAINS=$(echo "$KEY_STORE" | jq -r 'keys[]')

    for DOMAIN in $DOMAINS; do
      echo "🌐 Domain: $DOMAIN"
      VALUE=$(jq -r --arg DOMAIN "$DOMAIN" '.[$DOMAIN]' <<< "$KEY_STORE")
      KEYS=$(jq -c 'to_entries[]' <<< "$VALUE")
      while read -r KEY; do
        KEY_NAME=$(jq -r '.key' <<< "$KEY")
        KEY_VALUE=$(jq -r '.value' <<< "$KEY")
        echo "   🔑 $KEY_NAME: $KEY_VALUE"
      done <<< "$KEYS"
    done

  else
    echo "❌ Key Storage doesn't exist.  Something has gone wrong with deployment.  Please check and redeploy."
    exit 1
  fi
}