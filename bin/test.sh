#!/bin/bash

# Container ID or Name
CONTAINER_ID="05f189a25cd0"

# Extract container details for review
docker inspect $CONTAINER_ID > ${CONTAINER_ID}_inspect.json

# Run docker-bench-security for the specific container
docker run --disable-content-trust -it --rm --net host --pid host --userns host --cap-add audit_control \
    -e DOCKER_CONTENT_TRUST=1 -v /var/run/docker.sock:/var/run/docker.sock \
    --label docker_bench_security docker-bench-security -b -c "container_images,container_runtime"

echo "Security review completed for container: $CONTAINER_ID"

