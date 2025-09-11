#!/usr/bin/env bash

#####################################################################
## Assist in setting up the DR space from scratch for DR/CP testing
## Additionally, restore the latest prod backups to the DR space
##
## As with all our scripts this expects to be run from the repo root
## (workspace) directory or it will be confused
#####################################################################

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi

# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi

# pull in common functions
if [ -f $SCRIPT_DIR/../bin/deploy/includes ]; then
  . $SCRIPT_DIR/../bin/deploy/includes
else
   echo Cannot find $SCRIPT_DIR/../bin/deploy/includes
   exit 1
fi

# just testing?
if [ x$1 = x"--dryrun" ]; then
  echo=echo
  dryrun=$1
  shift
fi

##############################################################
#
# functions !
#
#(Search 'Begin script' to get to the main part of the script)
#
##############################################################

function setVariables() {
  WWW_APP=www
  WAF_APP=waf
  CMS_APP=cms
  API_PROXY_APP=api-proxy
  ORG=gsa-tts-usagov
  APP_SPACE=dr
  EGRESS_SPACE=shared-egress

  USPS_USERID=XXXXXXX
  USPS_PASSWORD=ZZZZZZ

  echo "WWW_APP:       $WWW_APP"
  echo "WAF_APP:       $WAF_APP"
  echo "CMS_APP:       $CMS_APP"
  echo "API_PROXY_APP: $API_PROXY_APP"
  echo "ORG:           $ORG"
  echo "APP_SPACE:     $APP_SPACE"
  echo "EGRESS_SPACE:  $EGRESS_SPACE"

  source bin/deploy/get-latest-prod-containers
}

function sanityPreamble {
  echo cf target -s $APP_SPACE
  $echo cf target -s $APP_SPACE &> /dev/null
  echo assertCurSpace $APP_SPACE
  $echo assertCurSpace $APP_SPACE
}

function clearExistingBuckets {
  sanityPreamble
  echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
  $echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
  echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-callwait-storage
  $echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-callwait-storage
  echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-event-storage
  $echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-event-storage
  . bin/cloudgov/get-s3-access log-storage
  echo aws s3 rm --recursive s3://$S3_BUCKET/fluent-bit-logs/
  $echo aws s3 rm --recursive s3://$S3_BUCKET/fluent-bit-logs/
  $exit
}

function deleteExistingAppSpace {
  sanityPreamble
  echo  cf delete-space $APP_SPACE
  while [ 1 = 1 ]; do $echo cf delete-space $APP_SPACE; sleep 10; done
  $exit
}

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
function deleteExistingEgressSpace {
  echo "Deleting existing egress space: $EGRESS_SPACE ($CREATE_EGRESS_SPACE)"
#  sanityPreamble
#  echo  cf delete-space $EGRESS_SPACE
#  while [ 1 = 1 ]; do $echo cf delete-space $EGRESS_SPACE; sleep 10; done
#  $exit
}

#####################################################
### Start of recovery. Assume we're in a fresh session and set up env again.
#####################################################

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
function createNewEgressSpace {
  echo "Creating new egress space: $EGRESS_SPACE  ($CREATE_EGRESS_SPACE)"
#  echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
#  $echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#  sanityPreamble
#  $exit
}

function createNewAppSpace {
  echo "Creating new app space: $APP_SPACE  ($CREATE_APP_SPACE)"
#  echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
#  $echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
#  echo assertSpaceExists $APP_SPACE
#  $echo assertSpaceExists $APP_SPACE
#  sanityPreamble
#  echo "************************************************************************"
#  echo "Please remember to add each developer to the SpaceDeveloper group in the"
#  echo "newly created space."
#  echo
#  echo "Add devs in the CloudGov dashboard: https://dashboard.fr.cloud.gov/home"
#  echo "************************************************************************"
#  $exit
}

function deployServices {
  sanityPreamble
  echo bin/cloudgov/deploy-services PIPE tee ds.log
  $echo bin/cloudgov/deploy-services | tee ds.log
  $exit
}

### USAGOV-2473: Allow for parallel runs of cache creation and rds creation.
function deployCacheAndRDSService {
  sanityPreamble
  echo "Open up 2 new terminal sessions, and run one of the two following commands in each terminal:"
  echo bin/cloudgov/deploy-cache-service $APP_SPACE '| tee ds-cache.log'
  echo bin/cloudgov/deploy-rds-service $APP_SPACE '| tee ds-rds.log'
  $exit
}

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
function createS3KeyValueService {
  echo "Creating S3 key-value service: $S3_KEY_VALUE_SERVICE ($CREATE_EGRESS_SPACE)"
#  echo cf target -s $EGRESS_SPACE
#  $echo cf target -s $EGRESS_SPACE
#  echo cf create-service s3 basic key-value  PIPE tee cskv.log
#  $echo cf create-service s3 basic key-value  | tee cskv.log
#  $exit
}

function createCCIServiceAccount {
  sanityPreamble
  echo  bin/cloudgov/create-service-account $APP_SPACE cci PIPE tee csa.log
  $echo bin/cloudgov/create-service-account $APP_SPACE cci | tee csa.log
  $exit
}

function createCFEventsServiceAccount {
  sanityPreamble
  echo  bin/cloudgov/create-service-account $APP_SPACE cfevents PIPE tee csa.log
  $echo bin/cloudgov/create-service-account $APP_SPACE cfevents | tee csa.log
  $exit
}

function setCCIUserRoles {
  #
  # In order to use CircleCI to deploy to our new space, we have to grant PROD's cci service user
  # the proper role:
  #
  echo "Modifying CCI User's Roles in PROD"
  echo cf target -s prod
  $echo cf target -s prod &>/dev/null
  echo assertCurSpace prod
  $echo assertCurSpace prod
  
  SERVICE_KEY=$(cf service-key cci-service-account cci-service-key | tail -n +3)
  SERVICE_USER=$( echo $SERVICE_KEY | jq -r '.credentials.username')
  echo cf set-space-role $SERVICE_USER $ORG $APP_SPACE SpaceDeveloper
  $echo cf set-space-role $SERVICE_USER $ORG $APP_SPACE SpaceDeveloper
  $echo cf target -s $APP_SPACE  &> /dev/null
  $echo assertCurSpace $APP_SPACE
  $exit
}

function createDomainService {
  #
  # The creation of the external domains takes a bit of time, so we'll issue the commands,
  # then deploy the log-shipper, then check status.
  #
  sanityPreamble
  echo bin/cloudgov/create-domain-services-for-space $APP_SPACE
  $echo bin/cloudgov/create-domain-services-for-space $APP_SPACE
  # TODO: this does not create an external domain for the api-proxy.
  if [ x$echo = x ]; then
    echo "Checking domain status every 10 seconds.  Hit Ctrl-C when you see success"
    while [ 1 = 1 ]; do cf service ${APP_SPACE}-cms-usagov-domain; sleep 10; done
  fi
  $exit
}

#
# README:  This sequence needed to be run twice, to successfully deploy the cms app for the first time.
#
function deployCMSApp {
  sanityPreamble
  echo bin/cloudgov/deploy-cms $CCI_BUILD_ID $CMS_DIGEST
  $echo bin/cloudgov/deploy-cms $CCI_BUILD_ID $CMS_DIGEST
  $exit
}

function deployWWWApp {
  sanityPreamble
  echo bin/cloudgov/deploy-www $CCI_BUILD_ID $WWW_DIGEST
  $echo bin/cloudgov/deploy-www $CCI_BUILD_ID $WWW_DIGEST
  $exit
}

function deployAPIProxyApp {
  sanityPreamble
  echo bin/cloudgov/deploy-api-proxy
  $echo bin/cloudgov/deploy-api-proxy
  $exit
}

function deployWAFApp {
  sanityPreamble
  echo export ROUTE_SERVICE_APP_NAME=$WAF_APP ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP,$API_PROXY_APP" 
  echo bin/cloudgov/deploy-waf $CCI_BUILD_ID $WAF_DIGEST
  $echo export ROUTE_SERVICE_APP_NAME=$WAF_APP ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP,$API_PROXY_APP" 
  $echo bin/cloudgov/deploy-waf $CCI_BUILD_ID $WAF_DIGEST
  $exit
}

function deployCronApp {
  sanityPreamble
  echo bin/cloudgov/deploy-cron $APP_SPACE $CRON_BUILD $CRON_DIGEST
  $echo bin/cloudgov/deploy-cron $APP_SPACE $CRON_BUILD $CRON_DIGEST
  $exit
}

function deployAnalyticsReporterApp {
  sanityPreamble
  echo bin/cloudgov/deploy-reporter $APP_SPACE $REPORTER_BUILD $REPORTER_DIGEST
  $echo bin/cloudgov/deploy-reporter $APP_SPACE $REPORTER_BUILD $REPORTER_DIGEST
  $exit
}

##################################################
# README: only do this if you want to expose the servers to public traffic
##################################################
function exposeServers {
  sanityPreamble
  echo  cf set-env $WAF_APP IP_ALLOW_ALL_CMS 1
  $echo cf set-env $WAF_APP IP_ALLOW_ALL_CMS 1
  echo  cf set-env $WAF_APP IP_ALLOW_ALL_WWW 1
  $echo cf set-env $WAF_APP IP_ALLOW_ALL_WWW 1

  echo cf restage $WAF_APP
  $echo cf restage $WAF_APP
  $exit
}

#
# Set up egress proxy. This will also run setup-egress-for apps (--restart option),
#
function setupEgressForSpace {
  sanityPreamble
  echo bin/cloudgov/setup-egress-for-space --restart $EGRESS_SPACE
  $echo bin/cloudgov/setup-egress-for-space --restart $EGRESS_SPACE
  $exit
}

##################################################
# Set up log drains. Return to the log-shipper script, line 65
##################################################

# We need to download the prod snapshots locally:
# Public Files: https://drive.google.com/drive/folders/1tI4k5qasEtmhxCBuznR3t0fe466milYk
# Site Files:   https://drive.google.com/drive/folders/1EFJX3fGe4tyfYtK7T9jTqQ3GVw6Ugk0c
# DB Files:     https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx
#
# Get the LATEST production snapshot files from the above links
# Place them in the current directory
# The files should be named, for example:
#
# Public Files: USAGOV-2416.prod.14113.post-deploy.public.zip
# Site Files:   USAGOV-2416.prod.14113.post-deploy.zip
# DB Files:     USAGOV-2416.prod.14113.post-deploy.sql.gz

# Push the backup files into s3 for the new space.
#
function pushSnapshots {
  sanityPreamble
  echo bin/snapshot-backups/db-dump-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/db-dump-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  echo bin/snapshot-backups/site-folder-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/site-folder-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  echo bin/snapshot-backups/public-folder-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/public-folder-push-to-snapshot ${APP_SPACE} ${SNAPTAG}
  $exit
}

# Create that snapshot on $APP_SPACE, from the local snapshot files we just downloaded.
#
function deployPublicFiles {
  sanityPreamble
  echo bin/snapshot-backups/public-snapshot-deploy ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/public-snapshot-deploy ${APP_SPACE} ${SNAPTAG}
  $exit
}

function deploySiteFiles {
  sanityPreamble
  echo bin/snapshot-backups/site-snapshot-deploy ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/site-snapshot-deploy ${APP_SPACE} ${SNAPTAG}
  $exit
}

##################################################
# Run automated regression tests on the static site now.
# For stage and prod, this is just a matter of clicking a button under "Actions" in github.
# For a non-standard space, a developer with usagov-2021 containers running locally can do this:
# - bin/cypress-ssh
#   in cypress shell:
#   - CYPRESS_BASE_URL=https://beta-dr.usa.gov
#   - npx cypress run --spec cypress/e2e/regression_testing
# - Open the resulting report in a web browser:  ${repo dir}/automated_tests/e2e-cypress/cypress/reports/html/index.html
##################################################

# Deploy the database only if we're not getting a fresher backup from cloud.gov support
function deployDBDump {
  sanityPreamble
  echo bin/snapshot-backups/db-dump-deploy ${APP_SPACE} ${SNAPTAG}
  $echo bin/snapshot-backups/db-dump-deploy ${APP_SPACE} ${SNAPTAG}
  $exit
}

#############################################################
#
# Begin script
#
#############################################################

##############################################################
# By default, exit after each task is successfully completed
# Some day, we'll be able to set this to "" :)
##############################################################
#exit=exit

##############################################################
# In general we want to avoid recreating the app space unless 
# we explicitly need it.
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
fi

if [ $CREATE_APP_SPACE -ne 0 ]; then
  createNewAppSpace
fi

deployServices 
deployCacheAndRDSService 

if [ $CREATE_EGRESS_SPACE -ne 0 ]; then
  createS3KeyValueService
fi

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
