#!/bin/sh
arm=`uname -m`
node='node'
nodearm64='amd64/node'
echo ${amd}
if [ ${arm} == 'arm64' ]; then
  sed -i '' -e 's|{node}|'${nodearm64}'|' .docker/Dockerfile-cms
else
  sed -i '' -e 's|{node}|'${node}'|' .docker/Dockerfile-cms
fi