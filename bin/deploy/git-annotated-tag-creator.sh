#!/bin/sh

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

SCRIPT_DIR="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"
if [ -f $SCRIPT_DIR/../deploy/includes ]; then
  . $SCRIPT_DIR/../deploy/includes
else
    echo "File does not exist: $SCRIPT_DIR/../deploy/includes"
    exit 1
fi

# just testing?
if [ x$1 == x"--dryrun" ]; then
  export echo=echo
  shift
fi

SPACE=${1:-please-provide-space-as-first-argument}
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]') ## lowercase, so tags are properly formatted
shift


CCI_BUILD=$1
re='^[0-9]+$'
if ! [[ $CCI_BUILD =~ $re ]]; then
    echo "Invalid CirclCI build ID ($CCI_BUILD)"
    exit 1
fi
shift

CMS_DIGEST=$1
assertIsImageDigest $CMS_DIGEST
shift

WAF_DIGEST=$1
assertIsImageDigest $WAF_DIGEST
shift

WWW_DIGEST=$1
assertIsImageDigest $WWW_DIGEST
shift

if [ $CMS_DIGEST = $WAF_DIGEST -o $CMS_DIGEST = $WWW_DIGEST ]; then
    echo "Cannot use the same digest for any of the CMS, WAF and WWW images!"
    exit 1
fi

TAG_MESSAGE="'CCI_BUILD=${CCI_BUILD}|CMS_DIGEST=${CMS_DIGEST}|WAF_DIGEST=${WAF_DIGEST}|WWW_DIGEST=${WWW_DIGEST}'"

BACKUP_TAG=usagov-cci-build-${CCI_BUILD}-${SPACE}

$echo git tag -d $BACKUP_TAG &>/dev/null
$echo git tag -a -m $TAG_MESSAGE $BACKUP_TAG
if [ $? ]; then
    $echo git push origin $BACKUP_TAG
fi
