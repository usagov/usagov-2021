#!/usr/bin/env bash

#####################################################################
## Assist in setting up the DR space from scratch for DR/CP testing
## Additionally, restore the latest prod backups to the DR space
##
## As with all our scripts this expects to be run from the repo root
## (workspace) directory or it will be confused
#####################################################################

SCRIPT_DIR="$(dirname "$0")"

# Optional includes: we might be running in circleci, or a local dev machine
for f in /home/circleci/project/env.local $SCRIPT_DIR/env.local ./env.local; do
  if [ -f $f ]; then
    . $f
  fi
done

# Mandatory includes:
for inc in $SCRIPT_DIR/../bin/deploy/includes $SCRIPT_DIR/cf-space-setup; do
  if [ ! -f $inc ]; then
    echo "Cannot find $inc"
    exit 1
  fi
  . $inc
done

# just testing?
if [ x$1 = x"--dryrun" ]; then
  echo=echo
  dryrun=$1
  shift
fi

function setVariables() {
  WWW_APP=www
  WAF_APP=waf
  CMS_APP=cms
  API_PROXY_APP=api-proxy
  ORG=gsa-tts-usagov
  APP_SPACE=dr
  EGRESS_SPACE=shared-egress

  USPS_USERID=${USPS_USERID:-XXXXXXX}
  USPS_PASSWORD=${USPS_PASSWORD:-ZZZZZZ}

  echo "WWW_APP:       $WWW_APP"
  echo "WAF_APP:       $WAF_APP"
  echo "CMS_APP:       $CMS_APP"
  echo "API_PROXY_APP: $API_PROXY_APP"
  echo "ORG:           $ORG"
  echo "APP_SPACE:     $APP_SPACE"
  echo "EGRESS_SPACE:  $EGRESS_SPACE"

  source bin/deploy/get-latest-prod-containers
}

##############################################################
# By default, exit after each task is successfully completed
# Some day, we'll be able to set this to "" :)
##############################################################
#exit=exit

##############################################################
# In general we want to avoid recreating the app space unless 
# we explicitly need it (i.e. if it does not exist)
CREATE_APP_SPACE=0

##############################################################
# WE REALLY DO NOT WANT TO RECREATE THE EGRESS SPACE UNLESS 
# WE ARE IN AN ACTUAL DISASTER RECOVERY SITUATION
CREATE_EGRESS_SPACE=0

##############################################################
# Snapshot tag for restoration of latest backup to DR space
# CHANGE THE SNAPTAG TO THE LATEST PRODUCTION SNAPSHOT TAG!!
export SNAPTAG=USAGOV-2424.prod.14197.post-deploy

setVariables

### Check our current cloud foundry space for real, even if we're in dryrun mode
assertCurSpace $APP_SPACE

if [ $CREATE_APP_SPACE -ne 0 ]; then
  clearExistingBuckets
  deleteExistingAppSpace
fi

if [ $CREATE_EGRESS_SPACE -ne 0 ]; then
  deleteExistingEgressSpace
  createNewEgressSpace
  createS3KeyValueService
fi

if [ $CREATE_APP_SPACE -ne 0 ]; then
  createNewAppSpace
fi

deployServices 
deployCacheAndRDSService 

createCCIServiceAccount 
createCFEventsServiceAccount 
setCCIUserRoles 
createDomainService 
deployCMSApp 
deployWWWApp 
deployAPIProxyApp 
deployWAFApp 
deployCronApp 
deployAnalyticsReporterApp 

exposeServers 

### should not need this, as each app deploy calls it
### setupEgressForSpace 

pushSnapshots 
deployPublicFiles 
deploySiteFiles 
deployDBDump 

exit $?
