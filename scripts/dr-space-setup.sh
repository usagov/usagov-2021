#!/bin/sh

#####################################################################
##
## This is meant to be a temporary script to assist in setting up
## the DR spaces for testing USAGOV-1083 w/ non-standard DNS records
##
#####################################################################

WAF_APP=waf-dr
CMS_APP=cms
ORG=gsa-tts-usagov
APP_SPACE=dev-dr
EGRESS_SPACE=shared-egress-dr
CTAG=cms-7149
CDIGEST=@sha256:d5bb5c7cb4f28643e66dc62be24faa4915190780f52befed3d9cd41c8f3d415d
WTAG=waf-7149
WDIGEST=@sha256:6141cbd8010762842da90468b03780732843879230ee05ccfa0870c392e76afc

echo  cf delete-space $APP_SPACE
$echo cf delete-space $APP_SPACE
exit
 
echo  cf delete-space $EGRESS_SPACE
$echo cf delete-space $EGRESS_SPACE
exit
 
echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
$echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
exit
 
echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
$echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
exit
 
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo bin/cloudgov/deploy-services  PIPE tee ds.log
$echo bin/cloudgov/deploy-services  | tee ds.log
exit
 
echo cf target -s $EGRESS_SPACE
$echo cf target -s $EGRESS_SPACE
echo cf create-service s3 basic-sandbox key-value  PIPE tee cskv.log
$echo cf create-service s3 basic-sandbox key-value  | tee cskv.log
exit
 
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  bin/cloudgov/create-service-account PIPE tee csa.log
$echo bin/cloudgov/create-service-account | tee csa.log
exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  cf delete-service ${APP_SPACE}-usagov-domain
$echo cf delete-service ${APP_SPACE}-usagov-domain
exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
$echo cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
#echo  cf service ${APP_SPACE}-usagov-domain
#$echo cf service ${APP_SPACE}-usagov-domain
while [ 1 = 1 ]; do clear; cf service ${APP_SPACE}-usagov-domain; sleep 10; done
exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  bin/cloudgov/create-service-account PIPE tee csa2.log
$echo bin/cloudgov/create-service-account | tee csa2.log
exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
bin/cloudgov/deploy-cms $CTAG $CDIGEST
exit
  
ROUTE_SERVICE_APP_NAME=$WAF_APP \
ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov \
PROTECTED_APP_NAME=$CMS_APP \
bin/cloudgov/deploy-waf $WTAG $WDIGEST
exit

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
