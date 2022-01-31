#!/bin/ash

TOMELOG=$1

BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')

# Use a unique dir for each run - just in case more than one are running
UNIQ_DIR=$(date +"%Y_%m_%d_%H_%M_%S")

mkdir -p /tmp/tome/$UNIQ_DIR;
cp -R /var/www/html/* /tmp/tome/$UNIQ_DIR
cd /tmp/tome/$UNIQ_DIR

# lower case  everything
for f in `find /tmp/tome/$UNIQ_DIR/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  [ "$f" != "$ff" ] && mv -v "$f" "$ff";
done

# TODO: fix --endpoint-url and --no-verify-ssl. They are required for minio functionality on local docker but cause trouble against real s3
#aws s3 sync /var/www/html s3://$BUCKET_NAME/web --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl --delete
echo "aws s3 sync /tmp/tome/$UNIQ_DIR s3://$BUCKET_NAME/web/ --acl public-read --delete"

if [ -z "$TOMELOG" ]; then
  echo "aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome/$TOMELOG"
fi;
