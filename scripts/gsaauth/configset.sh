#!/bin/sh

# Run at bootstrap to set samlauth.authentication config values for a given environment

SCRIPT_DIR="$(dirname "$0")"

SPACE=$1
if [ x$SPACE = x ]; then
  SPACE=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')
fi
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')

#echo=echo

SAMLCONF=$SCRIPT_DIR/gsaauth.default.conf
if [ -f $SCRIPT_DIR/gsaauth.$SPACE.conf ]; then
  SAMLCONF=$SCRIPT_DIR/gsaauth.$SPACE.conf
else
  echo "WARNING: Cannot find GSA Auth config file for $SPACE ($SCRIPT_DIR/gsaauth.$SPACE.conf)"
  echo "WARNING: Using default SecureAuth configuration: $SAMLCONF"
fi

while read -r f v; do
  if [ -n "$f" -a -n "$v" ]; then
    key=$(echo "$f" | sed "s/\.1//")
    #echo $key
    #echo $f
    echo  drush cdel -y samlauth.authentication "$key"
    $echo drush cdel -y samlauth.authentication "$key"
    echo  drush cset -y --input-format=yaml samlauth.authentication "$f" "$v"
    $echo drush cset -y --input-format=yaml samlauth.authentication "$f" "$v"
  fi
done < $SAMLCONF
