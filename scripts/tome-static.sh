#!/bin/sh
set -x

URI=${1:-https://www.usa.gov}

echo "Starting Static Site Generation : "$(date)
mkdir -p /var/www/html
# time drush -vvv tome:static --uri=$URI --process-count=1 --path-count=1
# time drush tome:static -y --uri=$URI --process-count=5 --path-count=1
time drush tome:static -y --uri=$URI --process-count=4 --path-count=1
TOME_SUCCESS=$?
echo "Finished Static Site Generation : "$(date)
exit $TOME_SUCCESS
