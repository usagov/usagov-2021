#!/bin/ash
#set -euo pipefail
set -uo pipefail

if [ -z "${VCAP_SERVICES:-}" ]; then
    echo "VCAP_SERVICES must a be set in the environment: aborting bootstrap";
    exit 1;
fi

if [ ! -f /container_start_timestamp ]; then
  touch /container_start_timestamp
  chmod a+r /container_start_timestamp
  echo "$(date +'%s')" > /container_start_timestamp
fi

echo "Deployment: bootstrap starting"

# Add the cloud foundry certificates for communication with other apps in cloud.gov.
# cert-watcher.sh does this too, but we want it to happen before
# any php processes start, and especially before the newrelic-daemon starts.
if [ -d "${CF_SYSTEM_CERT_PATH:-}" ]; then
   cp ${CF_SYSTEM_CERT_PATH:-}/*  /usr/local/share/ca-certificates/
fi
/usr/sbin/update-ca-certificates


SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')
SECAUTHSECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secauthsecrets") | .credentials')

APP_NAME=$(echo $VCAP_APPLICATION | jq -r '.name')
APP_ROOT=$(dirname "$0")
APP_ID=$(echo "$VCAP_APPLICATION" | jq -r '.application_id')


AWSRDS=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"]')
if [ "$AWSRDS" = "null" ]; then
  echo "WARNING: The aws-rds variable is not set in the VCAP_SERVICES which is only a problem if this is NOT the WWW instance."
else
  echo "NOTICE: This bootstrap.sh sees the aws-rds variable is indeed set in the VCAP_SERVICES so this application should be able to connect to RDS/MySQL."
  DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
  DB_USER=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.username')
  DB_PW=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.password')
  DB_HOST=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.host')
  DB_PORT=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.port')
fi

ADMIN_EMAIL=$(echo $SECRETS | jq -r '.ADMIN_EMAIL')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
S3_ENDPOINT=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.fips_endpoint')
export S3_BUCKET
export S3_ENDPOINT

SPACE=$(echo $VCAP_APPLICATION | jq -r '.["space_name"]')

case $SPACE in
dev)
  WWW_HOST=beta-dev.usa.gov
  ;;
dr)
  WWW_HOST=beta-dr.usa.gov
  ;;
stage)
  WWW_HOST=beta-stage.usa.gov
  ;;
prod)
  WWW_HOST=www.usa.gov
  ;;
*)
  echo "**** WARNING:  Operating in cf space '$SPACE' - trying old method of WWW_HOST extraction.  May fail ****"
  WWW_HOST=${WWW_HOST:-$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep 'www\.usa\.gov' | tr '\n' ' ')}
  WWW_HOST=${WWW_HOST:-$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep -v 'apps.internal' | grep beta | tr '\n' ' ')}
  ;;
esac

CMS_HOST=${CMS_HOST:-$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep cms | tr '\n' ' ')}
if [ -z "$WWW_HOST" ]; then
  WWW_HOST="*.app.cloud.gov"
fi
if [ -z "$CMS_HOST" ]; then
  CMS_HOST=$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | head -n 1)
fi
export WWW_HOST
export CMS_HOST

S3_ROOT_WEB=${S3_ROOT_WEB:-/web}
S3_ROOT_CMS=${S3_ROOT_CMS:-/cms/public}
S3_HOST=${S3_HOST:-$S3_BUCKET.$S3_ENDPOINT}
S3_PROXY_WEB=${S3_PROXY_WEB:-$S3_HOST$S3_ROOT_WEB}
S3_PROXY_CMS=${S3_PROXY_CMS:-$S3_HOST$S3_ROOT_CMS}
S3_PROXY_PATH_CMS=${S3_PROXY_PATH_CMS:-/s3/files}
export S3_ROOT_WEB
export S3_ROOT_CMS
export S3_HOST
export S3_PROXY_WEB
export S3_PROXY_CMS
export S3_PROXY_PATH_CMS

if [ -f "/etc/php81/php-fpm.d/env.conf.tmpl" ]; then
  cp /etc/php81/php-fpm.d/env.conf.tmpl /etc/php81/php-fpm.d/env.conf
  echo "env[S3_PROXY_PATH_CMS] = "$S3_PROXY_PATH_CMS >> /etc/php81/php-fpm.d/env.conf
  echo "env[S3_PROXY_CMS] = "$S3_PROXY_CMS >> /etc/php81/php-fpm.d/env.conf
  echo "env[S3_ROOT_CMS] = "$S3_ROOT_CMS >> /etc/php81/php-fpm.d/env.conf
  echo "env[S3_HOST] = "$S3_HOST >> /etc/php81/php-fpm.d/env.conf
fi

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

export EN_404_PAGE=${EN_404_PAGE:-/page-error/index.html};
export ES_404_PAGE=${ES_404_PAGE:-/es/pagina-error/index.html};

export NEW_RELIC_DISPLAY_NAME=${NEW_RELIC_DISPLAY_NAME:-$(echo $SECRETS | jq -r '.NEW_RELIC_DISPLAY_NAME')}
export NEW_RELIC_APP_NAME=${NEW_RELIC_APP_NAME:-$(echo $SECRETS | jq -r '.NEW_RELIC_APP_NAME')}
export NEW_RELIC_API_KEY=${NEW_RELIC_API_KEY:-$(echo $SECRETS | jq -r '.NEW_RELIC_API_KEY')}
export NEW_RELIC_LICENSE_KEY=${NEW_RELIC_LICENSE_KEY:-$(echo $SECRETS | jq -r '.NEW_RELIC_LICENSE_KEY')}

SP_KEY=$(echo $SECAUTHSECRETS | jq -r '.spkey')
SP_CRT=$(echo $SECAUTHSECRETS | jq -r '.spcrt')

# seems not to be needed
#spkey=$(echo "$SP_KEY" | awk '{gsub(/\\n/, "\n")}1')
#spcrt=$(echo "$SP_CRT" | awk '{gsub(/\\n/, "\n")}1')

echo "$SP_KEY" > /var/www/sp.key
echo "$SP_CRT" > /var/www/sp.crt

ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')
# this overwrites the files in place, so be careful mounting in docker
echo "Inserting environment variables into nginx config templates ... "
for FILE in /etc/nginx/*/*.conf.tmpl /etc/nginx/*.conf.tmpl; do
    if [ -f "$FILE" ]; then
        OUTFILE=${FILE%.tmpl}
        echo " generating $OUTFILE"
        envsubst "$ENV_VARIABLES" < "$FILE" > "$OUTFILE"
    fi
done

# update new relic with environment specific settings
if [ -f "/etc/php81/conf.d/newrelic.ini" ]; then
  if [ -n "$NEW_RELIC_LICENSE_KEY" ] && [ "$NEW_RELIC_LICENSE_KEY" != "null" ]; then
    echo "Setting up New Relic ... "
    sed -i \
        -e "s|;\?newrelic.license =.*|newrelic.license = ${NEW_RELIC_LICENSE_KEY}|" \
        -e "s|;\?newrelic.process_host.display_name =.*|newrelic.process_host.display_name = ${NEW_RELIC_DISPLAY_NAME:-usa-cms}|" \
        -e "s|;\?newrelic.appname =.*|newrelic.appname = \"${NEW_RELIC_APP_NAME:-CMS-dev;USA.gov}\"|" \
        -e "s|;\?newrelic.daemon.loglevel =.*|newrelic.daemon.loglevel = \"${NEW_RELIC_LOG_LEVEL:-warning}\"|" \
        -e "s|;\?newrelic.enabled =.*|newrelic.enabled = true|" \
        /etc/php81/conf.d/newrelic.ini

  else
    echo "Turning off New Relic ... "
    sed -i \
        -e "s/;\?newrelic.enabled =.*/newrelic.enabled = false/" \
        /etc/php81/conf.d/newrelic.ini
  fi
  if [ "NoProxy" = "${PROXYROUTE:NoProxy}" ]; then
      # TODO: what to do here? PROXYROUTE should be set!
    sed -i \
      -e "s|;\?newrelic.daemon.ssl_ca_bundle =.*|newrelic.daemon.ssl_ca_bundle = \"/etc/ssl/certs/ca-certificates.crt\"|" \
      -e "s|;\?newrelic.daemon.ssl_ca_path =.*|newrelic.daemon.ssl_ca_path = \"/etc/ssl/certs/\"|" \
      /etc/php81/conf.d/newrelic.ini
  else
      # We are probably being needlessly redundant in setting both ssl_ca_bundle and ssl_ca_path.
      # NR says it will search ssl_ca_bundle first, then the certificates in ssl_ca_path. We have ssl_ca_bundle within ssl_ca_path, so ...
    sed -i \
      -e "s|;\?newrelic.daemon.ssl_ca_bundle =.*|newrelic.daemon.ssl_ca_bundle = \"/etc/ssl/certs/ca-certificates.crt\"|" \
      -e "s|;\?newrelic.daemon.ssl_ca_path =.*|newrelic.daemon.ssl_ca_path = \"/etc/ssl/certs/\"|" \
      -e "s|;\?newrelic.daemon.proxy =.*|newrelic.daemon.proxy = \"$PROXYROUTE\"|" \
      /etc/php81/conf.d/newrelic.ini
  fi
fi

echo "Checking for .git file to identify local installation; \"fatal: not a git repository\" is expected elsewhere"
git config --global --add safe.directory /var/www
if [[ $(git rev-parse --is-inside-work-tree) ]]; then
  # Find the php.ini file
  PHP_INI=$(php -i | grep 'Loaded Configuration File' | awk '{print $NF}')

  # Check if opcache is already disabled
  if grep -q 'opcache\.enable\s*=\s*0' "$PHP_INI"; then
    echo "OPCache is already disabled."
  else
    echo "Disabling OPCache..."
    sed -i 's/^opcache\.enable\s*=.*/opcache.enable=0/' "$PHP_INI"
    sed -i 's/^opcache\.enable_cli\s*=.*/opcache.enable_cli=0/' "$PHP_INI"
    echo "OPCache disabled."
  fi
fi

# php needs a restart so new relic ini changes take effect
if [ -d /var/run/s6/services/php ]; then
  echo "Asking php to reload conf ... "
  s6-svc -2 /var/run/s6/services/php
fi
# nginx needs a restart so proxy changes take effect
if [ -d /var/run/s6/services/nginx ]; then
  echo "Asking nginx to reload conf ... "
  s6-svc -h /var/run/s6/services/nginx
fi

if [ ! -d /var/www/private ]; then
  echo "Creating private directory ... "
  mkdir /var/www/private
  chown nginx:nginx /var/www/private
fi

if [ -n "${FIX_FILE_PERMS:-}" ]; then
  echo  "Fixing File Permissions ... "
  chown nginx:nginx /var/www
  find /var/www -group 0 -user 0 -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx
  find /var/www -not -user $(id -u nginx) -not -group $(id -g nginx) -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx
fi

if [ "${CF_INSTANCE_INDEX:-''}" == "0" ] && [ -z "${SKIP_DRUPAL_BOOTSTRAP:-}" ]; then

    echo  "Updating drupal ... "
    initial_mm_state=$(drush state:get system.maintenance_mode)
    if [ x$initial_mm_state = x0 ]; then
       echo "maintenance mode is off:  turning on for updatedb"
       drush state:set system.maintenance_mode 1 -y
    fi

    drush cr
    drush updatedb --no-cache-clear -y
    drush cim -y || drush cim -y
    drush cim -y
    echo "Notice: If a TXNDATA error is seen above this line, we believe it is likley NewRelic having a connection-reset-by-peer issue. We dont believe this is causing drush-cim to crash."

    drush php-eval "node_access_rebuild();" -y

    if [ x$initial_mm_state = x0 ]; then
      drush state:set system.maintenance_mode 0 -y
    fi
    drush cr

    echo "Bootstrap finished"
else
    echo "Bootstrap skipping Drupal CIM because: Instance=${CF_INSTANCE_INDEX:-''} Skip=${SKIP_DRUPAL_BOOTSTRAP:-''}"
fi

echo "Updating SAMLAuth configuration for $SPACE:"
/var/www/scripts/gsaauth/configset.sh $SPACE

echo "Adding the USPS credentials..."
if [[ ${USPS_USERID:-"unset"} != "unset" ]] &&
   [[ ${USPS_PASSWORD:-"unset"} != "unset" ]]; then
    echo "const USPS_USERID = '${USPS_USERID}';" > ./web/themes/custom/usagov/scripts/usps-credentials.js
    echo "const USPS_PASSWORD = '${USPS_PASSWORD}';" >> ./web/themes/custom/usagov/scripts/usps-credentials.js
    echo "USPS credentials added successfully!"
else
    echo "No credentials found in the env."
    echo "const error = 'No credentials found in the env.'" > ./web/themes/custom/usagov/scripts/usps-credentials.js
fi
