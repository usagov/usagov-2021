#!/bin/bash

SPACE=$1

aws s3 rm --recursive s3://$S3_BUCKET/cfevents/$SPACE/

cf run-task cfevents --name cfevents-instance --command "/opt/cfevents/capture-latest-events $SPACE"

sleep 15

aws s3 cp --recursive s3://$S3_BUCKET/cfevents/$SPACE/ .
