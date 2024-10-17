#!/usr/bin/env bash

SPACE=$(cf target | grep space: | awk '{print $2}');
if [ -z "$SPACE" ]; then
  echo "You must choose a space before procesing ./bin/cloudgov/space (personal|dev|stage|prod|shared-egress)"
  exit 1
fi;

SECAUTHSECRETS=$(cf curl /v2/user_provided_service_instances/$(cf service secauthsecrets --guid) | jq -r '.entity | select(.name == "secauthsecrets") | .credentials' )
SP_KEY=$(echo -E "$SECAUTHSECRETS" | jq -r '.spkey')
SP_CRT=$(echo -E "$SECAUTHSECRETS" | jq -r '.spcrt')

BASENAME=gsaauth.cms-${SPACE}.usa.gov

echo "$SP_KEY" > prvkey
chmod go-rwx prvkey
ssh-keygen -y -f prvkey > ${BASENAME}.pub
rm prvkey

echo "$SP_CRT" > ${BASENAME}.crt

ls -l ${BASENAME}*
