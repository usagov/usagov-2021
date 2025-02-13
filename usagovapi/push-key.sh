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
    echo "✅ Key Storage exists.  Proceeding to push key..."
    KEY_STORE=$(cf curl /v3/service_instances/$(cf service key-storage --guid)/credentials)

    echo "before KEY_STORE: $KEY_STORE"

    KEY_STORE_ENTRIES=$(echo "$KEY_STORE" | jq -c 'to_entries[]')
    NAME_EXISTS=false
    KEY_EXISTS=false

    while read -r ENTRY; do
      DOMAIN=$(echo "$ENTRY" | jq -r '.key')
      if [[ "$DOMAIN" == "$KEY_DOMAIN" ]]; then
      VALUE=$(echo "$ENTRY" | jq -r '.value')
      keys=$(jq 'keys' <<< "$VALUE")
      for NAME in $(echo "$keys" | jq -r '.[]'); do

        APIKEY=$(jq -r ".$NAME" <<< "$VALUE")
        if [[ "$APIKEY" == "$KEY_VALUE" ]]; then
          KEY_EXISTS=true
          break;
        fi
        if [[ "$NAME" == "$KEY_NAME" ]]; then
          NAME_EXISTS=true
          break;
        fi

      done
      fi
    done <<< "$KEY_STORE_ENTRIES"


    if [[ "$KEY_EXISTS" == true ]]; then
      echo "❌ Key is identical to current key.  Cancelling import."
      exit 1;
    fi

    if [[ "$NAME_EXISTS" == true ]]; then
      read -p "❓ Key with this name already exists.  Update key? " -n 1 -r
      echo
      if [[ ! $REPLY =~ ^[Yy]$ ]]; then
          # handle exits from shell or function but don't exit interactive shell
          echo "❌ Cancelling key import."
          exit 1;
      else
        KEY_STORE=$(jq --arg KEY_DOMAIN "$KEY_DOMAIN" --arg KEY_NAME "$KEY_NAME" --arg KEY_VALUE "$KEY_VALUE" '.[$KEY_DOMAIN][$KEY_NAME] = $KEY_VALUE' <<< "$KEY_STORE")
      fi
    else
      KEY_STORE=$(jq --arg KEY_DOMAIN "$KEY_DOMAIN" --arg KEY_NAME "$KEY_NAME" --arg KEY_VALUE "$KEY_VALUE" '.[$KEY_DOMAIN][$KEY_NAME] = $KEY_VALUE' <<< "$KEY_STORE")
    fi

    echo "after KEY_STORE: $KEY_STORE"
    # yes '' | cf update-user-provided-service key-storage -p $KEY_STORE
    echo "✅ Key pushed to Key Storage."
  else
    echo "❌ Key Storage doesn't exist.  Something has gone wrong with deployment.  Please check and redeploy."
    exit 1
  fi
}