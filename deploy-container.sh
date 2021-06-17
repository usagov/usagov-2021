#!/bin/sh
#
# This script will attempt to create a container image and store it on docker hub
# to be used when launching cloud.gov images
#


GITBRANCH=$(git symbolic-ref --short HEAD 2>/dev/null || echo "")
GITCOMMIT=$(git rev-parse HEAD 2>/dev/null || echo "")
GITTAG=$(git tag --points-at $(git rev-parse HEAD 2>/dev/null) | grep ^v | sort -rV | head -n 1 2>/dev/null || echo "")
CONTAINERTAG=${1:-$VERSIONTAG}

if [ -z "$CONTAINERTAG" ]
then
      echo "Must specify a container tag to build"
      exit 1;
fi;

chmod -R u+w ./web/sites/default/

docker build --force-rm \
    -t ednark/usagov-2021:$CONTAINERTAG \
    -f .docker/Dockerfile . \
    --build-arg GITBRANCH=$GITBRANCH \
    --build-arg GITCOMMIT=$GITCOMMIT \
    --build-arg GITTAG=$GITTAG

docker push ednark/usagov-2021:$CONTAINERTAG
