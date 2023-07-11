#!/bin/bash

# Run this script with a . in order to set environment variables in your shell
# For example:
# . ./getcreds.sh
#
# From: https://cloud.gov/docs/services/s3/

SERVICE_INSTANCE_NAME=s3-storage
KEY_NAME=s3-storage-key

cf create-service-key "${SERVICE_INSTANCE_NAME}" "${KEY_NAME}"
S3_CREDENTIALS=$(cf service-key "${SERVICE_INSTANCE_NAME}" "${KEY_NAME}" | tail -n +2)

export AWS_ACCESS_KEY_ID=$(echo "${S3_CREDENTIALS}" | jq -r '.credentials.access_key_id')
export AWS_SECRET_ACCESS_KEY=$(echo "${S3_CREDENTIALS}" | jq -r '.credentials.secret_access_key')
export BUCKET_NAME=$(echo "${S3_CREDENTIALS}" | jq -r '.credentials.bucket')
export AWS_DEFAULT_REGION=$(echo "${S3_CREDENTIALS}" | jq -r '.credentials.region')
