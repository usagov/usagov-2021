#!/bin/sh

TOME_MAX_CHANGE_ALLOWED=0.10

TOMELOGFILE=$1
YMDHMS=$2
FORCE=${3:-0}
RETRY_SEMAPHORE_FILE=/tmp/tome-log/retry-on-next-run

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
S3_EXTRA_PARAMS=""
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
cp -Rp /var/www/html/* $RENDER_DIR
cd $RENDER_DIR

mkdir -p /tmp/tome-log/
TOMELOG=/tmp/tome-log/$TOMELOGFILE
touch $TOMELOG

# Tome is failing to pull in these assets so we will pull them in ourself
echo "Add in any extra or missing files ... "
aws s3 cp --recursive s3://$BUCKET_NAME/cms/public/ $RENDER_DIR/s3/files/ --exclude "php/*" --exclude "*.gz" $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
cp -rfp /var/www/web/themes/custom/usagov/fonts  $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG
cp -rfp /var/www/web/themes/custom/usagov/images $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG
cp -rfp /var/www/web/themes/custom/usagov/assets $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG

# Copy "webroot" assets (files like robots.txt and site.xml)
cp -rfp /var/www/webroot/* $RENDER_DIR/ 2>&1 | tee -a $TOMELOG

echo "Removing unwanted files ... "
rm -rf $RENDER_DIR/jsonapi/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/node/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/es/node/ 2>&1 | tee -a $TOMELOG

# duplicate the logic used by the bootstrap script to find the static site hostname
WWW_HOST=$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep 'www\.usa\.gov' | head -n 1)
WWW_HOST=${WWW_HOST:-$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep -v 'apps.internal' | grep beta | head -n 1)}

# Regenerate the sitemap.
echo "Regenerating sitemap..."
drush ssr
drush ssg

# replacing inaccurate hostnames
echo "Replacing references to CMS hostname ... "
find $RENDER_DIR -type f \( -name "*.css" -o -name "*.js" -o -name "*.html" -o -name "*.xml" \) -exec sed -i 's|cms\(\-[^\.]*\)\?\.usa\.gov|'"$WWW_HOST"'|ig' {} \;

# Modification of the sitemap
SITEMAP_FILE="$RENDER_DIR/sitemap.xml"
# Check if sitemap exists
if [ -f "$SITEMAP_FILE" ]; then
  echo "Updating sitemap ... "

  # Stylesheet line
  STYLING_TEXT='<?xml-stylesheet type="text/xsl" href="/sitemap_generator/default/sitemap.xsl"?>'
  # Promo comment
  PROMO_TEXT='<!--Generated by the Simple XML Sitemap Drupal module: https://drupal.org/project/simple_sitemap.-->'

  # Add a "/" to the end of "localhost/es" in the sitemap and remove the promo comment and the stylesheet.
  sed -i -e "s|/es\"/>|/es/\"/>|g" -e "s|/es</loc>|/es/</loc>|g" -e "s|$STYLING_TEXT||g" -e "s|$PROMO_TEXT||g" $SITEMAP_FILE;

  # Remove the empty lines.
  sed -i '/^$/d' $SITEMAP_FILE

else
  # Sitemap doesn't exists.
  echo "$SITEMAP_FILE does not exist."
fi

# duplicate the logic used by the egress proxy to find bucket names
n=$(echo -E "$VCAP_SERVICES" | jq -r '.s3 | length')
i=0
echo "Replacing references to S3 Bucket hostnames ... "
while [ $i -lt "$n" ]
do
  # Add attached buckets to the allow list
  REF_BUCKET=$(            echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.bucket")
  REF_AWS_ENDPOINT=$(      echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.endpoint" | uniq )
  REF_AWS_ENDPOINT_ALT=$(  echo -E "$REF_AWS_ENDPOINT"  | sed 's/s3\-us\-/s3.us-/' | uniq )
  REF_AWS_FIPS_ENDPOINT=$( echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.fips_endpoint" | uniq )
  echo " ... $REF_BUCKET"
  # the (cms)? of the regex was used for a specfic reference we kept finding that used /public instead of /cms/public
  find $RENDER_DIR -type f \( -name "*.css" -o -name "*.js" -o -name "*.html" \) -exec sed -i 's|'"${REF_BUCKET}.${REF_AWS_ENDPOINT}"'\(/cms\)\?/public/|'"$WWW_HOST"'/s3/files/|ig' {} \;
  find $RENDER_DIR -type f \( -name "*.css" -o -name "*.js" -o -name "*.html" \) -exec sed -i 's|'"${REF_BUCKET}.${REF_AWS_ENDPOINT_ALT}"'\(/cms\)\?/public/|'"$WWW_HOST"'/s3/files/|ig' {} \;
  find $RENDER_DIR -type f \( -name "*.css" -o -name "*.js" -o -name "*.html" \) -exec sed -i 's|'"${REF_BUCKET}.${REF_AWS_FIPS_ENDPOINT}"'\(/cms\)\?/public/|'"$WWW_HOST"'/s3/files/|ig' {} \;
  i=$((i+1))
done


# lower case all filenames in the copied dir before uploading
LCF=0
echo "Lower-casing files:"
old_IFS="$IFS"
IFS=$'\n'
for f in `find $RENDER_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  if [ "$f" != "$ff" ]; then
    # VERBOSE MODE
    # mv -v "$f" "$fff"
    mv -v "$f" "$ff" > /dev/null
    LCF=$((LCF+1))
  fi
done
IFS="$old_IFS"
echo "    $LCF"

# get a count of current AWS files, total and by extension
echo "S3 dir storage files : count total" | tee -a $TOMELOG
S3_COUNT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | uniq | grep "^\d\{4\}\-" | grep -v "\bweb\/s3\/files\/" | wc -l)
echo "     $S3_COUNT" | tee -a $TOMELOG
echo "S3 dir storage files : count by extension" | tee -a $TOMELOG
S3_COUNT_BY_EXT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>&1 | uniq | grep "^\d\{4\}\-" | grep -v "\bweb\/s3\/files\/" | grep -o ".[^.]\+$" | sort | uniq -c)
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
  TOME_PUSH_NEW_CONTENT=1
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
if [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]]; then
  TOME_PUSH_NEW_CONTENT=1
fi

ANALYTICS_DIR=/var/www/website-analytics
echo "Copying $ANALYTICS_DIR to $RENDER_DIR" | tee -a $TOMELOG
cp -rfp "$ANALYTICS_DIR" "$RENDER_DIR"

EN_HOME_HTML_FILE=/var/www/html/index.html
ES_HOME_HTML_FILE=/var/www/html/es/index.html
EN_HOME_HTML_BAD=0
ES_HOME_HTML_BAD=0
if [ -f $ES_HOME_HTML_FILE ]; then
  ES_HOME_HTML_SIZE=$(stat -c%s "$ES_HOME_HTML_FILE")
else
  ES_HOME_HTML_SIZE=0
fi

# Too-small file is probably a redirect page
if [ $ES_HOME_HTML_SIZE -lt 1000 ]; then
  echo "WARNING: *** ES index.html is way too small ($ES_HOME_HTML_SIZE bytes) ***" | tee -a $TOMELOG
  ES_HOME_HTML_BAD=1
fi

# Sometimes Tome generates an English mobile menu on the Spanish home page
ES_HOME_CONTAINS_ENGLISH_MENU=`grep -c 'About us' $ES_HOME_HTML_FILE`
if [ "$ES_HOME_CONTAINS_ENGLISH_MENU" != "0"  ]; then
  echo "WARNING: *** ES index.html appears to contain English nav ***" | tee -a $TOMELOG
  ES_HOME_HTML_BAD=1
fi

# Check for the correct type of cards on the home page. (We can remove these checks if we don't
# see this problem through, oh, 2023-07-30.)
EN_HOME_CORRECT_CARDS=`grep -c 'homepage-card' $EN_HOME_HTML_FILE`
if [ "$EN_HOME_CORRECT_CARDS" == "0" ]; then
  echo "WARNING: *** EN index.html lacks homepage cards ***" | tee -a $TOMELOG
  EN_HOME_HTML_BAD=1
fi
ES_HOME_CORRECT_CARDS=`grep -c 'homepage-card' $ES_HOME_HTML_FILE`
if [ "$ES_HOME_CORRECT_CARDS" == "0" ]; then
  echo "WARNING: *** ES index.html lacks homepage cards ***" | tee -a $TOMELOG
  ES_HOME_HTML_BAD=1
fi

if [ "$EN_HOME_HTML_BAD" == "1" ]; then
  # Delete the known-bad file; it may be re-created correctly on the next run.
  rm $EN_HOME_HTML_FILE
  touch $RETRY_SEMAPHORE_FILE
  TOME_PUSH_NEW_CONTENT=0
fi

if [ "$ES_HOME_HTML_BAD" == "1" ]; then
  # Delete the known-bad file; it may be re-created correctly on the next run.
  rm $ES_HOME_HTML_FILE
  touch $RETRY_SEMAPHORE_FILE
  TOME_PUSH_NEW_CONTENT=0
fi

if [ "$TOME_PUSH_NEW_CONTENT" == "1" ]; then
  echo "Pushing Content to S3: $RENDER_DIR -> $BUCKET_NAME/web/" | tee -a $TOMELOG
  aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
else
  echo "Not pushing content to S3."
fi

if [ -d "$RENDER_DIR" ]; then
  echo "Removing Render Dir: $RENDER_DIR" | tee -a $TOMELOG
  rm -rf "$RENDER_DIR"
else
  echo "No Render Dir to remove" | tee -a $TOMELOG
fi

if [ -f "$TOMELOG" ]; then
  echo "Saving logs of this run to S3: $TOMELOG -> $BUCKET_NAME/tome-log/$TOMELOGFILE" | tee -a $TOMELOG
  echo "SYNC FINISHED" | tee -a $TOMELOG
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
else
  echo "No logs of this run to S3 available"
  echo "SYNC FINISHED"
fi
