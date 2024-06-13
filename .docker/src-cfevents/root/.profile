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

CF_USERNAME=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.username';)
CF_PASSWORD=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.password')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.fips_endpoint')
export S3_BUCKET
export S3_ENDPOINT

CF_API="https://api.fr.cloud.gov"
CF_ORG=gsa-tts-usagov
CF_SPACE=dev

cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

echo "CFEvents App Setup Complete"
