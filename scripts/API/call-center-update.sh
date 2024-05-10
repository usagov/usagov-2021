#!/bin/bash

tokenFile="token.json"
waitTimeFile="waittime.json"

SERVICE_NAME=${1:-storage}

if ! cf service "$SERVICE_NAME" >/dev/null 2>&1; then
  echo "S3 service $SERVICE_NAME does not exist in space $SPACE"
  exit 1
fi

SERVICE_KEY="storagekey-$SPACE-$SERVICE_NAME"

if ! cf service "$SERVICE_NAME" >/dev/null 2>&1; then
  echo "S3 service $SERVICE_NAME does not exist in space $SPACE"
  exit 1
fi

# gather s3 credentials from storage key
cf create-service-key $SERVICE_NAME $SERVICE_KEY
export S3INFO=$(cf service-key $SERVICE_NAME $SERVICE_KEY)
export S3_BUCKET=$(echo "$S3INFO" | grep '"bucket":' | sed 's/.*"bucket": "\([^"]*\)".*/\1/')
# export S3_REGION=$(echo "$S3INFO" | grep '"region":' | sed 's/.*"region": "\([^"]*\)".*/\1/')
# export AWS_ACCESS_KEY_ID=$(echo "$S3INFO" | grep '"access_key_id":' | sed 's/.*"access_key_id": "\([^"]*\)".*/\1/')
# export AWS_SECRET_ACCESS_KEY=$(echo "$S3INFO" | grep '"secret_access_key":' | sed 's/.*"secret_access_key": "\([^"]*\)".*/\1/')

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi

# yes I know there is a ton of repeated code here, I couldn't get functions to work properly
if [ -e $tokenFile ]; then
  echo "Token file exists."
  tokenJSON=$(< $tokenFile)

  if [ -z "$tokenJSON" ]; then
    echo "Token file is empty!"
    tokenResponse=$(curl -X POST https://login.$CALL_CENTER_ENVIRONMENT/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$CALL_CENTER_CLIENT_ID&client_secret=$CALL_CENTER_CLIENT_SECRET")

    currentTime=$(date +%s)
    expiresIn=$(echo "$tokenResponse" | jq -r '.expires_in')
    expireTime=$((currentTime + expiresIn))
    tokenResponse=$(jq -r --arg expireTime "$expireTime" '.expiresAt=$expireTime' <<< "$tokenResponse")

    echo "$tokenResponse" > $tokenFile
    token=$(echo "$tokenJSON" | jq -r '.access_token')
  else
    currentTime=$(date +%s)
    expireTime=$(jq -r '.expiresAt' <<< "$tokenJSON")

    if [ "$currentTime" -gt "$expireTime" ]; then
      echo "Token expired!"
      tokenResponse=$(curl -X POST https://login.$CALL_CENTER_ENVIRONMENT/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$CALL_CENTER_CLIENT_ID&client_secret=$CALL_CENTER_CLIENT_SECRET")

      currentTime=$(date +%s)
      expiresIn=$(echo "$tokenResponse" | jq -r '.expires_in')
      expireTime=$((currentTime + expiresIn))
      tokenResponse=$(jq -r --arg expireTime "$expireTime" '.expiresAt=$expireTime' <<< "$tokenResponse")

      echo "$tokenResponse" > $tokenFile
      token=$(echo "$tokenJSON" | jq -r '.access_token')
    else
      echo "Token not expired."
      token=$(echo "$tokenJSON" | jq -r '.access_token')
    fi
  fi
else
  echo "Token file does not exist!"
  tokenResponse=$(curl -X POST https://login.$CALL_CENTER_ENVIRONMENT/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$CALL_CENTER_CLIENT_ID&client_secret=$CALL_CENTER_CLIENT_SECRET")

  currentTime=$(date +%s)
  expiresIn=$(echo "$tokenResponse" | jq -r '.expires_in')
  expireTime=$((currentTime + expiresIn))
  tokenResponse=$(jq -r --arg expireTime "$expireTime" '.expiresAt=$expireTime' <<< "$tokenResponse")

  echo "$tokenResponse" > $tokenFile
  token=$(echo "$tokenJSON" | jq -r '.access_token')
fi

echo "Getting en wait time."
enWaitTime=$(curl -X GET https://api.$CALL_CENTER_ENVIRONMENT/api/v2/routing/queues/$CALL_CENTER_EN_QUEUE_ID/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

echo "Getting sp wait time."
spWaitTime=$(curl -X GET https://api.$CALL_CENTER_ENVIRONMENT/api/v2/routing/queues/$CALL_CENTER_SP_QUEUE_ID/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

combinedWaitTimes='{ "enEstimatedWaitTimeSeconds": '$enWaitTime',
  "spEstimatedWaitTimeSeconds": '$spWaitTime'}'

echo "Setting wait time file."
echo "$combinedWaitTimes" > $waitTimeFile

SPACE=$(cf target | grep space: | awk '{print $2}');
if [ -z "$SPACE" ]; then
  echo "You must choose a space before procesing ./bin/cloudgov/space (personal|dev|stage|prod|shared-egress)"
  exit 1
fi;

# # Upload file to S3 bucket
echo "uploading waittime.json to S3."
aws s3 cp waittime.json s3://$S3_BUCKET/cms/public/waittime.json
