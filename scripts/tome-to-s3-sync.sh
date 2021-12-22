#!/bin/env ash

export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')

#move from /var/www/html into /tmp/rand
#lowercase /tmp/rand
#delete /tmp/rand

#lowercase=$(echo $file | tr '[A-Z]' '[a-z]'])
#/bin/mv $file $lowercase

# find | xargs mv og_filename $(echo $og_filename | tr '[:upper:]' '[:lower:]')

#find . -exec mv filename='{}' $(echo $filename | tr '[:upper:]' '[:lower:]') ';'



#aws s3 sync /var/www/html s3://$BUCKET_NAME/web --acl public-read --endpoint-url https://$AWS_ENDPOINT --no-verify-ssl --delete

aws s3 ls s3://$BUCKET_NAME
