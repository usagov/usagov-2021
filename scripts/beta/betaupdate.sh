#!/bin/sh
. /etc/profile
while getopts c:f:h: flag
do
    case "${flag}" in
        c) commands=${OPTARG};;
        f) html_files=${OPTARG};;
        h) html=${OPTARG};;
    esac
done
echo "=================================="
### login to aws & every 15 seconds
aws_access_key_id=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.access_key_id' | tr -d '"'` &&\
aws_secret_access_key=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.secret_access_key' | tr -d '"'` &&\
default_region=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.region' | tr -d '"'` &&\
bucket=`echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')` &&\
aws configure set aws_access_key_id $aws_access_key_id &&\
aws configure set aws_secret_access_key $aws_secret_access_key &&\
aws configure set default.region $default_region
###
echo 'copy css and js to html'
aws s3 cp s3://${bucket}/cms/public/css ${html_files} --recursive --only-show-errors
aws s3 cp s3://${bucket}/cms/public/js ${html_files} --recursive --only-show-errors
echo 'Run tome static' &&\
eval ${commands} &&\
echo 'push html to s3 bucket web directory' &&\
aws s3 cp /tmp/betahtml/html s3://$bucket/web/  --recursive --acl public-read --only-show-errors
