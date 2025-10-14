#!/bin/sh

# Spins up a docker container to run luacheck on the scripts directory.
# Run this from the repo root (it uses pwd).

# luacheck image tags include "latest" and "edge," "edge" being later than "latest"
docker run -it --rm \
      -v "$(pwd)/scripts:/code/scripts" \
      pipelinecomponents/luacheck:latest luacheck scripts
