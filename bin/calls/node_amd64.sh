#!/bin/bash
echo "check environment for arm64"
arm=`uname -m`
node='node'
nodearm64='arm64v8/node'
echo ${arm}
if [ ${arm} == 'arm64' ]; then
  sed -i '' -e 's|FROM node:|FROM '${nodearm64}':|' .docker/Dockerfile-cms
fi  
