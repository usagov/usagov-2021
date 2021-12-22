#!/bin/ash

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')

#aws s3 sync /var/www/html s3://$BUCKET_NAME/web --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl --delete

mkdir -p /tmp/export;
cp -R /var/www/html/* /tmp/export/
cd /tmp/export

for f in `find .`; do mv -v "$f" $(echo $f | tr '[A-Z]' '[a-z]'); done

aws s3 sync /tmp/export s3://$BUCKET_NAME/web/ --acl public-read --delete
