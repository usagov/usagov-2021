#!/bin/bash
arm=`uname -m`
node='node'
nodearm64='arm64v8/node'
pfro='#{phantform_for_m1}'
pend='#{phantform_for_m1-END}'
echo '=========='${arm}'=========='
if [ ${arm} == 'arm64' ]; then
  sed -i '' -e 's|FROM arm64v8/node:|FROM '${node}':|' .docker/Dockerfile-cms
  sed -i '' -e "/^$pfro/,/^$pend/{/^$pfro/!{/^$pend/!d;};}" .docker/Dockerfile-cms
fi  