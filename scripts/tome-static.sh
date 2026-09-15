#!/bin/sh
set -x

SCRIPT_NAME=$(basename "$0")

URI=${1:-https://www.usa.gov}

TOME_PROCESS_COUNT=${TOME_PROCESS_COUNT:-4}
TOME_PATH_COUNT=${TOME_PATH_COUNT:-10}

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
  case $- in
    *x*)
      ssg_xtrace_enabled=1
      set +x
      ;;
    *)
      ssg_xtrace_enabled=0
      ;;
  esac

  ssg_metric_line "$@"

  if [ "$ssg_xtrace_enabled" = "1" ]; then
    set -x
  fi
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
ssg_metric "tome_static_script" "start" "uri=$URI" "process_count=$TOME_PROCESS_COUNT" "path_count=$TOME_PATH_COUNT"

INT_REGEX='^[0-9]+$'

if [[ $TOME_PROCESS_COUNT =~ $INT_REGEX ]]; then
  if [ $TOME_PROCESS_COUNT -eq 0 -o $TOME_PROCESS_COUNT -gt 10 ]; then
    echo "TOME_PROCESS_COUNT '$TOME_PROCESS_COUNT' is out of range.  Adjusting to 4"
    TOME_PROCESS_COUNT=4
  fi
else
  if [ x$TOME_PROCESS_COUNT = x ]; then
    TOME_PROCESS_COUNT=4
  else
    echo "TOME_PROCESS_COUNT '$TOME_PROCESS_COUNT' is not a valid, non-negative integer.  Adjusting to 4"
    TOME_PROCESS_COUNT=4
  fi
fi

if [[ $TOME_PATH_COUNT =~ $INT_REGEX ]]; then
  if [ $TOME_PATH_COUNT -eq 0 -o $TOME_PATH_COUNT -gt 50 ]; then
    echo "TOME_PATH_COUNT '$TOME_PATH_COUNT' is out of range.  Adjusting to 10"
    TOME_PATH_COUNT=10
  fi
else
  if [ x$TOME_PATH_COUNT = x ]; then
    TOME_PATH_COUNT=10
  else
    echo "TOME_PATH_COUNT '$TOME_PATH_COUNT' is not a valid, non-negative integer.  Adjusting to 10"
    TOME_PATH_COUNT=10
  fi
fi

ssg_metric "tome_static_config" "end" "process_count=$TOME_PROCESS_COUNT" "path_count=$TOME_PATH_COUNT"

# Regenerate the sitemap.
echo "Regenerating sitemap..."
SITEMAP_REGENERATION_START=$(ssg_now)
ssg_metric "sitemap_regeneration" "start"
drush ssr
SSR_SUCCESS=$?
ssg_metric_end "sitemap_regeneration" "$SITEMAP_REGENERATION_START" "end" "exit_code=$SSR_SUCCESS"

SIMPLE_SITEMAP_GENERATION_START=$(ssg_now)
ssg_metric "simple_sitemap_generation" "start" "uri=$URI"
drush --uri=$URI ssg
SSG_SUCCESS=$?
ssg_metric_end "simple_sitemap_generation" "$SIMPLE_SITEMAP_GENERATION_START" "end" "exit_code=$SSG_SUCCESS"

echo "Starting Static Site Generation : "$(date)
mkdir -p /var/www/html
# time drush -vvv tome:static --uri=$URI --process-count=1 --path-count=1
# time drush tome:static -y --uri=$URI --process-count=5 --path-count=1
TOME_STATIC_START=$(ssg_now)
ssg_metric "tome_static" "start" "uri=$URI" "process_count=$TOME_PROCESS_COUNT" "path_count=$TOME_PATH_COUNT"
time drush tome:static -y --uri=$URI --process-count=$TOME_PROCESS_COUNT --path-count=$TOME_PATH_COUNT
TOME_SUCCESS=$?
ssg_metric_end "tome_static" "$TOME_STATIC_START" "end" "exit_code=$TOME_SUCCESS"

echo "Finished Static Site Generation : "$(date)

if [ "$TOME_SUCCESS" -eq 0 ]; then
  # path is relative to drupal's web dir
  PUBLISHED_CSV_START=$(ssg_now)
  ssg_metric "published_pages_csv" "start" "uri=$URI"
  time drush usapubcsv --uri=$URI modules/custom/usagov_ssg_postprocessing/files/published-pages.csv
  PUBLISHED_CSV_SUCCESS=$?
  ssg_metric_end "published_pages_csv" "$PUBLISHED_CSV_START" "end" "exit_code=$PUBLISHED_CSV_SUCCESS"
  echo "Exported published-pages.csv"
else
  ssg_metric "published_pages_csv" "skipped" "reason=tome_static_failed" "tome_exit_code=$TOME_SUCCESS"
fi

ssg_metric_end "tome_static_script" "$RUN_START" "end" "exit_code=$TOME_SUCCESS"
exit $TOME_SUCCESS
