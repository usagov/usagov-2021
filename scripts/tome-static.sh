#!/bin/ash

URI=${1:-http://beta.usa.gov}

echo "Starting Static Site Generation : "$(date)
#time drush -vvv tome:static --uri=$URI --process-count=1 --path-count=1
time drush tome:static --uri=$URI
echo "Finished Static Site Generation : "$(date)
