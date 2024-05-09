#!/bin/bash

environment="use2.us-gov-pure.cloud"
clientId="ad16bccf-2afc-43f3-b6bb-37633c026c51"
clientSecret="5zLMAwQHDHH36xq8cBR54NY4ezS2iTmT11aooK3HI0g"
enQueueId="99b60af0-1047-4968-824e-7197fe582cea"
spQueueId="e7eef7e6-77f9-4b8a-bb8b-fb324f5d96be"
s3Bucket="cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5"
tokenFile="token.json"
waitTimeFile="waittime.json"

# yes I know there is a ton of repeated code here, I couldn't get functions to work properly
if [ -e $tokenFile ]; then
  echo "Token file exists."
  tokenJSON=$(< $tokenFile)

  if [ -z "$tokenJSON" ]; then
    echo "Token file is empty!"
    tokenResponse=$(curl -X POST https://login.$environment/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$clientId&client_secret=$clientSecret")

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
      tokenResponse=$(curl -X POST https://login.$environment/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$clientId&client_secret=$clientSecret")

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
  tokenResponse=$(curl -X POST https://login.$environment/oauth/token -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials&client_id=$clientId&client_secret=$clientSecret")

  currentTime=$(date +%s)
  expiresIn=$(echo "$tokenResponse" | jq -r '.expires_in')
  expireTime=$((currentTime + expiresIn))
  tokenResponse=$(jq -r --arg expireTime "$expireTime" '.expiresAt=$expireTime' <<< "$tokenResponse")

  echo "$tokenResponse" > $tokenFile
  token=$(echo "$tokenJSON" | jq -r '.access_token')
fi

echo "Getting en wait time."
enWaitTime=$(curl -X GET https://api.$environment/api/v2/routing/queues/$enQueueId/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

echo "Getting sp wait time."
spWaitTime=$(curl -X GET https://api.$environment/api/v2/routing/queues/$spQueueId/estimatedwaittime -H "Authorization:Bearer $token" | jq -r '.results[].estimatedWaitTimeSeconds');

combinedWaitTimes='{ "enEstimatedWaitTimeSeconds": '$enWaitTime',
  "spEstimatedWaitTimeSeconds": '$spWaitTime'}'

echo "Setting wait time file."
echo "$combinedWaitTimes" > $waitTimeFile

# # Upload file to S3 bucket
echo "uploading waittime.json to S3."
aws s3 cp waittime.json s3://$s3Bucket/cms/public/waittime.json
