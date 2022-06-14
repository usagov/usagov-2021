#!/bin/bash
echo "check environment for arm64"
arm=`uname -m`
node='node'
nodearm64='arm64v8/node'
pfro='#{phantform_for_m1}'
pend='#{phantform_for_m1-END}'
# pjs=$(cat bin/calls/phantomjs)
pjs='jknbjnaerijbaieoriboj'
echo '=========='${arm}'=========='

if [ ${arm} == 'arm64' ]; then
  sed -i '' -e 's|FROM node:|FROM '${nodearm64}':|' .docker/Dockerfile-cms
  awk "
    BEGIN      {p=1}
    /^$pfro/   {print;system(\"cat bin/calls/phantomjs;printf '\n'\");p=0}
    /^$pend/   {p=1}
    p" .docker/Dockerfile-cms > .docker/Dockerfile-cms_new
  mv .docker/Dockerfile-cms_new .docker/Dockerfile-cms
  
fi