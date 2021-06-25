#!/bin/ash 
set -euo pipefail

SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')
APP_NAME=$(echo $VCAP_APPLICATION | jq -r '.name')
APP_ROOT=$(dirname "$0")
APP_ID=$(echo "$VCAP_APPLICATION" | jq -r '.application_id')

DB_NAME=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.db_name')
DB_USER=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.username')
DB_PW=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.password')
DB_HOST=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.host')
DB_PORT=$(echo $VCAP_SERVICES | jq -r '.["aws-rds"][] | .credentials.port')

S3_BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
export S3_BUCKET
S3_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
export S3_REGION

ADMIN_EMAIL=$(echo $SECRETS | jq -r '.ADMIN_EMAIL')

echo  "Fixing File Permissions ... "
chown nginx:nginx /var/www
find /var/www -group 0 -user 0 -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx

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

if [ "${CF_INSTANCE_INDEX:-''}" == "0" ] && [ "${APP_NAME}" == "web" ]; then
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

    echo  "Updating configs ... "
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
fi
