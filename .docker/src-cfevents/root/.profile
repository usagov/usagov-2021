# ~/.profile: executed by Bourne-compatible login shells.

CF_SPACE=${1:-dr}

if [ "$BASH" ]; then
  if [ -f ~/.bashrc ]; then
    . ~/.bashrc
  fi
fi

# aws cli is installed here:
PATH=$PATH:/usr/local/bin

mesg n 2> /dev/null || true

if [ ! -f ~/.certs-updated ]; then
   echo "... Combining and updating certs ..."
   if [ -d "$CF_SYSTEM_CERT_PATH" ]; then
      cp $CF_SYSTEM_CERT_PATH/*  /usr/local/share/ca-certificates/
   fi
   /usr/sbin/update-ca-certificates
   touch ~/.certs-updated
fi

CF_USERNAME=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.username')
CF_PASSWORD=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cfevents-service-account") | .credentials.password')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.fips_endpoint')
export S3_BUCKET
export S3_ENDPOINT

export AWS_ACCESS_KEY_ID=$(echo -E "$VCAP_SERVICES" | jq -r ".s3[0].credentials.access_key_id" | uniq )
export AWS_SECRET_ACCESS_KEY=$(echo -E "$VCAP_SERVICES" | jq -r ".s3[0].credentials.secret_access_key" | uniq )
export AWS_DEFAULT_REGION=$(echo -E "$VCAP_SERVICES" | jq -r ".s3[0].credentials.region" | uniq )

CF_API="https://api.fr.cloud.gov"
CF_ORG=gsa-tts-usagov

cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

### aws cli does not want proxy envs
function aws_cp() {
   src=$1
   dst=$2
   local HTTP_PROXY=
   local HTTPS_PROXY=
   local http_proxy=
   local https_proxy=
   aws s3 cp $src $dst
}

echo "CFEvents App Setup Complete"
