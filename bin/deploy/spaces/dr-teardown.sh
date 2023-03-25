#!/bin/sh
SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
if [ -f $SCRIPT_DIR/../includes ]; then
  . $SCRIPT_DIR/../includes
fi

if [ -f $SCRIPT_DIR/includes ]; then
  . $SCRIPT_DIR/includes
fi

assertEnv DRYRUN CMS_TAG CMS_DIGEST WAF_TAG WAF_DIGEST ORG APP_SPACE EGRESS_SPACE

DRYRUN=

# If DRYRUN is set, don't run the commands, but echo them.
if [ -n "${DRYRUN}" ]; then
    output="echo # "
fi

$output cf target -o $ORG ### get out any spaces
$output cf delete-space -o $ORG $APP_SPACE
$output cf delete-space -o $ORG $EGRESS_SPACE
