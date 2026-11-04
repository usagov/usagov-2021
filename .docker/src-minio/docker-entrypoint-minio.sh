#!/bin/bash

/setup-bucket.sh &

exec minio "$@"
