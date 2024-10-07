#!/bin/sh

# Run at bootstrap to set samlauth.authentication config values for a given environment

SCRIPT_DIR="$(dirname "$0")"
SPACE=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')
#SPACE=$1
#echo=echo

if [ -f $SCRIPT_DIR/gsaauth.$SPACE.conf ]; then
  while read -r f v; do
    echo  drush cset -y --input-format=yaml samlauth.authentication "${f}" "${v}"
    $echo drush cset -y --input-format=yaml samlauth.authentication "${f}" "${v}"
  done < $SCRIPT_DIR/gsaauth.$SPACE.conf 
else
  echo Cannot find GSA Auth config file for $SPACE: $SCRIPT_DIR/gsaauth.$SPACE.conf 
  exit 1
fi
