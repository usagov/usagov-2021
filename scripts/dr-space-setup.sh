#!/usr/bin/env bash

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

#####################################################################
##
## This is meant to be a temporary script to assist in setting up
## the DR spaces for testing USAGOV-1083 w/ non-standard DNS records
##
#####################################################################

setVariables

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &> /dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE

echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
$echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-callwait-storage
$echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-callwait-storage
echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-event-storage
$echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE cron-event-storage
. bin/cloudgov/get-s3-access log-storage
echo aws s3 rm --recursive s3://$S3_BUCKET/fluent-bit-logs/
$echo aws s3 rm --recursive s3://$S3_BUCKET/fluent-bit-logs/
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &> /dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo  cf delete-space $APP_SPACE
while [ 1 = 1 ]; do $echo cf delete-space $APP_SPACE; sleep 10; done
exit

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
#echo  cf delete-space $EGRESS_SPACE
#$echo cf delete-space $EGRESS_SPACE
#exit

#####################################################
### Start of recovery. Assume we're in a fresh session and set up env again.
#####################################################

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
#echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
#$echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#exit

echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
$echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
echo assertSpaceExists $APP_SPACE
$echo assertSpaceExists $APP_SPACE
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &> /dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo "************************************************************************"
echo "Please remember to add each developer to the SpaceDeveloper group in the"
echo "newly created space."
echo
echo "Add devs in the CloudGov dashboard: https://dashboard.fr.cloud.gov/home"
echo "************************************************************************"
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/deploy-services PIPE tee ds.log
$echo bin/cloudgov/deploy-services | tee ds.log
exit

#####################################################
### README: !!! Only if re-creating egress space!!!
#####################################################
#echo cf target -s $EGRESS_SPACE
#$echo cf target -s $EGRESS_SPACE
#echo cf create-service s3 basic key-value  PIPE tee cskv.log
#$echo cf create-service s3 basic key-value  | tee cskv.log
#exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE  &> /dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo  bin/cloudgov/create-service-account $APP_SPACE cci PIPE tee csa.log
$echo bin/cloudgov/create-service-account $APP_SPACE cci | tee csa.log
echo  bin/cloudgov/create-service-account $APP_SPACE cfevents PIPE tee csa.log
$echo bin/cloudgov/create-service-account $APP_SPACE cfevents | tee csa.log
exit

#
# In order to use CircleCI to deploy to our new space, we have to grant Prod's cci service user
# the proper role:
#
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
exit

#
# The creation of the external domains takes a bit of time, so we'll issue the commands,
# then deploy the log-shipper, then check status.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/create-domain-services-for-space $APP_SPACE
$echo bin/cloudgov/create-domain-services-for-space $APP_SPACE
# TODO: this does not create an external domain for the api-proxy.


##################################################
### We probably want to move this after the cms/www/waf app deployments ?
##################################################
#echo "This will take awhile. Deploy the log-shipper, using bin/dr-space-setup.sh in the log-shipper repo, then come back."
#exit


#
# Check status of external domains. Ctrl-C out when you see success.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo "Run the following, and then Ctrl-C when you see success:"
echo "while [ 1 = 1 ]; do cf service ${APP_SPACE}-cms-usagov-domain; sleep 10; done"
exit

#
# README:  This sequence needed to be run twice, to successfully deploy the cms app for the first time.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/deploy-cms $CCI_BUILD_ID $CMS_DIGEST
$echo bin/cloudgov/deploy-cms $CCI_BUILD_ID $CMS_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/deploy-www $CCI_BUILD_ID $WWW_DIGEST
$echo bin/cloudgov/deploy-www $CCI_BUILD_ID $WWW_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo "deploy-api-proxy is expected to fail with \"Failed to configure network policies\". That is OKAY. deploy-waf will do those"
echo bin/cloudgov/deploy-api-proxy
$echo bin/cloudgov/deploy-api-proxy
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo export ROUTE_SERVICE_APP_NAME=$WAF_APP ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP,$API_PROXY_APP" 
echo bin/cloudgov/deploy-waf $CCI_BUILD_ID $WAF_DIGEST
$echo export ROUTE_SERVICE_APP_NAME=$WAF_APP ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP,$API_PROXY_APP" 
$echo bin/cloudgov/deploy-waf $CCI_BUILD_ID $WAF_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/deploy-cron $APP_SPACE $CRON_BUILD $CRON_DIGEST
$echo bin/cloudgov/deploy-cron $APP_SPACE $CRON_BUILD $CRON_DIGEST
exit

##################################################
# README: only do this if you want to expose the servers to public traffic
##################################################
# echo cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE &>/dev/null
# echo assertCurSpace $APP_SPACE
# $echo assertCurSpace $APP_SPACE
# cf set-env $WAF_APP IP_ALLOW_ALL_CMS 1
# cf set-env $WAF_APP IP_ALLOW_ALL_WWW 1
# echo cf restage $WAF_APP
# $echo cf restage $WAF_APP
# exit

#
# Set up egress proxy. This will also run setup-egress-for apps (--restart option),
#
#echo cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE &>/dev/null
#echo assertCurSpace $APP_SPACE
#$echo assertCurSpace $APP_SPACE
#echo bin/cloudgov/setup-egress-for-space --restart $EGRESS_SPACE
#$echo bin/cloudgov/setup-egress-for-space --restart $EGRESS_SPACE
#exit

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

# create an environment variable for the backup tag, for example:
#
export BACKUP_TAG=USAGOV-2424.prod.14197.post-deploy
#
### !!! CHANGE THE ABOVE BACKUP_TAG TO THE LATEST PRODUCTION SNAPSHOT TAG!!!

# Push the backup files into s3 for the new space.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/snapshot-backups/db-dump-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
$echo bin/snapshot-backups/db-dump-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
echo bin/snapshot-backups/site-folder-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
$echo bin/snapshot-backups/site-folder-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
echo bin/snapshot-backups/public-folder-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
$echo bin/snapshot-backups/public-folder-push-to-snapshot ${APP_SPACE} ${BACKUP_TAG}
exit

# Create that snapshot on $APP_SPACE, from the local snapshot files we just downloaded.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/snapshot-backups/public-snapshot-deploy ${APP_SPACE} ${BACKUP_TAG}
$echo bin/snapshot-backups/public-snapshot-deploy ${APP_SPACE} ${BACKUP_TAG}
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/snapshot-backups/site-snapshot-deploy ${APP_SPACE} ${BACKUP_TAG}
$echo bin/snapshot-backups/site-snapshot-deploy ${APP_SPACE} ${BACKUP_TAG}
exit

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
# echo cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE &>/dev/null
# echo assertCurSpace $APP_SPACE
# $echo assertCurSpace $APP_SPACE
# echo bin/snapshot-backups/db-dump-deploy ${APP_SPACE} ${BACKUP_TAG}
# $echo bin/snapshot-backups/db-dump-deploy ${APP_SPACE} ${BACKUP_TAG}
# exit


### Replaced by db-dump-deploy above!
### ### echo cf target -s $APP_SPACE
### ### $echo cf target -s $APP_SPACE &>/dev/null
### ### echo assertCurSpace $APP_SPACE
### ### $echo assertCurSpace $APP_SPACE
### ### SQL_FILE=usagov.sql
### ###
### ### echo "Attempting to deploy database backup $SQL_FILE to $APP_SPACE"
### ### $echo cat $SQL_FILE | cf ssh cms -c "cat - > /tmp/$SQL_FILE"
### ### cf ssh $CMS_APP -c "if [ -f /tmp/$SQL_FILE ]; then . /etc/profile; drush sql-drop -y; cat /tmp/$SQL_FILE | drush sql-cli; drush cr; fi"
### ### exit
