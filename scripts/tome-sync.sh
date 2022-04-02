#!/bin/sh

# make sure there is a static site to sync
STATIC_COUNT=$(ls /var/www/html/ | wc -l)
if [ "$STATIC_COUNT" = "0" ]; then
  echo "NO SITE TO SYNC"
  exit 1;
fi;

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')
if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
  export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint');
fi

# grab the cloudgov space we are hosted in
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
APP_SPACE=${APP_SPACE:-local}

# Use a unique dir for each run - just in case more than one of this is running
YMD=$(date +"%Y/%m/%d")
YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")
RENDER_DIR=/tmp/tome/$YMDHMS

mkdir -p $RENDER_DIR
cp -R /var/www/html/* $RENDER_DIR
cd $RENDER_DIR

TOMELOGFILE=$1
if [ -z "$TOMELOGFILE" ]; then
  TOMELOGFILE=$YMD/$APP_SPACE-$YMDHM.log
  TOMELOGDIR=/tmp/tome-log/$YMD
  TOMELOG=$TOMELOGDIR/$APP_SPACE-$YMDHM.log
  mkdir -p $TOMELOGDIR
else
  TOMELOG=/tmp/tome-log/$TOMELOGFILE
fi
touch $TOMELOG

# lower case  everything
for f in `find $RENDER_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  [ "$f" != "$ff" ] && mv -v "$f" "$ff";
done

# endpoint and ssl specifications only necessary on local for minio
# maybe use --only-show-errors if logs are too spammy
if [ "${APP_SPACE}" = "local" ]; then
  aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl 2>&1 | tee -a $TOMELOG
else
  aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read 2>&1 | tee -a $TOMELOG
fi

if [ -f "$TOMELOG" ]; then
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors
fi

if [ -d "$RENDER_DIR" ]; then
  rm -rf $RENDER_DIR
fi
