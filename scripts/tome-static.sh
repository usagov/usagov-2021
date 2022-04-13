#!/bin/sh

URI=${1:-https://beta.usa.gov}

echo "Starting Static Site Generation : "$(date)
#time drush -vvv tome:static --uri=$URI --process-count=1 --path-count=1
time drush tome:static -y --uri=$URI --process-count=1 --path-count=1
echo "Finished Static Site Generation : "$(date)
