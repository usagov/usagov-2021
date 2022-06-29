#!/bin/sh
www=/var/www &&\
html=${www}/html &&\
html_files=${html}/s3/files/ &&\
theme=${www}/web/themes/custom/usagov &&\
S3_BUCKET=`echo $VCAP_SERVICES | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket'`

## Run once
mkdir -p ${html_files}/js ${html_files}/css $html/themes/custom/usagov/fonts $html/themes/custom/usagov/images &&\
cp -rf $theme/fonts/* $html/themes/custom/usagov/fonts/ && cp -rf $theme/images/* $html/themes/custom/usagov/images &&\
  drush cr --root=${www} && drush cron --root=${www} &&\
  drush -y s3fs-copy-local --root=${www} 

if [ `echo "$VCAP_APPLICATION" | jq -r '.space_name'` != "local" ]; then
  ${www}/scripts/beta/betaupdate.sh

  ## every 15 seconds commands
  #write out current crontab
  crontab -l > betacmd &&\
  echo "*/15 * * * * . ${www}/scripts/beta/betaupdate.sh" >> betacmd &&\
  crontab betacmd &&\
  rm betacmd
fi
