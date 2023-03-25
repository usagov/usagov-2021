#!/bin/sh
SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
if [ -f $SCRIPT_DIR/../includes ]; then
  . $SCRIPT_DIR/../includes
fi

if [ -f $SCRIPT_DIR/includes ]; then
  . $SCRIPT_DIR/includes
fi

assertEnv CMS_TAG CMS_DIGEST WAF_TAG WAF_DIGEST ORG APP_SPACE EGRESS_SPACE

DRYRUN=
export DRYRUN

# If DRYRUN is set, don't run the commands, but echo them.
if [ -n "${DRYRUN}" ]; then
    output="echo # "
fi

#${output}bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG
#${output}cf target -o $ORG -s $EGRESS_SPACE
#${output}bin/cloudgov/deploy-services --egress

#${output}bin/cloudgov/create-app-space $APP_SPACE $ORG
#${output}cf target -o $ORG -s $APP_SPACE
#${output}bin/cloudgov/deploy-services --app

${output}cf target -o $ORG -s $APP_SPACE
${output}bin/cloudgov/deploy-cms cms $CMS_TAG $CMS_DIGEST

#ROUTE_SERVICE_APP_NAME=waf ROUTE_SERVICE_NAME=waf-route-dev-dr-usagov PROTECTED_APP_NAME=cms \
# bin/cloudgov/deploy-waf $WAF_TAG $WAF_DIGEST

#${output}bin/cloudgov/create-egress-for-apps cms
#${output}bin/cloudgov/create-egress-for-apps waf
