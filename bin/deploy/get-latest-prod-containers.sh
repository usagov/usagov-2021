#!/usr/bin/env bash

#
# This is meant to be sourced (e.g.  . bin/deploy/get-latest-prod-containers.sh) to populate the 4 env vars below
#
export CCI_BUILD_ID=$(bin/deploy/git-annotation-parser.sh prod | grep deploy-cms | awk '{print $2; }')
export CMS_DIGEST=$(bin/deploy/git-annotation-parser.sh prod | grep deploy-cms | awk '{print $3; }')
export WAF_DIGEST=$(bin/deploy/git-annotation-parser.sh prod | grep deploy-waf | awk '{print $3; }')
export WWW_DIGEST=$(bin/deploy/git-annotation-parser.sh prod | grep deploy-www | awk '{print $3; }')

echo CCI_BUILD_ID: $CCI_BUILD_ID
echo CMS_DIGEST:   $CMS_DIGEST
echo WAF_DIGEST:   $WAF_DIGEST
echo WWW_DIGEST:   $WWW_DIGEST
