#!/bin/bash

/setup-bucket.sh &

/usr/bin/docker-entrypoint.sh "$@"
