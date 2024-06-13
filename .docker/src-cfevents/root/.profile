# ~/.profile: executed by Bourne-compatible login shells.

if [ "$BASH" ]; then
  if [ -f ~/.bashrc ]; then
    . ~/.bashrc
  fi
fi

mesg n 2> /dev/null || true

if [ ! -f ~/.certs-updated ]; then
   echo "... Combining and updating certs ..."
   if [ -d "$CF_SYSTEM_CERT_PATH" ]; then
      cp $CF_SYSTEM_CERT_PATH/*  /usr/local/share/ca-certificates/
   fi
   /usr/sbin/update-ca-certificates
   touch ~/.certs-updated
fi

SA_USER=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.username';)
SA_PASS=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.password')

echo SA_USER $SA_USER
echo SA_PASS $SA_PASS

#SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')
#SECAUTHSECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secauthsecrets") | .credentials')

#APP_NAME=$(echo $VCAP_APPLICATION | jq -r '.name')
#APP_ROOT=$(dirname "$0")
#APP_ID=$(echo "$VCAP_APPLICATION" | jq -r '.application_id')
#ADMIN_EMAIL=$(echo $SECRETS | jq -r '.ADMIN_EMAIL')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.fips_endpoint')
export S3_BUCKET
export S3_ENDPOINT

echo S3_BUCKET   $S3_BUCKET
echo S3_ENDPOINT $S3_ENDPOINT

#SPACE=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')

echo "CFEvents App Setup Complete"
