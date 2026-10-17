#!/bin/sh

# luacheck image tags include "latest" and "edge," "edge" being later than "latest"
# Explicitly turn off DOCKER_CONTENT_TRUST, as pipelinecomponents are not signing their images.
# (Okay because we are not using this to build an image).
docker run --disable-content-trust -it --rm \
      -v "$(pwd)/project_conf:/code/project_conf" \
      pipelinecomponents/luacheck:latest luacheck project_conf/scripts
