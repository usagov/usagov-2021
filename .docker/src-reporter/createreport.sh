#!/bin/bash

# Copying certs into place
cp $CF_SYSTEM_CERT_PATH/*  /usr/local/share/ca-certificates/
/usr/sbin/update-ca-certificates 2>&1 > /dev/null || echo ""

# We do this here so that we have $PROXYROUTE, which is not available during build
echo "Updating Caddy config"
envsubst < ./local_proxy/Caddyfile.tmpl > ./local_proxy/Caddyfile

echo "Starting Caddy"
exec ./local_proxy/caddy run --config ./local_proxy/Caddyfile &

echo "starting container to create reports"
cat ${CF_SYSTEM_CERT_PATH}/* > /etc/combined-certs.pem
export NODE_EXTRA_CA_CERTS=/etc/combined-certs.pem

export NODE_OPTIONS=''

npm config set proxy $PROXYROUTE
npm config set https-proxy $PROXYROUTE

# NB we are setting these proxy variables to a local proxy, for the
# benefit of the GA4 analytics code, which uses grpc-js and doesn't support
# an https proxy.
export https_proxy=http://localhost:8080
export HTTPS_PROXY=http://localhost:8080


AWS_REGION=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_REGION"]' <<< "$VCAP_SERVICES")

AWS_ACCESS_KEY_ID=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_ACCESS_KEY_ID"]' <<< "$VCAP_SERVICES")

AWS_SECRET_ACCESS_KEY=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_SECRET_ACCESS_KEY"]' <<< "$VCAP_SERVICES")

AWS_BUCKET=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_BUCKET"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_BOTH=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_BUCKET_PATH_BOTH"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_EN=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_BUCKET_PATH_EN"]' <<< "$VCAP_SERVICES")
AWS_BUCKET_PATH_ES=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["AWS_BUCKET_PATH_ES"]' <<< "$VCAP_SERVICES")

ANALYTICS_REPORT_IDS_BOTH=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["ANALYTICS_REPORT_IDS_BOTH"]' <<< "$VCAP_SERVICES")
ANALYTICS_REPORT_IDS_EN=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["ANALYTICS_REPORT_IDS_EN"]' <<< "$VCAP_SERVICES")
ANALYTICS_REPORT_IDS_ES=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["ANALYTICS_REPORT_IDS_ES"]' <<< "$VCAP_SERVICES")

ANALYTICS_REPORT_EMAIL=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["ANALYTICS_REPORT_EMAIL"]' <<< "$VCAP_SERVICES")

ANALYTICS_KEY_PATH=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.["ANALYTICS_KEY_PATH"]' <<< "$VCAP_SERVICES")

GOOGLE_APPLICATION_CREDENTIALS=$ANALYTICS_KEY_PATH
export GOOGLE_APPLICATION_CREDENTIALS=$GOOGLE_APPLICATION_CREDENTIALS
ANALYTICS_KEY_PATH=analytics-reporter/$ANALYTICS_KEY_PATH
export ANALYTICS_KEY_PATH=$ANALYTICS_KEY_PATH
export AWS_REGION=$AWS_REGION
export AWS_ACCESS_KEY_ID=$AWS_ACCESS_KEY_ID
export AWS_SECRET_ACCESS_KEY=$AWS_SECRET_ACCESS_KEY
export AWS_BUCKET=$AWS_BUCKET
export ANALYTICS_REPORT_EMAIL=$ANALYTICS_REPORT_EMAIL

# GOOGLE_APPLICATION_CREDENTIALS=$(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.ANALYTICS_KEY_BASE64' <<< "$VCAP_SERVICES" | base64 -d)
# export GOOGLE_APPLICATION_CREDENTIALS=$GOOGLE_APPLICATION_CREDENTIALS

echo $(jq -r '.["user-provided"] | .[] | select(.name == "AnalyticsReporterServices")| .credentials.ANALYTICS_KEY_BASE64' <<< "$VCAP_SERVICES") | base64 -d > $ANALYTICS_KEY_PATH
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
