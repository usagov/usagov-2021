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

#echo=echo

ATAG=9600
CTAG=$ATAG
WTAG=$ATAG
STAG=$ATAG
export CDIGEST=@sha256:950b3569546f667776b81a44c96876d3488bfa0fb6d0f699387694e56d46764f
export WDIGEST=@sha256:6f031e36cbfc56aadfb0932fb7f3a2ea24f031587616b1e15bc35c5d49a4229a
export SDIGEST=@sha256:08c245dd259d230aa35f44578af85fe0aa2fd558e731ca2b533a099fa5926649

# echo  cf delete-space $APP_SPACE
# while [ 1 = 1 ]; do clear; $echo cf delete-space $APP_SPACE; sleep 10; done
# exit

#### NOT FOR 2024 CP/DR Exercise
#echo  cf delete-space $EGRESS_SPACE
#$echo cf delete-space $EGRESS_SPACE
#exit

#### NOT FOR 2024 CP/DR Exercise
#echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
#$echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#exit

# echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
# $echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
# echo assertSpaceExists $APP_SPACE
# $echo assertSpaceExists $APP_SPACE
# echo cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE
# echo assertCurSpace $APP_SPACE
# $echo assertCurSpace $APP_SPACE
# exit

# echo cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE
# echo assertCurSpace $APP_SPACE
# $echo assertCurSpace $APP_SPACE
# echo bin/cloudgov/deploy-services PIPE tee ds.log
# $echo bin/cloudgov/deploy-services | tee ds.log
# exit

#echo cf target -s $EGRESS_SPACE
#$echo cf target -s $EGRESS_SPACE
#echo cf create-service s3 basic-sandbox key-value  PIPE tee cskv.log
#$echo cf create-service s3 basic-sandbox key-value  | tee cskv.log
#exit

# echo cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE
# echo  bin/cloudgov/create-service-account dr cci PIPE tee csa.log
# $echo bin/cloudgov/create-service-account dr cci | tee csa.log
# echo  bin/cloudgov/create-service-account dr cfevents PIPE tee csa.log
# $echo bin/cloudgov/create-service-account dr cfevents | tee csa.log
# exit

#
#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#echo  cf delete-service ${APP_SPACE}-usagov-domain
#$echo cf delete-service ${APP_SPACE}-usagov-domain
#exit
#

# echo  cf target -s $APP_SPACE
# $echo cf target -s $APP_SPACE
# echo  cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "beta-dr.usa.gov,cms-dr.usa.gov"}'
# $echo cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "beta-dr.usa.gov,cms-dr.usa.gov"}'
# while [ 1 = 1 ]; do clear; cf service ${APP_SPACE}-usagov-domain; sleep 10; done
# exit

#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#echo bin/cloudgov/setup-egress-for-space
#$echo bin/cloudgov/setup-egress-for-space
#exit

#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#bin/cloudgov/deploy-cms $CTAG $CDIGEST
#exit

#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#bin/cloudgov/deploy-www $STAG $SDIGEST
#exit

# ROUTE_SERVICE_APP_NAME=$WAF_APP \
# ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov \
# PROTECTED_APP_NAMES="$CMS_APP,$WWW_APP" \
# bin/cloudgov/deploy-waf $WTAG $WDIGEST
# exit

#cf set-env $WAF_APP IP_ALLOW_ALL_CMS 1
#cf set-env $WAF_APP IP_ALLOW_ALL_WWW 1
#cf restage $WAF_APP
#exit

###
cat <<'ZZ'
### Run this on the CMS app, to test the connection to RDS
#!/bin/sh
DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_USER=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.username')
DB_PW=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.password')
DB_HOST=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.host')
DB_PORT=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.port')
mysql --protocol=TCP -h$DB_HOST -P$DB_PORT -u$DB_USER -p$DB_PW $DB_NAME
ZZ
exit

#PREFIX=usagov-${DEPLOY_TAG}-${SPACE}
SQL_FILE=usagov.sql

#echo "Attempting to deploy database backup $SQL_FILE to $APP_SPACE"
#$echo cat $SQL_FILE | cf ssh cms -c "cat - > /tmp/$SQL_FILE"
#cf ssh $CMS_APP -c "if [ -f /tmp/$SQL_FILE ]; then . /etc/profile; drush sql-drop -y; cat /tmp/$SQL_FILE | drush sql-cli; drush cr; fi"
