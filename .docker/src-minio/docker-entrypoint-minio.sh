#!/bin/bash

mc --insecure alias rm docker
mc --insecure alias set docker https://127.0.0.1:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-miniopass}

mc --insecure mb -p docker/local
mc --insecure policy set public docker/local

mkdir -p /data/local/web
chmod -R 777 /data/local/web
mc --insecure policy set public docker/local/web

mkdir -p /data/local/public
chmod -R 777 /data/local/public
mc --insecure policy set public docker/local/public

mkdir -p /data/local/private
chmod -R 777 /data/local/private
mc --insecure policy set public docker/local/private

mkdir -p /data/local/tome-log
chmod -R 777 /data/local/tome-log
mc --insecure policy set public docker/local/tome-log

/usr/bin/docker-entrypoint.sh "$@"
