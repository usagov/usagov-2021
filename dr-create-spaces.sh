#!/bin/sh

export echo=echo

ORG=gsa-tts-usagov
APP_SPACE=dev-dr
EGRESS_SPACE=shared-egress-dr

#bin/cloudgov/create-egress-space $EGRESS_SPACE $ORG | tee ce.log
#exit

#bin/cloudgov/create-app-space $APP_SPACE $ORG | tee ca.log
#cf target -s $APP_SPACE
#exit

## this takes a while to create the database service, and emits a FAILED each
## time it tests if the db is active, prior to the db becoming active.
#bin/cloudgov/deploy-services  | tee ds.log
#exit

### not needed -> fixed services script
#####cf target -s $EGRESS_SPACE
#####cf create-service s3 basic-sandbox key-value  | tee cskv.log
#####exit

#cf target -s $APP_SPACE
#bin/cloudgov/create-service-account | tee csa.log
#exit

#cf target -s $APP_SPACE
#15:27 $ bin/cloudgov/container-push USAGOV-812-cf-spaces
###docker push gsatts/usagov-2021:cms-USAGOV-812-cf-spaces
###The push refers to repository [docker.io/gsatts/usagov-2021]
###...
###denied: requested access to the resource is denied

### CMS
CTAG=latest
CDIGEST="sha256:4b511701bbaabac7c6c8352efd67dcc8a97cf7d9cfc6aeb3f62e0f8f85f72ac5"

###cf target -s $APP_SPACE
###bin/cloudgov/deploy-cms cms $CTAG $CDIGEST | tee dcms.log
#### -> still have errors, as newrelic is not present in shared-egress-dr ??
###exit

### WAF
CTAG=latest
CDIGEST="sha256:2ddf0c639b2a92faa008e0b092155329bddcc69d4ed6d6487cbdfcc7c30a4427"

cf target -s $APP_SPACE
DRYRUN= ROUTE_SERVICE_APP_NAME=waf ROUTE_SERVICE_NAME=waf-route-dev-dr-usagov  PROTECTED_APP_NAME=cms bin/cloudgov/deploy-waf $CTAG $CDIGEST | tee dwaf.log
exit
