#!/bin/sh

TOME_MAX_CHANGE_ALLOWED=0.10
TR_START_TIME=$(date -u +"%s")
SCRIPT_PATH=$(dirname "$0")

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

# ===================================================================
# SYNC GUARD HELPERS
# ===================================================================
#
# The guard below decides whether a destructive `aws s3 sync --delete` may
# replace the live static site. It used to count S3 objects with
# `grep "^\d\{4\}\-"`, which is not a portable digit class: GNU grep and
# BusyBox treat `\d` as a stray escape and match a literal `d`, so no AWS
# listing line matched and the count came back 0. A zero baseline then divided
# by zero in `bc`, which emits nothing, so the "is this change too large" test
# was an empty string, both direction flags were false, and control fell
# through to the branch that publishes. The guard against deleting the site
# could not fire.
#
# Counting is now structural, an unusable baseline refuses instead of
# publishing, and the arithmetic is integer so no input produces an empty
# verdict.

# Count objects under an S3 prefix.
# Parses the listing by field shape - date, time, size, key - rather than
# matching the date with a regex whose digit class is not portable. stderr is
# kept out of the count, and a failed AWS call is reported rather than folded
# into the number as extra lines.
# Args:
#   $1: s3 uri
#   $2: optional grep -v pattern applied to keys
# Returns: 0 and echoes the count, 1 if the listing could not be taken
tome_count_s3_objects() {
    _tcs_uri="$1"
    _tcs_exclude="$2"
    _tcs_out="/tmp/tome-s3-count.$$"

    if ! aws s3 ls --recursive "$_tcs_uri" $S3_EXTRA_PARAMS > "$_tcs_out" 2>/dev/null; then
        rm -f "$_tcs_out"
        return 1
    fi

    if [ -n "$_tcs_exclude" ]; then
        awk 'NF >= 4 && $1 ~ /^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/ && $3 ~ /^[0-9]+$/ { print $4 }' "$_tcs_out" |
            grep -v "$_tcs_exclude" | wc -l | tr -d ' '
    else
        awk 'NF >= 4 && $1 ~ /^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/ && $3 ~ /^[0-9]+$/ { print $4 }' "$_tcs_out" |
            wc -l | tr -d ' '
    fi

    rm -f "$_tcs_out"
    return 0
}

# Count generated files under the render directory.
# Args: $1 directory, $2 optional grep -v pattern
# Returns: 0 and echoes the count, 1 if the directory is not there
tome_count_render_files() {
    _tcr_dir="$1"
    _tcr_exclude="$2"

    [ -d "$_tcr_dir" ] || return 1

    if [ -n "$_tcr_exclude" ]; then
        find "$_tcr_dir" -type f 2>/dev/null | grep -v "$_tcr_exclude" | wc -l | tr -d ' '
    else
        find "$_tcr_dir" -type f 2>/dev/null | wc -l | tr -d ' '
    fi
    return 0
}

# Decide whether a sync may proceed.
# Args:
#   $1: current S3 object count ("" when the listing failed)
#   $2: generated file count ("" when the render directory is missing)
#   $3: maximum allowed change as a fraction, e.g. 0.10
# Echoes one of:
#   publish                  - within tolerance
#   publish-more             - adds more than expected; allowed, as it always
#                              was, because growth does not delete the site
#   refuse-fewer             - removes more than expected
#   refuse-no-baseline       - the current state is unknown or empty
#   refuse-nothing-generated - nothing was generated to publish
# Returns: 0 when publishing is allowed, 1 when it is refused
tome_change_guard() {
    _tcg_s3="$1"
    _tcg_tome="$2"
    _tcg_max="$3"

    # An unknown or empty baseline is the case that used to fall through to
    # publishing. There is nothing to compare against, so it refuses.
    case "$_tcg_s3" in
        ''|*[!0-9]*) echo "refuse-no-baseline"; return 1 ;;
    esac
    [ "$_tcg_s3" -gt 0 ] || { echo "refuse-no-baseline"; return 1; }

    case "$_tcg_tome" in
        ''|*[!0-9]*) echo "refuse-nothing-generated"; return 1 ;;
    esac
    [ "$_tcg_tome" -gt 0 ] || { echo "refuse-nothing-generated"; return 1; }

    # Integer arithmetic in permille, so no input can produce an empty verdict.
    # awk converts the configured fraction and is handed only that constant.
    _tcg_thr=$(awk -v t="${_tcg_max:-0.10}" 'BEGIN { printf "%d", (t * 1000) + 0.5 }' 2>/dev/null)
    case "$_tcg_thr" in
        ''|*[!0-9]*) _tcg_thr=100 ;;
    esac

    if [ "$_tcg_tome" -ge "$_tcg_s3" ]; then
        _tcg_diff=$((_tcg_tome - _tcg_s3))
        _tcg_growing=yes
    else
        _tcg_diff=$((_tcg_s3 - _tcg_tome))
        _tcg_growing=no
    fi

    _tcg_permille=$(( (_tcg_diff * 1000) / _tcg_s3 ))

    if [ "$_tcg_permille" -le "$_tcg_thr" ]; then
        echo "publish"
        return 0
    fi

    if [ "$_tcg_growing" = yes ]; then
        echo "publish-more"
        return 0
    fi

    echo "refuse-fewer"
    return 1
}

# Run a command, append its output to the log, and return *its* status.
# `cmd | tee -a "$log"` returns tee's status, so a failed sync, image sync or
# upload read as success. This script declares /bin/sh, where `pipefail` is not
# available, so the output is captured and appended instead of piped.
# Args: $1 log file, $2.. command and arguments
tome_run_logged() {
    _trl_log="$1"
    shift
    _trl_out="/tmp/tome-run-logged.$$"

    "$@" > "$_trl_out" 2>&1
    _trl_status=$?

    cat "$_trl_out"
    [ -n "$_trl_log" ] && cat "$_trl_out" >> "$_trl_log"
    rm -f "$_trl_out"

    return $_trl_status
}

# make sure there is a static site to sync
STATIC_COUNT=$(ls /var/www/html/ | wc -l)
if [ "$STATIC_COUNT" = "0" ]; then
  MSG="NO SITE TO SYNC"
  echo $MSG
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
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
# These are the site's own media files. If this copy fails the generated site is
# missing content, and publishing it with --delete would remove that content
# from the live site, so the failure is recorded and the guard below refuses.
tome_run_logged "$TOMELOG" aws s3 cp --recursive "s3://$BUCKET_NAME/cms/public/" "$RENDER_DIR/s3/files/" --exclude "php/*" --exclude "*.gz" $S3_EXTRA_PARAMS
PUBLIC_FILES_COPY_STATUS=$?
if [ "$PUBLIC_FILES_COPY_STATUS" -ne 0 ]; then
  echo "ERROR: copying public files into the render directory failed (status $PUBLIC_FILES_COPY_STATUS)" | tee -a $TOMELOG
fi

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

echo "Removing unwanted files ... "
rm -rf $RENDER_DIR/jsonapi/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/node/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/es/node/ 2>&1 | tee -a $TOMELOG
rm -rf $RENDER_DIR/s3/files/benefit-finder/api/draft/life-event/ 2>&1 | tee -a $TOMELOG

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


################################################################################
# USAGOV-2515: Sync static images referenced in HTML from S3FS/public:// to static output
# and rewrite HTML references to use static file paths. This is done via Drush command.
################################################################################
echo "Running Drush static image sync (usagov:ssg-sync-images) ..." | tee -a $TOMELOG
if tome_run_logged "$TOMELOG" drush usagov:ssg-sync-images --html_dir="$RENDER_DIR" --output_files_dir="$RENDER_DIR/files"; then
  echo "Drush static image sync completed successfully." | tee -a $TOMELOG
else
  echo "ERROR: Drush static image sync failed!" | tee -a $TOMELOG
  exit 1
fi

# lower case all filenames in the copied dir before uploading
LCF=0
echo "Lower-casing files:"
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

# get a count of current AWS files, total and by extension
echo "S3 dir storage files : count total" | tee -a $TOMELOG
# Both sides of this comparison have to measure the same set. The live count
# used to exclude the web/s3/files/ subtree while the generated count included
# its own copy of it, so the guard compared 4,379 live objects against 9,155
# generated ones: it always read as "adding more content", and a build that had
# lost half the site still came out larger than the filtered baseline and was
# published. Neither side filters now, which is also what the --delete sync
# actually replaces.
if ! S3_COUNT=$(tome_count_s3_objects "s3://$BUCKET_NAME/web/"); then
  S3_COUNT=""
  echo "ERROR: could not list s3://$BUCKET_NAME/web/ - the current object count is unknown" | tee -a $TOMELOG
fi
echo "     $S3_COUNT" | tee -a $TOMELOG
echo "S3 dir storage files : count by extension" | tee -a $TOMELOG
S3_COUNT_BY_EXT=$(aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>/dev/null | awk 'NF >= 4 && $1 ~ /^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/ { print $4 }' | grep -v "web/s3/files/" | grep -o ".[^.]\+$" | sort | uniq -c)
echo "  $S3_COUNT_BY_EXT" | tee -a $TOMELOG

# get a count of tome generated files, total and by extension
echo "Tome generated files : count total" | tee -a $TOMELOG
if ! TOME_COUNT=$(tome_count_render_files "$RENDER_DIR"); then
  TOME_COUNT=""
  echo "ERROR: render directory $RENDER_DIR is missing - nothing was generated" | tee -a $TOMELOG
fi
echo "      $TOME_COUNT" | tee -a $TOMELOG
echo "Tome generated files : count by extension" | tee -a $TOMELOG
TOME_COUNT_BY_EXT=$(find $RENDER_DIR -type f 2>&1 | uniq | grep -o ".[^.]\+$" | sort | uniq -c)
echo "  $TOME_COUNT_BY_EXT" | tee -a $TOMELOG

# Compare what is live against what was generated. The verdict is decided in
# one place, with no input able to produce an empty result: an unknown or empty
# baseline, or an empty render directory, refuses rather than publishing.
GUARD_VERDICT=$(tome_change_guard "$S3_COUNT" "$TOME_COUNT" "$TOME_MAX_CHANGE_ALLOWED")
if [ "${PUBLIC_FILES_COPY_STATUS:-0}" -ne 0 ]; then
  GUARD_VERDICT="refuse-incomplete-input"
fi

TOME_PUSH_NEW_CONTENT=0
case "$GUARD_VERDICT" in
  publish)
    echo "Tome static build looks fine. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)" | tee -a $TOMELOG
    TOME_PUSH_NEW_CONTENT=1
    ;;
  publish-more)
    echo "Tome static build looks suspicious - adding more content than expected. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)" | tee -a $TOMELOG
    TOME_PUSH_NEW_CONTENT=1
    ;;
  refuse-fewer)
    MSG="Tome static build failure - removing more content than expected. Currently Have ($S3_COUNT) and Tome Generated ($TOME_COUNT)"
    echo $MSG | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
    TOME_PUSH_NEW_CONTENT=0
    ;;
  refuse-no-baseline)
    MSG="Tome sync refused - the current S3 object count could not be established, so a destructive sync cannot be judged safe"
    echo $MSG | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
    TOME_PUSH_NEW_CONTENT=0
    ;;
  refuse-incomplete-input)
    MSG="Tome sync refused - the site's public files could not be copied into the render directory, so the generated site is incomplete"
    echo $MSG | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
    TOME_PUSH_NEW_CONTENT=0
    ;;
  refuse-nothing-generated)
    MSG="Tome sync refused - no generated files were found to publish, and syncing with --delete would empty the live site"
    echo $MSG | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
    TOME_PUSH_NEW_CONTENT=0
    ;;
  *)
    MSG="Tome sync refused - the change guard returned no verdict ($GUARD_VERDICT)"
    echo $MSG | tee -a $TOMELOG
    $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "$MSG"
    TOME_PUSH_NEW_CONTENT=0
    ;;
esac
# --force overrides the size judgement, which is what it is for: an operator who
# has looked at a large legitimate change and wants it published. It does not
# override a missing precondition. "Nothing was generated", "the live count is
# unknown" and "the site's own files could not be copied in" are not opinions
# about how large a change is safe; forcing past them would sync an incomplete
# render directory with --delete and take the live site down with it.
if [[ "$FORCE" =~ ^\-{0,2}f\(orce\)?$ ]]; then
  case "$GUARD_VERDICT" in
    refuse-no-baseline|refuse-nothing-generated|refuse-incomplete-input)
      echo "Not honouring --force: $GUARD_VERDICT is a missing precondition, not a size judgement" | tee -a $TOMELOG
      ;;
    *)
      echo "Honouring --force: publishing despite '$GUARD_VERDICT'" | tee -a $TOMELOG
      TOME_PUSH_NEW_CONTENT=1
      ;;
  esac
fi

ANALYTICS_DIR=/var/www/website-analytics
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

###############################################################################
## The *HOME_HTML* tests looked for problems we have since solved. They remain
## in case such problems recur.
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



##############################################################
# Missing blog pages; Jira USAGOV-2667
#

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



SYNC_STATUS=0
SYNC_VERIFIED=0
if [ "$TOME_PUSH_NEW_CONTENT" == "1" ]; then
  echo "Pushing Content to S3: $RENDER_DIR -> $BUCKET_NAME/web/" | tee -a $TOMELOG
  tome_run_logged "$TOMELOG" aws s3 sync "$RENDER_DIR" "s3://$BUCKET_NAME/web/" --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS
  SYNC_STATUS=$?

  # The status of the sync, not of the tee it used to be piped through
  if [ "$SYNC_STATUS" -eq 0 ]; then
      echo "Sync operation completed successfully." | tee -a "$TOMELOG"

      # get a count of current AWS files, total and by extension
      echo "S3 dir storage files : count total" | tee -a $TOMELOG
      if ! S3_COUNT=$(tome_count_s3_objects "s3://$BUCKET_NAME/web/" "files/styles/"); then
        S3_COUNT=""
      fi
      echo "     $S3_COUNT" | tee -a $TOMELOG

      # get a count of tome generated files, total and by extension
      echo "Tome generated files : count total" | tee -a $TOMELOG
      if ! TOME_COUNT=$(tome_count_render_files "$RENDER_DIR" "files/styles/"); then
        TOME_COUNT=""
      fi
      echo "      $TOME_COUNT" | tee -a $TOMELOG

      # Verify the result before anything treats this run as a success. The
      # counts have to agree within the same tolerance the pre-sync guard uses,
      # and the two home pages have to be present: a sync that reported success
      # while leaving the site short is exactly what this run must not record as
      # a good state, back up, or publish a success status for.
      SYNC_VERIFIED=1
      VERIFY_VERDICT=$(tome_change_guard "$S3_COUNT" "$TOME_COUNT" "$TOME_MAX_CHANGE_ALLOWED")
      case "$VERIFY_VERDICT" in
        publish|publish-more) ;;
        *)
          SYNC_VERIFIED=0
          echo "ERROR: post-sync verification failed ($VERIFY_VERDICT): S3 has $S3_COUNT objects, $TOME_COUNT were generated" | tee -a $TOMELOG
          ;;
      esac

      for SENTINEL in index.html es/index.html; do
        if ! aws s3 ls "s3://$BUCKET_NAME/web/$SENTINEL" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
          SYNC_VERIFIED=0
          echo "ERROR: post-sync verification failed: s3://$BUCKET_NAME/web/$SENTINEL is missing" | tee -a $TOMELOG
        fi
      done

      if [ "$SYNC_VERIFIED" = "1" ]; then
        echo "Post-sync verification passed: $S3_COUNT objects live, both home pages present" | tee -a $TOMELOG
      fi

      # Run automatic backups using manager.sh
      BACKUP_MANAGER="$SCRIPT_PATH/snapshot/manager.sh"

      if [ "$SYNC_VERIFIED" != "1" ]; then
          echo "Skipping automatic backups: the sync did not verify, so this state must not become a recovery point" | tee -a $TOMELOG
      elif [ -f "$BACKUP_MANAGER" ]; then
          echo "Starting automatic backups..." | tee -a $TOMELOG

          # Create static and public backups using manager.sh backup command
          # The manager.sh script will handle all the logic, config loading, and smart detection
          # Run from /var/www to ensure manager.sh can find its dependencies
          # Use --throttle to skip backups if one was created recently (configurable via BACKUP_THROTTLE_HOURS)
          if (cd /var/www && tome_run_logged "$TOMELOG" "$BACKUP_MANAGER" backup static,public --throttle); then
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
          if (cd /var/www && tome_run_logged "$TOMELOG" "$BACKUP_MANAGER" clean static,public $CLEANUP_DAYS --non-interactive); then
              echo "Cleanup completed." | tee -a $TOMELOG
          else
              echo "WARNING (backup): *** Cleanup encountered issues. ***" | tee -a $TOMELOG
          fi
      else
          echo "WARNING (backup): *** Backup system not found at $BACKUP_MANAGER - skipping backups ***" | tee -a $TOMELOG
      fi

      # calculate the diff between s3 and tome
      DIFF_S3_TOME=$(echo "scale=2; $S3_COUNT - $TOME_COUNT" | bc)
      DIFF_S3_TOME=${DIFF_S3_TOME#-}
      DIFF_S3_TOME_PCT=$(echo "scale=2; $DIFF_S3_TOME / $S3_COUNT" | bc)
      DIFF_S3_TOME_PCT=${DIFF_S3_TOME_PCT#-}
      DIFF_S3_TOME_IS_BAD=$(echo "scale=2; $DIFF_S3_TOME_PCT > $TOME_MAX_CHANGE_ALLOWED" | bc)
      if [ "$DIFF_S3_TOME_IS_BAD" == "1" ]; then
        echo "Warning: Mismatch detected! S3 has $S3_COUNT files, but local directory has $TOME_COUNT files." | tee -a "$TOMELOG"
        aws s3 ls --recursive s3://$BUCKET_NAME/web/ $S3_EXTRA_PARAMS 2>/dev/null | awk 'NF >= 4 && $1 ~ /^[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]$/' | grep -v "web/s3/files/" > /var/www/web/modules/custom/usagov_ssg_postprocessing/files/s3-files.txt
        find $RENDER_DIR -type f 2>&1 | uniq > /var/www/web/modules/custom/usagov_ssg_postprocessing/files/tome-files.txt
        php -f $SCRIPT_PATH/tome-sync-comparison.php
      else
        echo "Success: The number of files in S3 matches (close enough) to the local count." | tee -a "$TOMELOG"
      fi

  else
      echo "Error: Sync operation failed." | tee -a "$TOMELOG"
  fi

  # The success status used to be published here unconditionally - immediately
  # after the branch that prints "Sync operation failed." Report what happened.
  if [ "$SYNC_STATUS" -ne 0 ]; then
      $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Sync FAILED: aws s3 sync returned $SYNC_STATUS"
  elif [ "$SYNC_VERIFIED" != "1" ]; then
      $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Sync completed but FAILED verification: S3 has $S3_COUNT objects, $TOME_COUNT were generated"
  else
      $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation and Sync Completed Successfully"
  fi
else
  echo "Not pushing content to S3."
  $SCRIPT_PATH/tome-status-indicator-update.sh "$TR_START_TIME" "Static Site Generation Completed Successfully, but sync to S3 failed or was not attempted"
fi

if [ -d "$RENDER_DIR" ]; then
  echo "Removing Render Dir: $RENDER_DIR" | tee -a $TOMELOG
  rm -rf "$RENDER_DIR"
else
  echo "No Render Dir to remove" | tee -a $TOMELOG
fi

echo "Changing directory to /tmp/ since the rend-directory we are currently in just got deleted..."
# Note: That change-dir is done in order to stop the "aws" call below from crashing.
cd /tmp/

if [ -f "$TOMELOG" ]; then
  echo "Saving logs of this run to S3: $TOMELOG -> $BUCKET_NAME/tome-log/$TOMELOGFILE" | tee -a $TOMELOG
  echo "SYNC FINISHED" | tee -a $TOMELOG
  tome_run_logged "$TOMELOG" aws s3 cp "$TOMELOG" "s3://$BUCKET_NAME/tome-log/$TOMELOGFILE" $S3_EXTRA_PARAMS
  LOG_UPLOAD_STATUS=$?

  # Check if the AWS S3 copy command was successful
  if [ "$LOG_UPLOAD_STATUS" -eq 0 ]; then
      echo "s3-cp command successfully ran to copy log to S3." | tee -a "$TOMELOG"

      # Verify the file exists in S3
      aws s3 ls "s3://$BUCKET_NAME/tome-log/$TOMELOGFILE" > /dev/null 2>&1
      if [ $? -eq 0 ]; then
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
fi
