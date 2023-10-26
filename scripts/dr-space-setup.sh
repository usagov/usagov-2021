#!/bin/sh

#####################################################################
##
## This is meant to be a temporary script to assist in setting up
## the DR spaces for testing USAGOV-1083 w/ non-standard DNS records
##
#####################################################################

ORG=gsa-tts-usagov
APP_SPACE=dr
EGRESS_SPACE=shared-egress-dr


#echo  cf delete-space $APP_SPACE
#$echo cf delete-space $APP_SPACE
#echo  cf delete-space $EGRESS_SPACE
#$echo cf delete-space $EGRESS_SPACE
#echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
#$echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#exit

#echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
#$echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
#echo cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#exit

#echo cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#echo bin/cloudgov/deploy-services  PIPE tee ds.log
#$echo bin/cloudgov/deploy-services  | tee ds.log

#echo cf target -s $EGRESS_SPACE
#$echo cf target -s $EGRESS_SPACE
#echo cf create-service s3 basic-sandbox key-value  PIPE tee cskv.log
#$echo cf create-service s3 basic-sandbox key-value  | tee cskv.log
#echo cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#exit

#echo  bin/cloudgov/create-service-account PIPE tee csa.log
#$echo bin/cloudgov/create-service-account | tee csa.log

#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#exit

#echo  cf delete-service ${APP_SPACE}-usagov-domain
#$echo cf delete-service ${APP_SPACE}-usagov-domain
#echo  cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
#$echo cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
#echo  cf service ${APP_SPACE}-usagov-domain
#$echo cf service ${APP_SPACE}-usagov-domain
#exit

#echo  cf target -s $APP_SPACE
#$echo cf target -s $APP_SPACE
#echo  bin/cloudgov/create-service-account PIPE tee csa2.log
#$echo bin/cloudgov/create-service-account | tee csa2.log
#exit

### CMS
#CTAG=5936
#CDIGEST="@sha256:d971a1d9d90ef9b20fd53adfd1c5772636ae682f5faa1c6d9ac9cbe8eb2750cd"
#bin/cloudgov/deploy-cms $CTAG $CDIGEST
#exit

### WAF
#WTAG=5936
#WDIGEST="@sha256:69d3fe9c373562ad42c8d8d0efe99d187957e45e4968dd43a4539198b15d12a"

ROUTE_SERVICE_APP_NAME=waf \
ROUTE_SERVICE_NAME=waf-route-dr-usagov \
PROTECTED_APP_NAME=cms \
bin/cloudgov/deploy-waf $WTAG $WDIGEST
