#!/bin/sh

echo=echo

DEPLOY_ENV=$1
CCI_BUILD=$2
CMS_DIGEST=$3
WAF_DIGEST=$4

TAG_MESSAGE="'CCI_BUILD=${CCI_BUILD}|CMS_DIGEST=${CMS_DIGEST}|WAF_DIGEST=${WAF_DIGEST}'"

BACKUP_TAG=usagov-cci-build-${CCI_BUILD}-${DEPLOY_ENV}

$echo git tag -d $BACKUP_TAG
$echo git tag -a -m $TAG_MESSAGE $BACKUP_TAG
if [ $? ]; then
    $echo git push origin $BACKUP_TAG
fi
