#!/bin/bash

mkdir -p /data/local/web
mc --insecure config host rm docker
mc --insecure config host add docker http://127.0.0.1:9000 ${MINIO_ROOT_USER:-minioadmin} ${MINIO_ROOT_PASSWORD:-miniopass}
mc --insecure mb -p docker/local
mc --insecure policy set public docker/local
mc --insecure policy set public docker/local/
mc --insecure policy set public docker/local/web
mc --insecure policy set public docker/local/web/
chmod -R 777 /data/local/web

/usr/bin/docker-entrypoint.sh "$@"
