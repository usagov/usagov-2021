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
  sed -e '/drush tome:static/d' ./betacmd > betacmd.t && mv betacmd.t betacmd &&\
  echo "*/30 * * * * . ${www}/scripts/beta/betaupdate.sh" >> betacmd &&\
  crontab betacmd &&\
  rm betacmd
else
  drushc='/var/www/vendor/bin/drush'
  echo 'copy css and js to html'
  cp -rf ${www}/s3/local/cms/public/css ${html_files}/css &&\
  cp -rf ${www}/s3/local/cms/public/js ${html_files}/js
  echo 'Run tome static'
  tomestatic="drush cr --root=${www} && drush tome:static -y --uri=$URI --process-count=10 --path-count=10 --root=${www}"
  drush cr --root=${www} && drush tome:static -y --uri=$URI --process-count=10 --path-count=10 --root=${www}
  crontab -l > betacmd &&\
  sed -e '/drush tome:static/d' ./betacmd > betacmd.t && mv betacmd.t betacmd &&\
  echo "*/30 * * * * ${tomestatic}" >> betacmd &&\
  crontab betacmd &&\
  rm betacmd
fi
