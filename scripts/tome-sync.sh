#!/bin/sh

TOME_MAX_CHANGE_ALLOWED=0.10

TOMELOGFILE=$1
YMDHMS=$2

if [ -z "$YMDHMS" ]; then
  YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")
fi;

if [ -z "$TOMELOGFILE" ]; then
  TOMELOGFILE="${YMDHMS}.log"
fi;

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

# endpoint and ssl specifications only necessary on local for minio support
S3_EXTRA_PARAMS="--no-verify-ssl"
if [ "${APP_SPACE}" = "local" ]; then
  S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
fi

# Use a unique dir for each run - just in case more than one of this is running
RENDER_DIR=/tmp/tome/$YMDHMS

if [ -d "$RENDER_DIR" ]; then
  rm -rf $RENDER_DIR
fi;
mkdir -p $RENDER_DIR

# copy from tome's output directory to our working directory RENDER_DIR
# RISK: tome's output diretory is not locked, mulitple processes could cause issues
cp -R /var/www/html/* $RENDER_DIR
cd $RENDER_DIR

mkdir -p /tmp/tome-log/
TOMELOG=/tmp/tome-log/$TOMELOGFILE
touch $TOMELOG

# lower case all filenames in the copied dir before uploading
LCF=0
echo "Lower-casing files:"
for f in `find $RENDER_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  if [ "$f" != "$ff" ]; then
    # VERBOSE MODE
    # mv -v "$f" "$ff"
    mv -v "$f" "$ff" > /dev/null
    LCF=$((LCF+1))
  fi
done
echo "    $LCF"

# get a count of current AWS files, total and by extension
echo "S3 dir storage files : count total" | tee -a $TOMELOG
S3_COUNT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | uniq | grep "^\d\{4\}\-" | wc -l)
echo "     $S3_COUNT" | tee -a $TOMELOG
echo "S3 dir storage files : count by extension" | tee -a $TOMELOG
S3_COUNT_BY_EXT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | uniq | grep "^\d\{4\}\-" | grep -o ".[^.]\+$" | sort | uniq -c)
echo "  $S3_COUNT_BY_EXT" | tee -a $TOMELOG

# get a count of tome generated files, total and by extension
echo "Tome generated files : count total" | tee -a $TOMELOG
TOME_COUNT=$(find $RENDER_DIR -type f 2>&1 | uniq | wc -l)
echo "      $TOME_COUNT" | tee -a $TOMELOG
echo "Tome generated files : count by extension" | tee -a $TOMELOG
TOME_COUNT_BY_EXT=$(find $RENDER_DIR -type f 2>&1 | uniq | grep -o ".[^.]\+$" | sort | uniq -c)
echo "  $TOME_COUNT_BY_EXT" | tee -a $TOMELOG

# calculate the raw diff between s3 and tome
DIFF_S3_TOME=$(echo "scale=2; $S3_COUNT - $TOME_COUNT" | bc)
DIFF_S3_TOME_OVER=$(echo "scale=2; $DIFF_S3_TOME < 0" | bc)
DIFF_S3_TOME_UNDER=$(echo "scale=2; $DIFF_S3_TOME > 0" | bc)

# absolute value of diff, percent, and whether we are over th threshold
DIFF_S3_TOME=${DIFF_S3_TOME#-}
DIFF_S3_TOME_PCT=$(echo "scale=2; $DIFF_S3_TOME / $S3_COUNT" | bc)
DIFF_S3_TOME_PCT=${DIFF_S3_TOME_PCT#-}
DIFF_S3_TOME_IS_BAD=$(echo "scale=2; $DIFF_S3_TOME_PCT > $TOME_MAX_CHANGE_ALLOWED" | bc)

# which direction over the threshold are we
TOME_TOO_MUCH=$( [[ "$DIFF_S3_TOME_IS_BAD" == "1" ]] && [[ "$DIFF_S3_TOME_OVER" == "1" ]] && echo "1" || echo "0" )
TOME_TOO_LITTLE=$( [[ "$DIFF_S3_TOME_IS_BAD" == "1" ]] && [[ "$DIFF_S3_TOME_UNDER" == "1" ]] && echo "1" || echo "0" )

TOME_PUSH_NEW_CONTENT=0
# take actions depending on our situations
if [ "$TOME_TOO_MUCH" == "1" ]; then
  echo "Tome static build looks suspicious - adding more content than expected. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)" | tee -a $TOMELOG
  TOME_PUSH_NEW_CONTENT=0
  # send message, but continue on
  # write message to php log so newrelic will see it
elif [ "$TOME_TOO_LITTLE" == "1" ]; then
  echo "Tome static build failure - removing more content than expected. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)" | tee -a $TOMELOG
  TOME_PUSH_NEW_CONTENT=0
  # send message, and abort
  # write message to php log so newrelic will see it
  # exit 3
else
  echo "Tome static build looks fine. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)" | tee -a $TOMELOG
  TOME_PUSH_NEW_CONTENT=1
fi

if [ "$TOME_PUSH_NEW_CONTENT" == "1" ]; then
  # VERBOSE MODE
  # aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
  aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
  aws s3 sync s3://$BUCKET_NAME/cms/public/ s3://$BUCKET_NAME/web/s3/files/ --exclude "php/*" --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
fi

if [ -d "$RENDER_DIR" ]; then
  echo "Removing Render Dir: $RENDER_DIR" | tee -a $TOMELOG
  rm -rf "$RENDER_DIR"
else
  echo "No Render Dir to remove" | tee -a $TOMELOG
fi

if [ -f "$TOMELOG" ]; then
  echo "Saving logs of this run to S3" | tee -a $TOMELOG
  echo "SYNC FINISHED" | tee -a $TOMELOG
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE --only-show-errors $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
else
  echo "No logs of this run to S3 available"
  echo "SYNC FINISHED"
fi
