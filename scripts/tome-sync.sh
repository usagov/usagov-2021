#!/bin/ash

TOMELOG=$1

BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')

mkdir -p /tmp/tome;
cp -R /var/www/html/* /tmp/tome/
cd /tmp/tome

for f in `find /tmp/tome/*`; do
  ff=$(echo $f | tr '[A-Z]' '[a-z]');
  [ "$f" != "$ff" ] && mv -v "$f" "$ff";
done

#aws s3 sync /var/www/html s3://$BUCKET_NAME/web --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl --delete
echo "aws s3 sync /tmp/tome s3://$BUCKET_NAME/web/ --acl public-read --delete"

if [ -z "$TOMELOG" ]; then
  echo "aws s3 cp $TOMELOG s3://$BUCKET_NAME/tome/$TOMELOG"
fi;
