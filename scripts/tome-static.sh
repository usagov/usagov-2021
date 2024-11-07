#!/bin/sh
set -x

URI=${1:-https://www.usa.gov}

TOME_PROCESS_COUNT=${TOME_PROCESS_COUNT:-4}

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

# Regenerate the sitemap.
echo "Regenerating sitemap..."
drush ssr
drush --uri=$URI ssg

echo "Starting Static Site Generation : "$(date)
mkdir -p /var/www/html
# time drush -vvv tome:static --uri=$URI --process-count=1 --path-count=1
# time drush tome:static -y --uri=$URI --process-count=5 --path-count=1
time drush tome:static -y --uri=$URI --process-count=$TOME_PROCESS_COUNT --path-count=10
TOME_SUCCESS=$?
echo "Finished Static Site Generation : "$(date)
exit $TOME_SUCCESS
