#!/bin/sh
www=/var/www &&\
html=${www}/html &&\
html_files=${html}/s3/files/ &&\
theme=${www}/web/themes/custom/usagov &&\
URI=${1:-https://beta.usa.gov}

### login to aws & every 15 seconds
aws_access_key_id=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.access_key_id' | tr -d '"'`
aws_secret_access_key=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.secret_access_key' | tr -d '"'`
default_region=`echo $VCAP_SERVICES | jq '.s3[] | select(.name=="storage") | .credentials.region' | tr -d '"'`
bucket=`echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')`
aws configure set aws_access_key_id $aws_access_key_id
aws configure set aws_secret_access_key $aws_secret_access_key
aws configure set default.region $default_region
###

aws s3 sync s3://$S3_BUCKET/cms/public/css ${html_files}/css &&\
aws s3 sync s3://$S3_BUCKET/cms/public/js ${html_files}/js
drush cr --root=${www} && drush tome:static -y --uri=$URI --process-count=10 --path-count=10 --root=${www} &&\
echo 'push html to s3 bucket web directory' &&\
aws s3 sync ${html} s3://$bucket/web/  --acl public-read 