# ~/.profile: executed by Bourne-compatible login shells.

CF_SPACE=$1

if [ x$CF_SPACE = x ]; then
   CF_SPACE=$(echo $VCAP_APPLICATION | jq -r '.space_name')
else
   shift
fi

TASK=$1
if [ x$TASK = x ]; then
   TASK=cron
fi

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

CF_USERNAME=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cron-service-account") | .credentials.username';)
CF_PASSWORD=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cron-service-account") | .credentials.password')

case $TASK in
   event)
      S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage") | .credentials.bucket')
      S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage") | .credentials.fips_endpoint')
      ;;
   callwait)
      STORAGE_SERVICE=cron-callwait-storage
      S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.bucket')
      S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.fips_endpoint')
      ;;
   *)
      S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.bucket')
      S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.fips_endpoint')
      ;;
esac

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
   rec=$3
   if [ x$rec != '--recursive' ]; then
      rec=''
   fi
   local HTTP_PROXY=
   local HTTPS_PROXY=
   local http_proxy=
   local https_proxy=
   aws s3 cp $rec $src $dst
}

function aws_ls() {
   src=$1
   local HTTP_PROXY=
   local HTTPS_PROXY=
   local http_proxy=
   local https_proxy=
   aws s3 ls $src
}

function aws_rm() {
   src=$1
   rec=$2
   if [ x$rec != '--recursive' ]; then
      rec=''
   fi
   local HTTP_PROXY=
   local HTTPS_PROXY=
   local http_proxy=
   local https_proxy=
   aws s3 rm $rec $src
}

export CFEVENTS_DATE_FORMAT="%Y-%m-%dT%H:%M:%SZ"
export CFEVENTS_DEFAULT_LASTRUN="2 months ago"
### -> if we do not have GNU formatting, use 'now - (number of seconds in 60 days)': "@$(( $(date +%s) - 5259492 ))"

### use ps from procps-ng package on alpine containers
TASKLOCK_PS=/bin/ps

### Use /opt/cron on the container
TASKLOCK_SCRIPT_ROOT=/opt/cron

### Maybe we should be using /var/run/tasks/ on the container?
TASKLOCK_RUN_ROOT=/tmp/tasks/run

echo "Cron App Setup Complete"
