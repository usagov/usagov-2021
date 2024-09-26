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

### If we detect PROXYROUTE env var, then set HTTP/S Proxy vars:
if [ -n "$PROXYROUTE" ]; then
   export HTTPS_PROXY=$PROXYROUTE
   export HTTP_PROXY=$PROXYROUTE
   export https_proxy=$PROXYROUTE
   export http_proxy=$PROXYROUTE
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
      export S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage") | .credentials.bucket')
      export S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage") | .credentials.fips_endpoint')
      export AWS_ACCESS_KEY_ID=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage")    | .credentials.access_key_id')
      export AWS_SECRET_ACCESS_KEY=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage")    | .credentials.secret_access_key')
      export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-event-storage")    | .credentials.region')
      ;;
   callwait)
      export S3_BUCKET=$(echo "$VCAP_SERVICES"             | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.bucket')
      export S3_ENDPOINT=$(echo "$VCAP_SERVICES"           | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.fips_endpoint')
      export AWS_ACCESS_KEY_ID=$(echo "$VCAP_SERVICES"     | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.access_key_id')
      export AWS_SECRET_ACCESS_KEY=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.secret_access_key')
      export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES"    | jq -r '.["s3"][]? | select(.name == "cron-callwait-storage") | .credentials.region')
      ;;
   *)
      export S3_BUCKET=$(echo "$VCAP_SERVICES"             | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.bucket')
      export S3_ENDPOINT=$(echo "$VCAP_SERVICES"           | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.fips_endpoint')
      export AWS_ACCESS_KEY_ID=$(echo "$VCAP_SERVICES"     | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.access_key_id')
      export AWS_SECRET_ACCESS_KEY=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.secret_access_key')
      export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES"    | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.region')
      ;;
esac

#echo S3_BUCKET: $S3_BUCKET
#echo S3_ENDPOINT: $S3_ENDPOINT
#echo AWS_ACCESS_KEY_ID: $AWS_ACCESS_KEY_ID
#echo AWS_SECRET_ACCESS_KEY: $AWS_SECRET_ACCESS_KEY
#echo AWS_DEFAULT_REGION: $AWS_DEFAULT_REGION

CF_API="https://api.fr.cloud.gov"
CF_ORG=gsa-tts-usagov

API_RESULT=0
AUTH_RESULT=0
TARGET_RESULT=0

cf api "$CF_API" &> /dev/null
API_RESULT=$?

cf auth "$CF_USERNAME" "$CF_PASSWORD" &> /dev/null
AUTH_RESULT=$?

cf target -o "$CF_ORG" -s "$CF_SPACE" &> /dev/null
TARGET_RESULT=$?

if [ 0 -ne $API_RESULT -o 0 -ne $AUTH_RESULT -o 0 -ne $TARGET_RESULT ]; then
   echo "ERROR: Cloud Foundry Initialization Failed"
fi

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

#echo "Cron App Setup Complete"
