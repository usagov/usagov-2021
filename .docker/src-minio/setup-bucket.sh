#!/bin/bash

sleep 2

if [ mc --insecure alias list minio 2>1 >/dev/null ]; then
  mc --insecure alias rm minio
fi;
mc --insecure alias set minio https://127.0.0.1:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-miniopass}

mc --insecure mb -p minio/local
mc anonymous --insecure set public /data/local
mc anonymous --insecure set public minio/local

mkdir -p /data/local/web
chmod -R 777 /data/local/web
mc anonymous --insecure set public /data/local/web
mc anonymous --insecure set public minio/local/web

mkdir -p /data/local/cms
chmod -R 777 /data/local/cms
mc anonymous --insecure set public /data/local/cms
mc anonymous --insecure set public minio/local/cms

mkdir -p /data/local/cms/public
chmod -R 777 /data/local/cms/public
mc anonymous --insecure set public /data/local/cms/public
mc anonymous --insecure set public minio/local/cms/public

mkdir -p /data/local/cms/private
chmod -R 777 /data/local/cms/private
mc anonymous --insecure set public /data/local/cms/private
mc anonymous --insecure set public minio/local/cms/private

mkdir -p /data/local/tome-log
chmod -R 777 /data/local/tome-log
mc anonymous --insecure set public /data/local/tome-log
mc anonymous --insecure set public minio/local/tome-log
