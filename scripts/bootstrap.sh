#!/bin/sh
set -uo pipefail

echo "Bootstrapping Drupal ... "

if [ -n "${FIX_FILE_PERMS:-}" ]; then
  echo  "Fixing File Permissions ... "
  chown nginx:nginx /var/www
  find /var/www -group 0 -user 0 -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx
  find /var/www -not -user $(id -u nginx) -not -group $(id -g nginx) -print0 | xargs -P 0 -0 --no-run-if-empty chown --no-dereference nginx:nginx
fi

if [ "${CF_INSTANCE_INDEX:-''}" == "0" ]; then
    if [ -z "${VCAP_SERVICES:-}" ]; then
      echo "VCAP_SERVICES must a be set in the environment: aborting bootstrap";
      exit 1;
    fi

    echo  "Updating drupal ... "
    SECRETS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "secrets") | .credentials')
    ADMIN_EMAIL=$(echo $SECRETS | jq -r '.ADMIN_EMAIL')

    drush state:set system.maintenance_mode 1 -y
    drush cr
    drush updatedb --no-cache-clear -y
    drush cim -y || drush cim -y
    drush cim -y
    drush php-eval "node_access_rebuild();" -y
    drush config:set system.site mail $ADMIN_EMAIL -y > /dev/null
    drush state:set system.maintenance_mode 0 -y
    drush cr

    echo "Bootstrap finished"
else
    echo "Bootstrap skipping config import because we are not Instance 0"
fi
