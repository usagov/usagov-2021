#!/bin/bash
set -e  # Exit on any error

# this function is a convenient symantic wrapper around a one-liner
service_exists()
{
  cf service "$1" >/dev/null 2>&1
}

KEY_DOMAIN=${1:-} # The base url of the api.
KEY_VALUE=${2:-} # The api key value.
KEY_NAME=${3:-} # Arbitrary name for the key.

{
  if service_exists "key-storage" ; then
    echo "✅ Key Storage exists.  Proceeding to push key..."
    KEY_STORE=$(cf curl /v3/service_instances/$(cf service key-storage --guid)/credentials)

    echo "$KEY_STORE" | jq -c 'to_entries[]' | while read -r KEY_STORE; do
      domain=$(echo "$KEY_STORE" | jq -r '.key')
      echo "Domain: $domain"
      if [[ "$domain" == "$KEY_DOMAIN" ]]; then
        value=$(echo "$KEY_STORE" | jq -r '.value')
        keys=$(jq 'keys' <<< $value)
        for name in "${keys[@]}"; do
          name=$(jq -r ".[]" <<< $name)
          if [[ "$name" == "$KEY_NAME" ]]; then
            read -p "Key with that name already exists.  Update key? " -n 1 -r
            echo
            if [[ ! $REPLY =~ ^[Nn]$ ]]; then
                # handle exits from shell or function but don't exit interactive shell
                [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1;
            fi
          fi
          echo "Name: $name"
          apikey=$(jq -r ".$name" <<< $value)
          echo "API Key: $apikey"
        done
      fi
    done

    # yes '' | cf update-user-provided-service key-storage -p $KEY_STORE
    echo "✅ Key pushed to Key Storage."
  else
    echo "❌ Key Storage doesn't exist.  Something has gone wrong with deployment.  Please check and redeploy."
    exit 1
  fi
}