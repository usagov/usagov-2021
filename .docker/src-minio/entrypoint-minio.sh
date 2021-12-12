#!/bin/bash

mc config host rm local
mc config host add local http://127.0.0.1:9000 minioadmin miniopass
mc mb -p local/web
mc policy set public local/web
mc policy set public local/web/*
chmod -R 777 /data/web/

/usr/bin/docker-entrypoint.sh "$@"
