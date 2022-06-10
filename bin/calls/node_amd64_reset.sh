#!/bin/bash
arm=`uname -m`
node='node'
nodearm64='arm64v8/node'
echo ${arm}
if [ ${arm} == 'arm64' ]; then
  sed -i '' -e 's|FROM arm64v8/node:|FROM '${node}':|' .docker/Dockerfile-cms
fi  