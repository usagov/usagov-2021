#!/bin/sh

# make sure there is a static site to sync
STATIC_COUNT=$(ls /var/www/html/ | wc -l)
if [ "$STATIC_COUNT" = "0" ]; then
  echo "NO SITE TO SYNC"
  exit 1;
fi;

TOMELOG=$1

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')
if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
  export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint');
fi

# Use a unique dir for each run - just in case more than one of this is running
UNIQ_DIR=$(date +"%Y_%m_%d_%H_%M_%S")

mkdir -p /tmp/tome/$UNIQ_DIR;
cp -R /var/www/html/* /tmp/tome/$UNIQ_DIR
cd /tmp/tome/$UNIQ_DIR

if [ -z "$TOMELOG" ] || [ ! -f "$TOMELOG" ]; then
  TOMELOG=/tmp/tome/$UNIQ_DIR.log
  touch /tmp/tome/$UNIQ_DIR.log
fi

# lower case  everything
for f in `find /tmp/tome/$UNIQ_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  [ "$f" != "$ff" ] && mv -v "$f" "$ff";
done

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
# endpoint and ssl specifications only necessary on local for minio
# maybe use --only-show-errors if logs are too spammy
if [ "${APP_SPACE}" = "local" ]; then
  aws s3 sync /tmp/tome/$UNIQ_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl 2>&1 | tee -a $TOMELOG
else
  aws s3 sync /tmp/tome/$UNIQ_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read 2>&1 | tee -a $TOMELOG
fi

if [ -f "$TOMELOG" ]; then
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome/$TOMELOG --only-show-errors
fi

if [ -d "/tmp/tome/$UNIQ_DIR" ]; then
  rm -rf /tmp/tome/$UNIQ_DIR
fi
