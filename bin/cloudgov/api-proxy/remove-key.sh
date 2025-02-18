#!/bin/bash
set -e  # Exit on any error

# this function is a convenient symantic wrapper around a one-liner
service_exists()
{
  cf service "$1" >/dev/null 2>&1
}

KEY_DOMAIN=${1:-} # The base url of the api.
KEY_NAME=${2:-} # Arbitrary name for the key.

{
  if service_exists "key-storage" ; then
    echo "✅ Key Storage exists.  Proceeding to remove key..."
    KEY_STORE=$(cf curl /v3/service_instances/$(cf service key-storage --guid)/credentials)

    KEY_STORE_ENTRIES=$(echo "$KEY_STORE" | jq -c 'to_entries[]')
    NAME_EXISTS=false
    LENGTH=0

    while read -r ENTRY; do
      DOMAIN=$(echo "$ENTRY" | jq -r '.key')
      if [[ "$DOMAIN" == "$KEY_DOMAIN" ]]; then
      VALUE=$(echo "$ENTRY" | jq -r '.value')
      KEYS=$(jq 'keys' <<< "$VALUE")
      LENGTH=$(jq length <<< "$KEYS")
      for NAME in $(echo "$KEYS" | jq -r '.[]'); do
        if [[ "$NAME" == "$KEY_NAME" ]]; then
          NAME_EXISTS=true
          break;
        fi
      done
      fi
    done <<< "$KEY_STORE_ENTRIES"

    if [[ "$NAME_EXISTS" == true ]]; then

      if [[ "$LENGTH" -gt 1 ]]; then
        KEY_STORE=$(jq --arg KEY_DOMAIN "$KEY_DOMAIN" --arg KEY_NAME "$KEY_NAME" 'del(.[$KEY_DOMAIN][$KEY_NAME])' <<< "$KEY_STORE")
      else
        echo "❗️ Since this is the last key in the domain, the domain will be removed."
        KEY_STORE=$(jq --arg KEY_DOMAIN "$KEY_DOMAIN" 'del(.[$KEY_DOMAIN])' <<< "$KEY_STORE")
      fi
    else
      echo "❌ Key with this name does not exist.  Cancelling key removal."
      exit 1;
    fi

    yes '' | cf update-user-provided-service key-storage -p "$KEY_STORE"
    echo "✅ Key pushed to Key Storage. Restarting proxy."
    cf restart api-proxy
    echo "✅ Proxy restarted."
  else
    echo "❌ Key Storage doesn't exist.  Something has gone wrong with deployment.  Please check and redeploy."
    exit 1
  fi
}