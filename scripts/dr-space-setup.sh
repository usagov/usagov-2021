#!/bin/sh
#####################################################################
## This is meant to be a temporary script to assist in setting up
## the DR spaces for testing USAGOV-1083 w/ non-standard DNS records
#####################################################################

WAF_APP=waf-dr
CMS_APP=cms
ORG=gsa-tts-usagov
APP_SPACE=dev-dr
EGRESS_SPACE=shared-egress-dr
CTAG=cms-7230
CDIGEST=@sha256:5dfeaa2eca51fd7ff86a093617b82a95677e779df0d157091f1d117f503742f2

WTAG=waf-7230
WDIGEST=@sha256:cc5450808a054e5da8897dd433bdc575f5c726aa7b42ec2d1e4a18ecebf13c97

echo  cf delete-space $APP_SPACE
while [ 1 = 1 ]; do clear; $echo cf delete-space $APP_SPACE; sleep 10; done
exit
 
echo  cf delete-space $EGRESS_SPACE
$echo cf delete-space $EGRESS_SPACE
#exit
 
echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG  PIPE tee ce.org
$echo bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#exit
 
echo bin/cloudgov/create-app-space $APP_SPACE $ORG PIPE tee ca.log
$echo bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
#exit
 
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo bin/cloudgov/deploy-services  PIPE tee ds.log
$echo bin/cloudgov/deploy-services  | tee ds.log
#exit
 
echo cf target -s $EGRESS_SPACE
$echo cf target -s $EGRESS_SPACE
echo cf create-service s3 basic-sandbox key-value  PIPE tee cskv.log
$echo cf create-service s3 basic-sandbox key-value  | tee cskv.log
#exit
 
echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  bin/cloudgov/create-service-account PIPE tee csa.log
$echo bin/cloudgov/create-service-account | tee csa.log
#exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  cf delete-service ${APP_SPACE}-usagov-domain
$echo cf delete-service ${APP_SPACE}-usagov-domain
#exit
 
echo  cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE
echo  cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
$echo cf create-service external-domain domain ${APP_SPACE}-usagov-domain -c '{"domains": "dev-dr.usa.gov,shared-egress-dr.usa.gov"}'
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
#exit

ROUTE_SERVICE_APP_NAME=$WAF_APP \
ROUTE_SERVICE_NAME=waf-route-${APP_SPACE}-usagov \
PROTECTED_APP_NAME=$CMS_APP \
bin/cloudgov/deploy-waf $WTAG $WDIGEST
exit

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
 
echo "Attempting to deploy database backup $SQL_FILE to $APP_SPACE"

$echo cat $SQL_FILE | cf ssh cms -c "cat - > /tmp/$SQL_FILE"
cf ssh $CMS_APP -c "if [ -f /tmp/$SQL_FILE ]; then . /etc/profile; drush sql-drop -y; cat /tmp/$SQL_FILE | drush sql-cli; drush cr; fi"
