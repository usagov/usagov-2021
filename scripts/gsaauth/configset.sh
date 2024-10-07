#!/usr/bin/env bash

# Run at bootstrap to set samlauth.authentication config values for a given environment
SPACE=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')
#SPACE=$1
#echo=echo
SCRIPT_DIR="$(dirname "$0")"

if [ -f $SCRIPT_DIR/gsaauth.$SPACE.conf ]; then
  while read -r f v; do
    echo  drush cset -y --input-format=yaml ${f} ${v}
    $echo drush cset -y --input-format=yaml ${f} ${v}
  done < $SCRIPT_DIR/gsaauth.$SPACE.conf 
else
  echo Cannot find GSA Auth config file for $SPACE: $SCRIPT_DIR/gsaauth.$SPACE.conf 
  exit 1
fi
