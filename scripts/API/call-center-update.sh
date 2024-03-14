#!/bin/bash

# Make a GET request to the Genesys API
queues=$(curl -X GET https://api.mypurecloud.com/api/v2/routing/queues)

#TODO: determine what queue id belongs to usa.gov, assuming we need to find out this way.
queueid=1

waittime=$(curl -X GET /api/v2/routing/queues/$queueId/estimatedwaittime)

# get json status attribute
status=$(echo $waittime | jq -r '.status')

if [[ $status == 200 ]]; then
  echo "$waittime" > waittime.json
else
  test_data='
  { "results": [
    { "intent": "CALL",
      "formula": "BEST",
      "estimatedWaitTimeSeconds": 123 }
  ] }'

  echo "$test_data" > waittime.json
fi

# Upload file to S3 bucket
aws s3 cp waittime.json s3://cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5/cms/public/waittime.json
