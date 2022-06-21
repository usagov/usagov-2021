#!/bin/bash

sleep 2

if [ mc --insecure alias list docker2 2>1 >/dev/null ]; then
  mc --insecure alias rm docker
fi;
mc --insecure alias set docker https://127.0.0.1:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-miniopass}

mc --insecure mb -p docker/local
mc --insecure policy set public docker/local

mkdir -p /data/local/web
chmod -R 777 /data/local/web
mc --insecure policy set public docker/local/web

mkdir -p /data/local/cms
chmod -R 777 /data/local/cms
mc --insecure policy set public docker/local/cms

mkdir -p /data/local/cms/public
chmod -R 777 /data/local/cms/public
mc --insecure policy set public docker/local/cms/public

mkdir -p /data/local/cms/private
chmod -R 777 /data/local/cms/private
mc --insecure policy set public docker/local/cms/private

mkdir -p /data/local/tome-log
chmod -R 777 /data/local/tome-log
mc --insecure policy set public docker/local/tome-log
