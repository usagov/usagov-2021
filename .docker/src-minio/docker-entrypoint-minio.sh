#!/bin/bash

mkdir -p /data/local/web
mc config host rm local
mc config host add local http://127.0.0.1:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-miniopass}
mc mb -p local/web
mc policy set public local/web
mc policy set public local/web/*
chmod -R 777 /data/local/web

/usr/bin/docker-entrypoint.sh "$@"
