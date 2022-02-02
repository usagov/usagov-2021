#!/bin/sh
set -uo pipefail

# EFILES=$(ls /run/s6/basedir/env/)
# for EFILE in $EFILES; do
#   eval "${EFILE}"=`cat /run/s6/basedir/env/$EFILE`;
#   echo "setting ${EFILE}";
# done
[ -f "/run/s6/basedir/env/VCAP_APPLICATION" ] && VCAP_APPLICATION=`cat /run/s6/basedir/env/VCAP_APPLICATION`
[ -f "/run/s6/basedir/env/VCAP_SERVICES" ] && VCAP_SERVICES=`cat /run/s6/basedir/env/VCAP_SERVICES`

if [ -z "${VCAP_SERVICES:-}" ]; then
    echo "VCAP_SERVICES must a be set in the environment: aborting bootstrap";
    exit 1;
fi

APP_ROOT=$(dirname "$0")

APP_NAME=$(echo $VCAP_APPLICATION | jq -r '.name')
APP_ID=$(echo "$VCAP_APPLICATION" | jq -r '.application_id')
SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')

DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_USER=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.username')
DB_PW=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.password')
DB_HOST=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.host')
DB_PORT=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.port')

ADMIN_EMAIL=$(echo $SECRETS | jq -r '.ADMIN_EMAIL')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
S3_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export S3_BUCKET
export S3_REGION

WWW_HOST=$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep beta | head -n 1)
CMS_HOST=$(echo $VCAP_APPLICATION | jq -r '.["application_uris"][]' | grep cms  | head -n 1)
export WWW_HOST
export CMS_HOST

S3_WEBROOT=${S3_WEBROOT:-/web}
S3_PROXY=${S3_PROXY:-$S3_BUCKET.s3-fips.$S3_REGION.amazonaws.com$S3_WEBROOT}
S3_HOST=${S3_HOST:-$S3_BUCKET.s3-fips.$S3_REGION.amazonaws.com}
export S3_WEBROOT
export S3_PROXY
export S3_HOST

export DNS_SERVER=${DNS_SERVER:-$(grep -i '^nameserver' /etc/resolv.conf|head -n1|cut -d ' ' -f2)}

export EN_404_PAGE=${EN_404_PAGE:-/404/index.html};
export ES_404_PAGE=${ES_404_PAGE:-/es/404/index.html};

ENV_VARIABLES=$(awk 'BEGIN{for(v in ENVIRON) print "$"v}')

FILES="/etc/nginx/nginx.conf /etc/nginx/conf.d/default.conf"
# this overwrites the files in place, so be careful mounting in docker
for FILE in $FILES; do
    if [ -f "$FILE.tmpl" ]; then
        envsubst "$ENV_VARIABLES" < "$FILE.tmpl" > "$FILE"
    fi
done

## operating on long lists of docker mounted files for local dev is too slow, and prod doesn't need this
# echo  "Fixing File Permissions ... "
# chown nginx:nginx /var/www
# find /var/www -group 0 -user 0 -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx
# find /var/www -not -user $(id -u nginx) -not -group $(id -g nginx) -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx

# if [ -n "$S3_BUCKET" ] && [ -n "$S3_REGION" ]; then
#   # Add Proxy rewrite rules to the top of the htaccess file
#   # sed "s/^#RewriteRule .s3fs/RewriteRule ^s3fs/" "$APP_ROOT/web/template-.htaccess" > "$APP_ROOT/web/.htaccess"
#   # Add Proxy rewrite rule to nginx ???
# else
#   # cp "$APP_ROOT/web/template-.htaccess" "$APP_ROOT/web/.htaccess"
# fi

# install_drupal() {
#     ROOT_USER_NAME=$(echo $SECRETS | jq -r '.ROOT_USER_NAME')
#     ROOT_USER_PASS=$(echo $SECRETS | jq -r '.ROOT_USER_PASS')

#     : "${ROOT_USER_NAME:?Need and root user name for Drupal}"
#     : "${ROOT_USER_PASS:?Need and root user pass for Drupal}"

#     drupal site:install \
#         --root=$APP_ROOT/web \
#         --no-interaction \
#         --account-name="$ROOT_USER_NAME" \
#         --account-pass="$ROOT_USER_PASS" \
#         --langcode="en"
#     # Delete some data created in the "standard" install profile
#     # See https://www.drupal.org/project/drupal/issues/2583113
#     drupal --root=$APP_ROOT entity:delete shortcut_set default --no-interaction
#     drupal --root=$APP_ROOT config:delete active field.field.node.article.body --no-interaction
#     # Set site uuid to match our config
#     UUID=$(grep uuid $APP_ROOT/web/sites/default/config/system.site.yml | cut -d' ' -f2)
#     drupal --root=$APP_ROOT config:override system.site uuid $UUID
# }

if [ "${CF_INSTANCE_INDEX:-''}" == "1" ]; then
#   if [ "$APP_ID" = "docker" ] ; then
#     # make sure database is created
#     echo "create database $DB_NAME;" | mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER" --password="$DB_PW" || true
#   fi

#    drupal list | grep database > /dev/null || echo "install_drupal"
#   # Mild data migration: fully delete database entries related to these
#   # modules. These plugins (and the dependencies) can be removed once they've
#   # been uninstalled in all environments

#   # Sync configs from code
#    drupal config:import

    echo  "Updating drupal ... "
    PATH=$PATH:/var/www/vendor/bin
    drush state:set system.maintenance_mode 1 -y
    drush cr
    drush updatedb --no-cache-clear -y
    drush cim -y || drush cim -y
    drush cim -y
    drush php-eval "node_access_rebuild();" -y
    drush config:set system.site mail $ADMIN_EMAIL -y > /dev/null
    drush state:set system.maintenance_mode 0 -y
    drush cr

# #   # Import initial content
# #   # drush --root=$APP_ROOT/web default-content-deploy:import --no-interaction

# #   # Clear the cache
#     drupal cache:rebuild --no-interaction
    echo "Bootstrap finished"
else
    echo "Bootstrap skipping Drupal CIM because we are not Instance 0"
fi
