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

#####################################################################
##
## This is meant to be a temporary script to assist in setting up
## the DR spaces for testing USAGOV-1083 w/ non-standard DNS records
##
#####################################################################

WWW_APP=www
WAF_APP=waf
CMS_APP=cms
ORG=gsa-tts-usagov
APP_SPACE=dr
EGRESS_SPACE=shared-egress

echo "WWW_APP:      $WWW_APP"
echo "WAF_APP:      $WAF_APP"
echo "CMS_APP:      $CMS_APP"
echo "ORG:          $ORG"
echo "APP_SPACE:    $APP_SPACE"
echo "EGRESS_SPACE: $EGRESS_SPACE"

source bin/deploy/get-latest-prod-containers

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &> /dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
$echo bin/cloudgov/s3-clear-bucket --proceed-with-bucket-content-deletion $APP_SPACE
bin/cloudgov/get-s3-access log-storage
aws s3 rm --recursive s3://$S3_BUCKET/fluent-bit-logs/
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
#echo cf create-service s3 basic-sandbox key-value  PIPE tee cskv.log
#$echo cf create-service s3 basic-sandbox key-value  | tee cskv.log
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
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
echo  cf delete-service ${APP_SPACE}-usagov-domain
$echo cf delete-service ${APP_SPACE}-usagov-domain
exit

#
# The creation of the external domains takes a bit of time, so we will loop over
# a status command, and wait until we see success (or failure) and Ctl-C out of the loop
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
DOMAIN_STRING="{\"domains\": \"beta-${APP_SPACE}.usa.gov, cms-${APP_SPACE}.usa.gov\"}"
echo  cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c "$DOMAIN_STRING"
$echo cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c "$DOMAIN_STRING"
while [ 1 = 1 ]; do cf service ${APP_SPACE}-usagov-domain; sleep 10; done
exit

#
# README:  This sequence needed to be run twice, to successfully deploy the cms app for the first time.
#
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
bin/cloudgov/deploy-cms $CCI_BUILD_ID $CMS_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
bin/cloudgov/deploy-www $CCI_BUILD_ID $WWW_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
ROUTE_SERVICE_APP_NAME=$WAF_APP \
ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov \
PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP" \
bin/cloudgov/deploy-waf $CCI_BUILD_ID $WAF_DIGEST
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
cf set-env $WAF_APP IP_ALLOW_ALL_CMS 1
cf set-env $WAF_APP IP_ALLOW_ALL_WWW 1
cf restage $WAF_APP
exit

#
# In order to get the backed up files into the new space, we need to get the prod snapshot locally,
# then create that snapshot on dr, from the local snapshot file we just downloaded.
#
# Then we can deploy the public snapshot as usual
#
echo cf target -s prod
$echo cf target -s prod &>/dev/null
echo assertCurSpace prod
$echo assertCurSpace prod
bin/snapshot-backups/local-snapshot-download prod USAGOV-1669.prod.9820.post-deploy

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
bin/snapshot-backups/db-dump-push-to-snapshot dr USAGOV-1669.prod.9820.post-deploy
bin/snapshot-backups/db-dump-deploy dr USAGOV-1669.prod.9820.post-deploy
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
bin/snapshot-backups/public-folder-push-to-snapshot dr USAGOV-1669.prod.9820.post-deploy
bin/snapshot-backups/public-snapshot-deploy dr USAGOV-1669.prod.9820.post-deploy
exit

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE &>/dev/null
echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE
bin/snapshot-backups/site-folder-push-to-snapshot dr USAGOV-1669.prod.9820.post-deploy
bin/snapshot-backups/site-snapshot-deploy dr USAGOV-1669.prod.9820.post-deploy
exit

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
