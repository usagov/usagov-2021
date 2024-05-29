#!/bin/sh

# Uses jq to:
# - extract S3 bucket params from a bound service in VCAP_SERVICES and set env vars
export BUCKET_NAME="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.tags[] | contains("usagov-crons-s3")) | .credentials.bucket')"
export AWS_DEFAULT_REGION="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.tags[] | contains("usagov-crons-s3")) | .credentials.region')"
export AWS_ACCESS_KEY_ID="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.tags[] | contains("usagov-crons-s3")) | .credentials.access_key_id')"
export AWS_SECRET_ACCESS_KEY="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.tags[] | contains("usagov-crons-s3")) | .credentials.secret_access_key')"

# - extract S3 bucket params from a bound service in VCAP_SERVICES and set env vars



# Install crontab
# crontab ./crontab
