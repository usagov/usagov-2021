#!/bin/bash

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
  . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
  . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
  . ./env.local
fi
if [ -f $SCRIPT_DIR/deploy/includes ]; then
  . $SCRIPT_DIR/deploy/includes
else
   echo Cannot find $SCRIPT_DIR/deploy/includes
   exit 1
fi

if ! command -v cf &> /dev/null
then
    echo "CF : the cloud foundry client could not be found and is required"
    exit 1
fi

# just testing?
if [ x$1 = x"--dryrun" ]; then
  echo=echo
  dryrun=$1
  shift
fi

APP_SPACE=${1:-please-provide-space-name-as-first-argument}
APP_SPACE=$(echo "$APP_SPACE" | tr '[:upper:]' '[:lower:]')
assertCurSpace $APP_SPACE
shift

# expose or hide
if [ x$1 = x"--expose" ]; then
  expose=1
  shift
elif [ x$1 = x"--hide" ]; then
  expose=0
  shift
fi

if [ x$expose = x"" ]; then
  echo "Usage: $0 [--dryrun] <space> [--expose|--hide]"
  exit 1
fi


WAF_APP=${WAF_APP:-waf}
CMS_APP=${CMS_APP:-cms}

echo cf target -s $APP_SPACE
$echo cf target -s $APP_SPACE

echo assertCurSpace $APP_SPACE
$echo assertCurSpace $APP_SPACE

echo  cf set-env $WAF_APP IP_ALLOW_ALL_CMS $expose
$echo cf set-env $WAF_APP IP_ALLOW_ALL_CMS $expose

echo cf set-env $WAF_APP IP_ALLOW_ALL_WWW $expose
$echo cf set-env $WAF_APP IP_ALLOW_ALL_WWW $expose

echo cf restage $WAF_APP
$echo cf restage $WAF_APP
