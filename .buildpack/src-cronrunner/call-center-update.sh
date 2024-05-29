#!/bin/bash

# Script to get the Estimated Wait Time for the call center queues.
#
# API docs:
# https://developer.genesys.cloud/devapps/api-explorer#get-api-v2-routing-queues--queueId--mediatypes--mediaType--estimatedwaittime
# https://developer.genesys.cloud/routing/routing/estimatedwaittime-v2

# Init directory for data for this process, and touch files we expect to have:
DATA_DIR="/tmp/call-center"
mkdir -p $DATA_DIR

# tokenFile is for the OAuth token for the Call Center API
tokenFile="${DATA_DIR}/token.json"
touch $tokenFile

# waitTimeFile will be our output data
waitTimeFile="${DATA_DIR}/waittime.json"
touch $waitTimeFile

# gather s3 credentials from VCAP_SERVICES
# TODO:
# S3_BUCKET, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY

# TODO: if we do this as a cf task, there will never be a tokenJSON when we start.
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

# TODO: we should be using the new API with mediatype; see https://developer.genesys.cloud/routing/routing/estimatedwaittime-v2
echo "Getting en wait time."
enWaitTime=$(curl -X GET https://api.$CALL_CENTER_ENVIRONMENT/api/v2/routing/queues/$CALL_CENTER_EN_QUEUE_ID/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

echo "Getting sp wait time."
spWaitTime=$(curl -X GET https://api.$CALL_CENTER_ENVIRONMENT/api/v2/routing/queues/$CALL_CENTER_SP_QUEUE_ID/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

# TODO: make sure we are checking this timestamp when we display wait time -- if the waittime.json
# data is more than X seconds old, treat it as "no data."
combinedWaitTimes='{ "enEstimatedWaitTimeSeconds": '$enWaitTime',
  "spEstimatedWaitTimeSeconds": '$spWaitTime',
  "query_timestamp": "$currentTime"}'

echo "Setting wait time file."
echo "$combinedWaitTimes" > $waitTimeFile

# Upload file to S3 bucket
echo "uploading waittime.json to S3."
aws s3 cp $waitTimeFile s3://$S3_BUCKET/cms/public/waittime.json
