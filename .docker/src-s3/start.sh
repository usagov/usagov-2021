#!/bin/sh
#
# Entrypoint for the local S3 service ("s3" in docker-compose.yml): SeaweedFS
# standing in for the cloud.gov S3 bucket. See docs/Local_S3_for_Dev.md.
#
# Runs as root. It makes the TLS files readable by the seaweed user, starts a
# background job that creates the bucket once the S3 API answers, and then
# hands over to the image's own entrypoint with the arguments from
# docker-compose.yml. That entrypoint drops to the seaweed user.
#
# NIST 800-53: CM-6 - Configuration Settings (local development only; holds no
# production credentials)

set -e

BUCKET=${S3_BUCKET:-local}
SOURCE_CERTS=/etc/seaweedfs/local/certs
CERTS=/etc/seaweedfs/certs

# The bind-mounted key keeps its file mode from the host, which the seaweed
# user may not be able to read on Linux and WSL.
mkdir -p "$CERTS"
cp "$SOURCE_CERTS/public.crt" "$SOURCE_CERTS/private.key" "$CERTS/"
chown -R seaweed:seaweed "$CERTS"
chmod 600 "$CERTS/private.key"

create_bucket() {
  tries=0
  # Any HTTP answer means the S3 API is up; an anonymous request gets a 403.
  until curl -sk -o /dev/null https://127.0.0.1:9000/; do
    tries=$((tries + 1))
    if [ "$tries" -ge 120 ]; then
      echo "s3: the S3 API didn't start, so bucket '$BUCKET' wasn't created" >&2
      return 1
    fi
    sleep 1
  done

  # s3.bucket.list prints one "  <name>  size:..." line per bucket.
  if echo "s3.bucket.list" | weed shell -master=127.0.0.1:9333 2>/dev/null \
    | awk -v bucket="$BUCKET" '$1 == bucket { found = 1 } END { exit !found }'; then
    echo "s3: bucket '$BUCKET' exists"
  elif echo "s3.bucket.create -name $BUCKET" | weed shell -master=127.0.0.1:9333; then
    echo "s3: created bucket '$BUCKET'"
  else
    echo "s3: could not create bucket '$BUCKET'" >&2
    return 1
  fi
}

# The subshell exits at once, so the job is handed to the container's init
# (init: true in docker-compose.yml), which reaps it when it finishes.
(create_bucket &)

exec /entrypoint.sh "$@"
