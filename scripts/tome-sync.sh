#!/bin/sh

set -o pipefail

SCRIPT_NAME=$(basename "$0")
TOME_MAX_CHANGE_ALLOWED=0.10
TR_START_TIME=$(date -u +"%s")
SCRIPT_PATH=$(dirname "$0")

TOMELOGFILE=$1
YMDHMS=$2
FORCE=${3:-0}
RETRY_SEMAPHORE_FILE=/tmp/tome-log/retry-on-next-run

# Referenced-only publishing (USAGOV-2781): when set to 1, skip the broad
# cms/public copy and publish only the assets referenced by the rendered HTML.
# Defaults to 0, so the flag is inert unless explicitly enabled per environment.
SSG_REFERENCED_ONLY=${SSG_REFERENCED_ONLY:-0}

if [ -z "$YMDHMS" ]; then
  YMDHMS=$(date +"%Y_%m_%d_%H_%M_%S")
fi

if [ -z "$TOMELOGFILE" ]; then
  TOMELOGFILE="${YMDHMS}.log"
fi

TOMELOG=/tmp/tome-log/$TOMELOGFILE
mkdir -p "$(dirname "$TOMELOG")"
touch "$TOMELOG"

ssg_now() {
  date -u +"%s"
}

ssg_metric_line() {
  phase=$1
  status=$2
  ts=$(ssg_now)
  shift 2

  printf 'SSG_METRIC script=%s phase=%s status=%s ts=%s' "$SCRIPT_NAME" "$phase" "$status" "$ts"
  for field in "$@"; do
    printf ' %s' "$field"
  done
  printf '\n'
}

ssg_metric() {
  ssg_metric_line "$@" | tee -a "$TOMELOG"
}

ssg_metric_end() {
  phase=$1
  start=$2
  status=$3
  now=$(ssg_now)
  duration=$((now - start))
  shift 3

  ssg_metric "$phase" "$status" "duration_s=$duration" "$@"
}

RUN_START=$(ssg_now)
ssg_metric "tome_sync_script" "start" "log_file=$TOMELOGFILE" "ymdhms=$YMDHMS" "force=$FORCE"

sync_failure() {
  phase=$1
  exit_code=$2
  message=$3

  echo "$message" | tee -a "$TOMELOG"
  ssg_metric "$phase" "failed" "exit_code=$exit_code"
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$message"
  ssg_metric_end "tome_sync_script" "$RUN_START" "exit" "exit_code=$exit_code" "reason=$phase"
  exit "$exit_code"
}

collect_s3_inventory() {
  inventory_file=$1
  response_file=$(mktemp "/tmp/tome-s3-response-${YMDHMS}.XXXXXX")

  if ! aws s3api list-objects-v2 --bucket "$BUCKET_NAME" --prefix "web/" --output json $S3_EXTRA_PARAMS > "$response_file" 2>>"$TOMELOG"; then
    rm -f "$response_file"
    return 1
  fi
  if ! jq -r '.Contents[]?.Key' "$response_file" > "$inventory_file"; then
    rm -f "$response_file"
    return 1
  fi
  rm -f "$response_file"
}

collect_render_inventory() {
  inventory_file=$1

  find "$RENDER_DIR" -type f -print > "$inventory_file"
}

inventory_count() {
  wc -l < "$1" | tr -d ' '
}

inventory_extensions() {
  if [ "$2" = "s3" ]; then
    awk '{name = $NF; sub(/^.*\./, ".", name); count[name]++} END {for (extension in count) print count[extension], extension}' "$1" | sort
  else
    awk '{name = $0; sub(/^.*\./, ".", name); count[name]++} END {for (extension in count) print count[extension], extension}' "$1" | sort
  fi
}

# make sure there is a static site to sync
STATIC_SITE_CHECK_START=$(ssg_now)
ssg_metric "static_site_check" "start"
STATIC_COUNT=$(ls /var/www/html/ | wc -l)
if [ "$STATIC_COUNT" = "0" ]; then
  MSG="NO SITE TO SYNC"
  ssg_metric_end "static_site_check" "$STATIC_SITE_CHECK_START" "failed" "static_count=$STATIC_COUNT"
  echo $MSG
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
  ssg_metric_end "tome_sync_script" "$RUN_START" "exit" "exit_code=1" "reason=no_site_to_sync"
  exit 1;
fi;
ssg_metric_end "static_site_check" "$STATIC_SITE_CHECK_START" "end" "static_count=$STATIC_COUNT"

S3_CONFIG_START=$(ssg_now)
ssg_metric "s3_config" "start"

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
  export AWS_REQUEST_CHECKSUM_CALCULATION=when_required
  export AWS_RESPONSE_CHECKSUM_VALIDATION=when_required
fi
ssg_metric_end "s3_config" "$S3_CONFIG_START" "end" "app_space=$APP_SPACE" "bucket=$BUCKET_NAME"

# Use a unique dir for each run - just in case more than one of this is running
RENDER_DIR=/tmp/tome/$YMDHMS

RENDER_DIR_PREP_START=$(ssg_now)
ssg_metric "render_dir_prep" "start" "render_dir=$RENDER_DIR"

if [ -d "$RENDER_DIR" ]; then
  rm -rf $RENDER_DIR
fi;
mkdir -p $RENDER_DIR

# copy from tome's output directory to our working directory RENDER_DIR
# RISK: tome's output diretory is not locked, mulitple processes could cause issues
cp -Rp /var/www/html/* $RENDER_DIR
cd $RENDER_DIR
ssg_metric_end "render_dir_prep" "$RENDER_DIR_PREP_START" "end" "render_dir=$RENDER_DIR"

# Tome is failing to pull in these assets so we will pull them in ourself.
# Copies the entire cms/public bucket into the render tree's /s3/files path.
run_public_file_copy() {
  copy_reason=${1:-broad}
  PUBLIC_FILE_COPY_START=$(ssg_now)
  ssg_metric "public_file_copy" "start" "reason=$copy_reason"
  aws s3 cp --recursive s3://$BUCKET_NAME/cms/public/ $RENDER_DIR/s3/files/ --exclude "php/*" --exclude "*.gz" $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
  PUBLIC_FILE_COPY_SUCCESS=$?
  ssg_metric_end "public_file_copy" "$PUBLIC_FILE_COPY_START" "end" "exit_code=$PUBLIC_FILE_COPY_SUCCESS" "reason=$copy_reason"
  if [ "$PUBLIC_FILE_COPY_SUCCESS" != "0" ]; then
    sync_failure "public_file_copy" "$PUBLIC_FILE_COPY_SUCCESS" "Static site sync failed while copying public files."
  fi
}

echo "Add in any extra or missing files ... "
if [ "$SSG_REFERENCED_ONLY" = "1" ]; then
  echo "Referenced-only mode: skipping broad cms/public copy; referenced assets are copied during image sync." | tee -a $TOMELOG
  ssg_metric "public_file_copy" "skipped" "reason=referenced_only"
else
  run_public_file_copy "broad"
fi

THEME_WEBROOT_COPY_START=$(ssg_now)
ssg_metric "theme_webroot_copy" "start"
cp -rfp /var/www/web/themes/custom/usagov/fonts  $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG
cp -rfp /var/www/web/themes/custom/usagov/images $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG
cp -rfp /var/www/web/themes/custom/usagov/assets $RENDER_DIR/themes/custom/usagov 2>&1 | tee -a $TOMELOG

# --- USAGOV-2515: Copy Drupal image styles to static output ---
if [ -d /var/www/web/sites/default/files/styles ]; then
  echo "Copying Drupal image styles to static output ..."
  mkdir -p "$RENDER_DIR/s3/files/"
  cp -rfp /var/www/web/sites/default/files/styles "$RENDER_DIR/s3/files/styles" 2>&1 | tee -a $TOMELOG
fi


# Copy "webroot" assets (files like robots.txt and site.xml)
cp -rfp /var/www/webroot/* $RENDER_DIR/ 2>&1 | tee -a $TOMELOG
ssg_metric_end "theme_webroot_copy" "$THEME_WEBROOT_COPY_START" "end"

echo "Removing unwanted files ... "
UNWANTED_FILE_REMOVAL_START=$(ssg_now)
ssg_metric "unwanted_file_removal" "start"
rm -rf $RENDER_DIR/jsonapi/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/node/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/es/node/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/s3/files/benefit-finder/api/draft/life-event/ 2>&1 | tee -a $TOMELOG
ssg_metric_end "unwanted_file_removal" "$UNWANTED_FILE_REMOVAL_START" "end"

# WWW_HOST is not present in CMS app, as of USAGOV-1083.
# Determine WWW_HOST based on space name
case $APP_SPACE in
dev)
  WWW_HOST=beta-dev.usa.gov
  ;;
dr)
  WWW_HOST=beta-dr.usa.gov
  ;;
stage)
  WWW_HOST=beta-stage.usa.gov
  ;;
prod)
  WWW_HOST=www.usa.gov
  ;;
*)
  echo "**** WARNING:  generating in cf space '$APP_SPACE' - trying old method of WWW_HOST extraction.  May fail ****"
  WWW_HOST=$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep 'www\.usa\.gov' | head -n 1)
  WWW_HOST=${WWW_HOST:-$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep -v 'apps.internal' | grep beta | head -n 1)}
  ;;
esac

# replacing inaccurate hostnames
# Note that we explicitly do not replace hostnames in .js files, so we can include conditionals based
# on the host; see themes/custom/usagov/scripts/ceoResults.js
HOSTNAME_REWRITE_START=$(ssg_now)
ssg_metric "hostname_rewrites" "start" "www_host=$WWW_HOST"
echo "Replacing references to CMS hostname ... "
find $RENDER_DIR -type f \( -name "*.css" -o -name "*.html" -o -name "*.xml" \) -exec sed -i 's|cms\(\-[^\.]*\)\?\.usa\.gov|'"$WWW_HOST"'|ig' {} \;

# Modification of the sitemap
SITEMAP_FILE="$RENDER_DIR/sitemap.xml"
# Check if sitemap exists
if [ -f "$SITEMAP_FILE" ]; then
  echo "Updating sitemap ... "

  # Stylesheet line
  STYLING_TEXT='<?xml-stylesheet type="text/xsl" href="/sitemap_generator/default/sitemap.xsl"?>'
  # Promo comment
  PROMO_TEXT='<!--Generated by the Simple XML Sitemap Drupal module: https://drupal.org/project/simple_sitemap.-->'

  # Remove the promo comment and the stylesheet from the sitemap.
  sed -i -e "s|$STYLING_TEXT||g" -e "s|$PROMO_TEXT||g" $SITEMAP_FILE;

  # Remove the empty lines.
  sed -i '/^$/d' $SITEMAP_FILE

else
  # Sitemap doesn't exists.
  echo "$SITEMAP_FILE does not exist."
fi

# duplicate the logic used by the egress proxy to find bucket names
n=$(echo -E "$VCAP_SERVICES" | jq -r '.s3 | length')
i=0
HOSTNAME_REWRITE_SED_SCRIPT=$(mktemp "/tmp/tome-s3-hostname-rewrites-${YMDHMS}.XXXXXX")
echo "Replacing references to S3 Bucket hostnames ... "
while [ $i -lt "$n" ]
do
  # Add attached buckets to the allow list
  REF_BUCKET=$(            echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.bucket")
  REF_AWS_ENDPOINT=$(      echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.endpoint" | uniq )
  REF_AWS_ENDPOINT_ALT=$(  echo -E "$REF_AWS_ENDPOINT"  | sed 's/s3\-us\-/s3.us-/' | uniq )
  REF_AWS_FIPS_ENDPOINT=$( echo -E "$VCAP_SERVICES" | jq -r ".s3[$i].credentials.fips_endpoint" | uniq )
  echo " ... $REF_BUCKET"
  # The optional /cms segment handles legacy /public references. Build all
  # replacement rules first so the render tree is scanned only once.
  printf '%s\n' "s|${REF_BUCKET}.${REF_AWS_ENDPOINT}\(/cms\)\?/public/|${WWW_HOST}/s3/files/|ig" >> "$HOSTNAME_REWRITE_SED_SCRIPT"
  printf '%s\n' "s|${REF_BUCKET}.${REF_AWS_ENDPOINT_ALT}\(/cms\)\?/public/|${WWW_HOST}/s3/files/|ig" >> "$HOSTNAME_REWRITE_SED_SCRIPT"
  printf '%s\n' "s|${REF_BUCKET}.${REF_AWS_FIPS_ENDPOINT}\(/cms\)\?/public/|${WWW_HOST}/s3/files/|ig" >> "$HOSTNAME_REWRITE_SED_SCRIPT"
  i=$((i+1))
done
if ! find "$RENDER_DIR" -type f \( -name "*.css" -o -name "*.js" -o -name "*.html" \) -exec sed -i -f "$HOSTNAME_REWRITE_SED_SCRIPT" {} +; then
  rm -f "$HOSTNAME_REWRITE_SED_SCRIPT"
  sync_failure "hostname_rewrites" 1 "Static site sync failed while rewriting S3 bucket hostnames."
fi
rm -f "$HOSTNAME_REWRITE_SED_SCRIPT"
ssg_metric_end "hostname_rewrites" "$HOSTNAME_REWRITE_START" "end" "bucket_count=$n"


################################################################################
# USAGOV-2515: Sync static images referenced in HTML from S3FS/public:// to static output
# and rewrite HTML references to use static file paths. This is done via Drush command.
################################################################################
echo "Running Drush static image sync (usagov:ssg-sync-images) ..." | tee -a $TOMELOG
STATIC_IMAGE_SYNC_START=$(ssg_now)
REFERENCE_ASSET_MANIFEST="/tmp/tome-referenced-assets-${YMDHMS}.tsv"
REFERENCED_ONLY_ARG=""
if [ "$SSG_REFERENCED_ONLY" = "1" ]; then
  REFERENCED_ONLY_ARG="--s3_files_dir=$RENDER_DIR/s3/files"
fi
ssg_metric "static_image_sync" "start" "referenced_only=$SSG_REFERENCED_ONLY"
if drush usagov:ssg-sync-images --html_dir="$RENDER_DIR" --output_files_dir="$RENDER_DIR/files" --reference_manifest_path="$REFERENCE_ASSET_MANIFEST" $REFERENCED_ONLY_ARG 2>&1 | tee -a $TOMELOG; then
  ssg_metric_end "static_image_sync" "$STATIC_IMAGE_SYNC_START" "end" "exit_code=0"
  REFERENCE_ASSET_COUNT=$(awk 'END { print NR - 1 }' "$REFERENCE_ASSET_MANIFEST")
  REFERENCE_ASSET_UNRESOLVED=$(awk -F '\t' '$3 == "unresolved" { count++ } END { print count + 0 }' "$REFERENCE_ASSET_MANIFEST")
  ssg_metric "referenced_asset_manifest" "end" "entry_count=$REFERENCE_ASSET_COUNT" "unresolved_count=$REFERENCE_ASSET_UNRESOLVED" "path=$REFERENCE_ASSET_MANIFEST"
  echo "Drush static image sync completed successfully." | tee -a $TOMELOG

  # Referenced-only guard: if more than ~10% of referenced assets failed to
  # materialize, fall back to the broad cms/public copy so we never publish a
  # render tree missing assets the rendered pages need.
  if [ "$SSG_REFERENCED_ONLY" = "1" ]; then
    if [ "${REFERENCE_ASSET_COUNT:-0}" -gt 0 ] && [ $(( ${REFERENCE_ASSET_UNRESOLVED:-0} * 10 )) -le "${REFERENCE_ASSET_COUNT:-0}" ]; then
      ssg_metric "referenced_only_guard" "passed" "entry_count=$REFERENCE_ASSET_COUNT" "unresolved_count=$REFERENCE_ASSET_UNRESOLVED"
    else
      echo "Referenced-only guard failed (entry_count=$REFERENCE_ASSET_COUNT unresolved_count=$REFERENCE_ASSET_UNRESOLVED); falling back to broad copy." | tee -a $TOMELOG
      ssg_metric "referenced_only_guard" "failed" "entry_count=$REFERENCE_ASSET_COUNT" "unresolved_count=$REFERENCE_ASSET_UNRESOLVED" "action=broad_copy_fallback"
      run_public_file_copy "referenced_only_fallback"
    fi
  fi
else
  STATIC_IMAGE_SYNC_SUCCESS=$?
  ssg_metric_end "static_image_sync" "$STATIC_IMAGE_SYNC_START" "failed" "exit_code=$STATIC_IMAGE_SYNC_SUCCESS"
  sync_failure "static_image_sync" "$STATIC_IMAGE_SYNC_SUCCESS" "Static site sync failed while processing referenced images."
fi

# lower case all filenames in the copied dir before uploading
LCF=0
echo "Lower-casing files:"
LOWERCASE_FILES_START=$(ssg_now)
ssg_metric "lowercase_files" "start"
old_IFS="$IFS"
IFS=$'\n'
# Process paths in reverse depth order (deepest first) to avoid "No such file or directory" errors
# when parent directories are renamed before their children
for f in `find $RENDER_DIR/* -depth`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  if [ "$f" != "$ff" ]; then
    # VERBOSE MODE
    # mv -v "$f" "$fff"
    mv -v "$f" "$ff" > /dev/null 2>&1
    LCF=$((LCF+1))
  fi
done
IFS="$old_IFS"
echo "    $LCF"
ssg_metric_end "lowercase_files" "$LOWERCASE_FILES_START" "end" "renamed_count=$LCF"

TOME_PUSH_NEW_CONTENT=1

ANALYTICS_DIR=/var/www/website-analytics
ANALYTICS_AND_PPR_START=$(ssg_now)
ssg_metric "analytics_and_ppr" "start"
echo "Copying $ANALYTICS_DIR to $RENDER_DIR" | tee -a $TOMELOG
cp -rfp "$ANALYTICS_DIR" "$RENDER_DIR"
export ANALYTICS_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "s3forAnalyticsReporter") | .credentials.bucket')
echo "ANALYTICS_BUCKET is: $ANALYTICS_BUCKET"
echo "Making the website-analytics pages use: $ANALYTICS_BUCKET"
find "$RENDER_DIR/website-analytics" -type f -exec sed -i "s|{{analytics_bucket}}|$ANALYTICS_BUCKET|g" {} +
# Dev Note: Un-comment the next line if you want to confirm the files within the Docker container locally before the sync
# sleep 500

mkdir -p $RENDER_DIR/ppr
cp -fp "/var/www/web/modules/custom/usagov_ssg_postprocessing/files/published-pages.csv" "$RENDER_DIR/ppr/published-pages.csv"
ssg_metric_end "analytics_and_ppr" "$ANALYTICS_AND_PPR_START" "end"

###############################################################################
## The *HOME_HTML* tests looked for problems we have since solved. They remain
## in case such problems recur.
HOME_HTML_CHECKS_START=$(ssg_now)
ssg_metric "home_html_checks" "start"
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

# Checks that the spanish and english home page doesn't have links to /node/[some number] and don't publish when that happens.
ES_HOME_CONTAINS_NODE_LINKS=$(grep -c '/node/[0-9]' $ES_HOME_HTML_FILE)
if [ "$ES_HOME_CONTAINS_NODE_LINKS" != "0"  ]; then
  echo "WARNING: *** ES index.html menu appears to contain links that goes to /node/ pages ***" | tee -a $TOMELOG
  ES_HOME_HTML_BAD=1
fi

EN_HOME_CONTAINS_NODE_LINKS=$(grep -c '/node/[0-9]' $EN_HOME_HTML_FILE)
if [ "$EN_HOME_CONTAINS_NODE_LINKS" != "0"  ]; then
  echo "WARNING: *** EN index.html menu appears to contain links that goes to /node/ pages ***" | tee -a $TOMELOG
  EN_HOME_HTML_BAD=1
fi

# Sometimes Tome generates an English mobile menu on the Spanish home page
ES_HOME_CONTAINS_ENGLISH_MENU=$(grep -E -c 'All topics and services|About USAGov|Military and veterans' $ES_HOME_HTML_FILE)
if [ "$ES_HOME_CONTAINS_ENGLISH_MENU" != "0"  ]; then
  echo "WARNING: *** ES index.html appears to contain English nav ***" | tee -a $TOMELOG
  ES_HOME_HTML_BAD=1
fi

# Sometimes Tome generates an Spanish mobile menu on the English home page
EN_HOME_CONTAINS_SPANISH_MENU=$(grep -E -c 'Todos los temas y servicios|Acerca de USAGov en Español|Fuerzas Armadas de EE. UU. y veteranos' $EN_HOME_HTML_FILE)
if [ "$EN_HOME_CONTAINS_SPANISH_MENU" != "0"  ]; then
  echo "WARNING: *** EN index.html appears to contain Spanish nav ***" | tee -a $TOMELOG
  EN_HOME_HTML_BAD=1
fi

# Check for the correct type of cards on the home page. (We can remove these checks if we don't
# see this problem through, oh, 2023-07-30.)
EN_HOME_CORRECT_CARDS=$(grep -c 'homepage-card' $EN_HOME_HTML_FILE)
if [ "$EN_HOME_CORRECT_CARDS" == "0" ]; then
  echo "WARNING: *** EN index.html lacks homepage cards ***" | tee -a $TOMELOG
  EN_HOME_HTML_BAD=1
fi
ES_HOME_CORRECT_CARDS=$(grep -c 'homepage-card' $ES_HOME_HTML_FILE)
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

#
# End of *HOME_HTML* checks
##############################################################
ssg_metric_end "home_html_checks" "$HOME_HTML_CHECKS_START" "end" "en_home_bad=$EN_HOME_HTML_BAD" "es_home_bad=$ES_HOME_HTML_BAD"



##############################################################
# Missing blog pages; Jira USAGOV-2667
#
BLOG_CHECKS_START=$(ssg_now)
ssg_metric "blog_checks" "start"

BLOG_DIR=/var/www/html/blog
BLOG_TOP_INDEX_MISSING=
BLOG_INDEX_MISSING=""
BLOG_PROBLEM=0

# /blog/index.html should exist
if [ -e "${BLOG_DIR}/index.html" ]; then
    # that's good!
    BLOG_TOP_INDEX_MISSING=
else
    BLOG_TOP_INDEX_MISSING="${BLOG_DIR}"
    BLOG_PROBLEM=1
fi

# every blog/year and blog/year/month directory should have an index.html
yr_mo_idx_missing=""

# Years
yr_mo_idx_partial="`find ${BLOG_DIR} -path '*/[0-9][0-9][0-9][0-9]' -exec test ! -e '{}/index.html' ';' -print`"

if [ "${yr_mo_idx_partial}" ]; then
    yr_mo_idx_missing="${yr_mo_idx_missing} ${yr_mo_idx_partial}"
    BLOG_PROBLEM=1
fi


# Months
yr_mo_idx_partial="`find ${BLOG_DIR} -path '*/[0-9][0-9][0-9][0-9]/[0-9][0-9]' -exec test ! -e '{}/index.html' ';' -print`"

if [ "${yr_mo_idx_partial}" ]; then
    yr_mo_idx_missing="${yr_mo_idx_missing} ${yr_mo_idx_partial}"
    BLOG_PROBLEM=1
fi

# Every */pages/\d+ directory should have an index.html.
page_idx_missing=""

# one-digit directories
page_idx_partial="`find ${BLOG_DIR} -path '*/page/[0-9]' -empty | sed 's/\n/, /g'`"

if [ "${page_idx_partial}" ]; then
    page_idx_missing="${page_idx_missing} ${page_idx_partial}"
    BLOG_PROBLEM=1
fi

# two-digit directories
page_idx_partial="`find ${BLOG_DIR} -path '*/page/[0-9][0-9]' -empty | sed 's/\n/, /g'`"

if [ "${page_idx_partial}" ]; then
    page_idx_missing="${page_idx_missing} ${page_idx_partial}"
    BLOG_PROBLEM=1
fi

if [ "${BLOG_TOP_INDEX_MISSING}" ]; then
    BLOG_INDEX_MISSING="${BLOG_INDEX_MISSING}${BLOG_TOP_INDEX_MISSING} "
    BLOG_PROBLEM=1
fi

if [ "${page_idx_missing}" ]; then
    BLOG_INDEX_MISSING="${BLOG_INDEX_MISSING}$(echo ${page_idx_missing} ) "
    BLOG_PROBLEM=1
fi

if [ "${yr_mo_idx_missing}" ]; then
    BLOG_INDEX_MISSING="${BLOG_INDEX_MISSING}$(echo ${yr_mo_idx_missing} ) "
    BLOG_PROBLEM=1
fi

# If any "missing index" paths were found, log the error.
# Don't prevent publishing, because there are cases where a directory might contain,
# for example, redirects but no index.html file. USAGOV-2693
if [ "${BLOG_PROBLEM}" -ne "0" ]; then
    echo "WARNING: *** BLOG index.html(s) missing: ${BLOG_INDEX_MISSING} ***" | tee -a $TOMELOG
fi

#
# End of blog page checks (USAGOV-2667)
##############################################################
ssg_metric_end "blog_checks" "$BLOG_CHECKS_START" "end" "blog_problem=$BLOG_PROBLEM"



S3_INVENTORY_FILE=""
TOME_INVENTORY_FILE=""
POST_S3_INVENTORY_FILE=""
if [ "$TOME_PUSH_NEW_CONTENT" = "1" ]; then
  PRE_SYNC_SAFETY_COUNTS_START=$(ssg_now)
  ssg_metric "pre_sync_safety_counts" "start"
  S3_INVENTORY_FILE=$(mktemp "/tmp/tome-s3-inventory-${YMDHMS}.XXXXXX")
  TOME_INVENTORY_FILE=$(mktemp "/tmp/tome-render-inventory-${YMDHMS}.XXXXXX")

  if ! collect_s3_inventory "$S3_INVENTORY_FILE"; then
    sync_failure "pre_sync_safety_counts" "1" "Static site sync failed while listing existing S3 output."
  fi
  if ! collect_render_inventory "$TOME_INVENTORY_FILE"; then
    sync_failure "pre_sync_safety_counts" "1" "Static site sync failed while inventorying rendered output."
  fi

  S3_COUNT=$(inventory_count "$S3_INVENTORY_FILE")
  TOME_COUNT=$(inventory_count "$TOME_INVENTORY_FILE")
  echo "S3 output files: $S3_COUNT" | tee -a "$TOMELOG"
  echo "Rendered output files: $TOME_COUNT" | tee -a "$TOMELOG"

  if [ "$S3_COUNT" -eq 0 ]; then
    echo "No existing S3 output files found; allowing initial publish." | tee -a "$TOMELOG"
  else
    DIFF_S3_TOME=$(echo "$S3_COUNT - $TOME_COUNT" | bc)
    DIFF_S3_TOME_OVER=$(echo "$DIFF_S3_TOME < 0" | bc)
    DIFF_S3_TOME_UNDER=$(echo "$DIFF_S3_TOME > 0" | bc)
    DIFF_S3_TOME=${DIFF_S3_TOME#-}
    DIFF_S3_TOME_PCT=$(echo "scale=4; $DIFF_S3_TOME / $S3_COUNT" | bc)
    DIFF_S3_TOME_IS_BAD=$(echo "$DIFF_S3_TOME_PCT > $TOME_MAX_CHANGE_ALLOWED" | bc)

    if [ "$DIFF_S3_TOME_IS_BAD" = "1" ] && [ "$DIFF_S3_TOME_UNDER" = "1" ]; then
      echo "Tome static build looks unsafe - removing more content than expected. Currently have $S3_COUNT and rendered $TOME_COUNT." | tee -a "$TOMELOG"
      TOME_PUSH_NEW_CONTENT=0
    elif [ "$DIFF_S3_TOME_IS_BAD" = "1" ] && [ "$DIFF_S3_TOME_OVER" = "1" ]; then
      echo "Tome static build adds more content than expected. Currently have $S3_COUNT and rendered $TOME_COUNT." | tee -a "$TOMELOG"
    else
      echo "Tome static build count is within the allowed change threshold." | tee -a "$TOMELOG"
    fi
  fi

  if [ "${SSG_DEEP_VALIDATION:-0}" = "1" ]; then
    echo "S3 output files by extension:" | tee -a "$TOMELOG"
    inventory_extensions "$S3_INVENTORY_FILE" s3 | tee -a "$TOMELOG"
    echo "Rendered output files by extension:" | tee -a "$TOMELOG"
    inventory_extensions "$TOME_INVENTORY_FILE" render | tee -a "$TOMELOG"
  fi

  if [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]]; then
    TOME_PUSH_NEW_CONTENT=1
  fi
  ssg_metric_end "pre_sync_safety_counts" "$PRE_SYNC_SAFETY_COUNTS_START" "end" "s3_count=$S3_COUNT" "tome_count=$TOME_COUNT" "push_new_content=$TOME_PUSH_NEW_CONTENT"
else
  ssg_metric "pre_sync_safety_counts" "skipped" "reason=render_safety_check_failed"
fi

SYNC_FAILURE=0
if [ "$TOME_PUSH_NEW_CONTENT" == "1" ]; then
  echo "Pushing Content to S3: $RENDER_DIR -> $BUCKET_NAME/web/" | tee -a $TOMELOG
  S3_SYNC_START=$(ssg_now)
  ssg_metric "s3_sync" "start" "render_dir=$RENDER_DIR" "bucket=$BUCKET_NAME"
  aws s3 sync $RENDER_DIR s3://$BUCKET_NAME/web/ --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
  S3_SYNC_SUCCESS=$?
  ssg_metric_end "s3_sync" "$S3_SYNC_START" "end" "exit_code=$S3_SYNC_SUCCESS"

  # Check if the sync command was successful
  if [ $S3_SYNC_SUCCESS -eq 0 ]; then
      echo "Sync operation completed successfully." | tee -a "$TOMELOG"

      POST_SYNC_VALIDATION_START=$(ssg_now)
      ssg_metric "post_sync_validation" "start"
      POST_S3_INVENTORY_FILE=$(mktemp "/tmp/tome-post-s3-inventory-${YMDHMS}.XXXXXX")

      if ! collect_s3_inventory "$POST_S3_INVENTORY_FILE"; then
        echo "Error: Could not verify published S3 output." | tee -a "$TOMELOG"
        SYNC_FAILURE=1
      else
        S3_COUNT=$(inventory_count "$POST_S3_INVENTORY_FILE")
        TOME_COUNT=$(inventory_count "$TOME_INVENTORY_FILE")
        echo "Published S3 output files: $S3_COUNT" | tee -a "$TOMELOG"
        echo "Rendered output files: $TOME_COUNT" | tee -a "$TOMELOG"

        # Run automatic backups using manager.sh
        BACKUP_MANAGER="$SCRIPT_PATH/snapshot/manager.sh"

        if [ -f "$BACKUP_MANAGER" ]; then
          echo "Starting automatic backups..." | tee -a $TOMELOG

          # Create static and public backups using manager.sh backup command
          # The manager.sh script will handle all the logic, config loading, and smart detection
          # Run from /var/www to ensure manager.sh can find its dependencies
          # Use --throttle to skip backups if one was created recently (configurable via BACKUP_THROTTLE_HOURS)
          if (cd /var/www && $BACKUP_MANAGER backup static,public --throttle) 2>&1 | tee -a $TOMELOG; then
              echo "Automatic backup completed successfully." | tee -a $TOMELOG
          else
              echo "WARNING (backup): *** Backup process encountered issues. ***" | tee -a $TOMELOG
          fi

          # Run cleanup using manager.sh clean command
          # Clean static/public backups older than BACKUP_RETENTION_DAYS (default: 180 days, system requirement)
          # Use --non-interactive flag to skip confirmation prompts in automated context
          CLEANUP_DAYS=180
          if [ -n "$BACKUP_RETENTION_DAYS" ] && [ "$BACKUP_RETENTION_DAYS" -ge 180 ]; then
              CLEANUP_DAYS=$BACKUP_RETENTION_DAYS
          elif [ -n "$BACKUP_RETENTION_DAYS" ] && [ "$BACKUP_RETENTION_DAYS" -lt 180 ]; then
              echo "WARNING (backup): *** BACKUP_RETENTION_DAYS ($BACKUP_RETENTION_DAYS) is less than minimum of 180 days. Using 180 days. ***" | tee -a $TOMELOG
          fi
          echo "Running automatic cleanup (retention: $CLEANUP_DAYS days for static/public)..." | tee -a $TOMELOG
          if (cd /var/www && $BACKUP_MANAGER clean static,public $CLEANUP_DAYS --non-interactive) 2>&1 | tee -a $TOMELOG; then
              echo "Cleanup completed." | tee -a $TOMELOG
          else
              echo "WARNING (backup): *** Cleanup encountered issues. ***" | tee -a $TOMELOG
          fi
        else
          echo "WARNING (backup): *** Backup system not found at $BACKUP_MANAGER - skipping backups ***" | tee -a $TOMELOG
        fi

          if [ "$S3_COUNT" -eq 0 ]; then
            echo "Error: Published S3 inventory is empty after a successful sync." | tee -a "$TOMELOG"
            SYNC_FAILURE=1
          else
            DIFF_S3_TOME=$(echo "$S3_COUNT - $TOME_COUNT" | bc)
            DIFF_S3_TOME=${DIFF_S3_TOME#-}
            DIFF_S3_TOME_PCT=$(echo "scale=4; $DIFF_S3_TOME / $S3_COUNT" | bc)
            DIFF_S3_TOME_IS_BAD=$(echo "$DIFF_S3_TOME_PCT > $TOME_MAX_CHANGE_ALLOWED" | bc)
            if [ "$DIFF_S3_TOME_IS_BAD" = "1" ]; then
              echo "Warning: Mismatch detected! S3 has $S3_COUNT files, but local directory has $TOME_COUNT files." | tee -a "$TOMELOG"
              cp "$POST_S3_INVENTORY_FILE" /var/www/web/modules/custom/usagov_ssg_postprocessing/files/s3-files.txt
              cp "$TOME_INVENTORY_FILE" /var/www/web/modules/custom/usagov_ssg_postprocessing/files/tome-files.txt
              php -f $SCRIPT_PATH/tome-sync-comparison.php
            else
              echo "Success: The number of files in S3 matches within the allowed threshold." | tee -a "$TOMELOG"
            fi
          fi
      fi
      ssg_metric_end "post_sync_validation" "$POST_SYNC_VALIDATION_START" "end" "s3_count=${S3_COUNT:-0}" "tome_count=${TOME_COUNT:-0}" "diff_bad=${DIFF_S3_TOME_IS_BAD:-unknown}"

  else
      echo "Error: Sync operation failed." | tee -a "$TOMELOG"
      SYNC_FAILURE=1
  fi

  if [ "$SYNC_FAILURE" = "0" ]; then
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation and Sync Completed Successfully"
  else
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation Sync Failed"
  fi
else
  ssg_metric "s3_sync" "skipped" "reason=push_new_content_false"
  echo "Not pushing content to S3."
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation Completed Successfully, but sync to S3 failed or was not attempted"
fi

for inventory_file in "$S3_INVENTORY_FILE" "$TOME_INVENTORY_FILE" "$POST_S3_INVENTORY_FILE"; do
  if [ -n "$inventory_file" ]; then
    rm -f "$inventory_file"
  fi
done

RENDER_CLEANUP_START=$(ssg_now)
ssg_metric "render_cleanup" "start" "render_dir=$RENDER_DIR"
if [ -d "$RENDER_DIR" ]; then
  echo "Removing Render Dir: $RENDER_DIR" | tee -a $TOMELOG
  rm -rf "$RENDER_DIR"
else
  echo "No Render Dir to remove" | tee -a $TOMELOG
fi
ssg_metric_end "render_cleanup" "$RENDER_CLEANUP_START" "end"

echo "Changing directory to /tmp/ since the rend-directory we are currently in just got deleted..."
# Note: That change-dir is done in order to stop the "aws" call below from crashing.
cd /tmp/

LOG_UPLOAD_START=$(ssg_now)
ssg_metric "log_upload" "start" "log_file=$TOMELOGFILE"
if [ -f "$TOMELOG" ]; then
  echo "Saving logs of this run to S3: $TOMELOG -> $BUCKET_NAME/tome-log/$TOMELOGFILE" | tee -a $TOMELOG
  echo "SYNC FINISHED" | tee -a $TOMELOG
  aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome-log/$TOMELOGFILE $S3_EXTRA_PARAMS 2>&1 | tee -a $TOMELOG
  LOG_UPLOAD_SUCCESS=$?

  # Check if the AWS S3 copy command was successful
  if [ $LOG_UPLOAD_SUCCESS -eq 0 ]; then
      echo "s3-cp command successfully ran to copy log to S3." | tee -a "$TOMELOG"

      # Verify the file exists in S3
      aws s3 ls "s3://$BUCKET_NAME/tome-log/$TOMELOGFILE" > /dev/null 2>&1
      LOG_UPLOAD_VERIFY_SUCCESS=$?
      if [ $LOG_UPLOAD_VERIFY_SUCCESS -eq 0 ]; then
          echo "Confirmed: File exists in S3." | tee -a "$TOMELOG"
      else
          echo "Error: File not found in S3 after upload." | tee -a "$TOMELOG"
      fi
  else
      echo "Error: File upload failed." | tee -a "$TOMELOG"
  fi
else
  echo "No logs of this run to S3 available"
  echo "SYNC FINISHED"
  LOG_UPLOAD_SUCCESS=1
  LOG_UPLOAD_VERIFY_SUCCESS=1
fi
ssg_metric_end "log_upload" "$LOG_UPLOAD_START" "end" "exit_code=$LOG_UPLOAD_SUCCESS" "verify_exit_code=${LOG_UPLOAD_VERIFY_SUCCESS:-0}"
if [ "$SYNC_FAILURE" != "0" ]; then
  ssg_metric_end "tome_sync_script" "$RUN_START" "exit" "exit_code=1" "reason=s3_sync_failed"
  exit 1
fi
ssg_metric_end "tome_sync_script" "$RUN_START" "end" "exit_code=0" "push_new_content=$TOME_PUSH_NEW_CONTENT"
