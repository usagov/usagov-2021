#!/bin/sh

TOMELOGFILE=$1
YMDHMS=$2

TOME_MAX_CHANGE_ALLOWED=0.10

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

S3_EXTRA_PARAMS=""
if [ "${APP_SPACE}" = "local" ]; then
  S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
fi

# Use a unique dir for each run - just in case more than one of this is running
RENDER_DIR=/tmp/tome/$YMDHMS

mkdir -p $RENDER_DIR
cp -R /var/www/html/* $RENDER_DIR
cd $RENDER_DIR

TOMELOG=/tmp/tome-log/$TOMELOGFILE
touch $TOMELOG

# lower case  everything
for f in `find $RENDER_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  [ "$f" != "$ff" ] && mv -v "$f" "$ff";
done


# get a count of current AWS files
echo "S3 dir storage files : count total" | tee -a $TOMELOG
S3_COUNT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | sort -u | wc -l | tee -a $TOMELOG)
echo "S3 dir storage files : count by entension" | tee -a $TOMELOG
aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | grep -o ".[^.]\+$" | sort | uniq -c | tee -a $TOMELOG

# get a count of tome generated files
echo "Tome generated files : count total" | tee -a $TOMELOG
TOME_COUNT=$(find $RENDER_DIR -type f 2>&1 | sort -u | wc -l | tee -a $TOMELOG)
echo "Tome generated files : count by extension" | tee -a $TOMELOG
find $RENDER_DIR -type f 2>&1 | sort -u | grep -o ".[^.]\+$" | uniq -c | tee -a $TOMELOG

# calculate the diff

DIFF_S3_TOME=$(echo "scale=2; $S3_COUNT - $TOME_COUNT" | bc)
DIFF_S3_TOME_OVER=$(echo "scale=2; $DIFF_S3_TOME < 0" | bc)
DIFF_S3_TOME_UNDER=$(echo "scale=2; $DIFF_S3_TOME > 0" | bc)

DIFF_S3_TOME=${DIFF_S3_TOME#-}
DIFF_S3_TOME_PCT=$(echo "scale=2; $DIFF_S3_TOME / $S3_COUNT" | bc)
DIFF_S3_TOME_PCT=${DIFF_S3_TOME_PCT#-}
DIFF_S3_TOME_IS_BAD=$(echo "scale=2; $DIFF_S3_TOME_PCT > $TOME_MAX_CHANGE_ALLOWED" | bc)

TOME_TOO_MUCH=$( ($DIFF_S3_TOME_IS_BAD == "1") && ($DIFF_S3_TOME_OVER == "1") && echo "1" || echo "0" )
TOME_TOO_LITTLE=$( ($DIFF_S3_TOME_IS_BAD == "1") && ($DIFF_S3_TOME_UNDER == "1") && echo "1" || echo "0" )

if [ "$DIFF_S3_TOME_OVER" == "1" ]; then
  echo "Tome static build looks suspicious - adding more content than expected - Currently Have ($S3_COUNT) - Tome Generated ($TOME_COUNT)" | tee $TOMELOG
  # send message, but continue on
  # write message to php log so newrelic will see it
fi
if [ "$DIFF_S3_TOME_UNDER" == "1" ]; then
  echo "Tome static build failure - removing more content than expected - Currently Have ($S3_COUNT) - Tome Generated ($TOME_COUNT)" | tee $TOMELOG
  # send message, and abort
  # write message to php log so newrelic will see it
  exit 3
fi

# endpoint and ssl specifications only necessary on local for minio
# maybe use --only-show-errors if logs are too spammy
aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG

if [ -f "$TOMELOG" ]; then
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
fi

if [ -d "$RENDER_DIR" ]; then
  rm -rf $RENDER_DIR
fi
