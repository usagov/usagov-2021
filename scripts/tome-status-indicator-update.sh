#!/bin/sh

PREFIX=/var/www
#PREFIX=.
IND_FILE=${PREFIX}/web/static-site-status.txt

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')
if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
  export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint');
fi

S3_EXTRA_PARAMS=""
if [ "${APP_SPACE}" = "local" ]; then
  S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
fi

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
APP_SPACE=${APP_SPACE:-local}

echo "

Static Site Generator, status as of %timestamp%:

%status%

" > $IND_FILE

TR_START_TIME=$1
if [ -n "$TR_START_TIME" ]; then
    shift
fi

STATUS=$1
if [ -n "$STATUS" ]; then
    shift
fi

if [ -n "$TR_START_TIME" -a -n "$STATUS" ]; then

   humanDateUTC=$(date -u -d @"$TR_START_TIME")
   sed -i "s|%timestamp%|$humanDateUTC|" $IND_FILE
   sed -i "s|%status%|$STATUS|" $IND_FILE
   aws s3 cp $IND_FILE s3://$BUCKET_NAME/web --only-show-errors $S3_EXTRA_PARAMS
else
   echo "To few args"
   exit 1
fi
