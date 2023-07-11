#!/bin/bash
echo starting container to create reports
cat ${CF_SYSTEM_CERT_PATH}/* > /etc/combined-certs.pem
export NODE_EXTRA_CA_CERTS=/etc/combined-certs.pem

export NODE_OPTIONS=''

export http_proxy=$PROXYROUTE
export https_proxy=$PROXYROUTE
export HTTP_PROXY=$PROXYROUTE
export HTTPS_PROXY=$PROXYROUTE

npm config set proxy $PROXYROUTE
npm config set https-proxy $PROXYROUTE

AWS_REGION=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_REGION"]' <<< "$VCAP_SERVICES")

AWS_ACCESS_KEY_ID=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_ACCESS_KEY_ID"]' <<< "$VCAP_SERVICES")

AWS_SECRET_ACCESS_KEY=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_SECRET_ACCESS_KEY"]' <<< "$VCAP_SERVICES")

AWS_BUCKET=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_BUCKET"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_BOTH=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_BUCKET_PATH_BOTH"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_EN=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_BUCKET_PATH_EN"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_ES=$(jq -r '.["user-provided"]| .[].credentials | .["AWS_BUCKET_PATH_ES"]' <<< "$VCAP_SERVICES")

ANALYTICS_REPORT_IDS_BOTH=$(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_REPORT_IDS_BOTH"]' <<< "$VCAP_SERVICES")
ANALYTICS_REPORT_IDS_EN=$(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_REPORT_IDS_EN"]' <<< "$VCAP_SERVICES")
ANALYTICS_REPORT_IDS_ES=$(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_REPORT_IDS_ES"]' <<< "$VCAP_SERVICES")

ANALYTICS_REPORT_EMAIL=$(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_REPORT_EMAIL"]' <<< "$VCAP_SERVICES")

ANALYTICS_KEY_PATH=$(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_KEY_PATH"]' <<< "$VCAP_SERVICES")

export ANALYTICS_KEY_PATH=$ANALYTICS_KEY_PATH
export AWS_REGION=$AWS_REGION
export AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID
export AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY
export AWS_BUCKET=$AWS_BUCKET
export ANALYTICS_REPORT_EMAIL=$ANALYTICS_REPORT_EMAIL

echo $(jq -r '.["user-provided"]| .[].credentials | .["ANALYTICS_KEY_BASE64"]' <<< "$VCAP_SERVICES") | base64 --decode > $ANALYTICS_KEY_PATH
chmod 600 $ANALYTICS_KEY_PATH

cd analytics-reporter

while true;
do
  # for both sites
  export AWS_BUCKET_PATH=$AWS_BUCKET_PATH_BOTH
  export ANALYTICS_REPORT_IDS=$ANALYTICS_REPORT_IDS_BOTH
  ./bin/analytics --publish --verbose;
  ./bin/analytics --csv --publish --verbose;
  # for spanish site only
  export AWS_BUCKET_PATH=$AWS_BUCKET_PATH_ES
  export ANALYTICS_REPORT_IDS=$ANALYTICS_REPORT_IDS_ES
  ./bin/analytics --publish --verbose;
  ./bin/analytics --csv --publish --verbose;
  # for english site only
  export AWS_BUCKET_PATH=$AWS_BUCKET_PATH_EN
  export ANALYTICS_REPORT_IDS=$ANALYTICS_REPORT_IDS_EN
  ./bin/analytics --publish --verbose;
  ./bin/analytics --csv --publish --verbose;
  # ping every 900- 15 min;
  sleep 900;
done;


echo ending container testscript.sh
