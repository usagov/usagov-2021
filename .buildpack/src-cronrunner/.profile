#!/bin/sh

export PATH="$HOME/app/bin:$PATH"

# Uses jq to:
# - extract S3 bucket params from a bound service in VCAP_SERVICES and set env vars
export BUCKET_NAME="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.name == "public-api-storage") | .credentials.bucket')"
export AWS_DEFAULT_REGION="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.name == "public-api-storage") | .credentials.region')"
export AWS_ACCESS_KEY_ID="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.name == "public-api-storage") | .credentials.access_key_id')"
export AWS_SECRET_ACCESS_KEY="$(echo "$VCAP_SERVICES" | jq --raw-output '."s3" | .[] | select(.name == "public-api-storage") | .credentials.secret_access_key')"

# - extract credentials for contact center api from a bound service as well
export CALL_CENTER_CLIENT_ID="$(echo "$VCAP_SERVICES" | jq --raw-output '."user-provided" | .[] | select(.name == "cron-runner-creds") | .credentials.CALL_CENTER_CLIENT_ID')"
export CALL_CENTER_CLIENT_SECRET="$(echo "$VCAP_SERVICES" | jq --raw-output '."user-provided" | .[] | select(.name == "cron-runner-creds") | .credentials.CALL_CENTER_CLIENT_SECRET')"
export CALL_CENTER_ENVIRONMENT="$(echo "$VCAP_SERVICES" | jq --raw-output '."user-provided" | .[] | select(.name == "cron-runner-creds") | .credentials.CALL_CENTER_ENVIRONMENT')"
export CALL_CENTER_EN_QUEUE_ID="$(echo "$VCAP_SERVICES" | jq --raw-output '."user-provided" | .[] | select(.name == "cron-runner-creds") | .credentials.CALL_CENTER_EN_QUEUE_ID')"
export CALL_CENTER_SP_QUEUE_ID="$(echo "$VCAP_SERVICES" | jq --raw-output '."user-provided" | .[] | select(.name == "cron-runner-creds") | .credentials.CALL_CENTER_SP_QUEUE_ID')"
