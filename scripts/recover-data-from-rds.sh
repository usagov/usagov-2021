#!/bin/bash

# we might be running in circleci
if [ -f /home/circleci/project/env.local ]; then
   . /home/circleci/project/env.local
fi
# we might be running from a local dev machine
SCRIPT_DIR="$(dirname "$0")"
if [ -f $SCRIPT_DIR/env.local ]; then
   . $SCRIPT_DIR/env.local
fi
if [ -f ./env.local ]; then
   . ./env.local
fi

if ! command -v cf &> /dev/null
then
   echo "CF : the cloud foundry client could not be found and is required"
   exit 1
fi
if [ -f $SCRIPT_DIR/../bin/deploy/includes ]; then
   . $SCRIPT_DIR/../bin/deploy/includes
else
   echo Cannot find $SCRIPT_DIR/../bin/deploy/includes
   exit 1
fi

# just testing?#
#if [ x$1 = x"--dryrun" ]; then
#   export echo=echo
#   dryrun=$1
#   shift
#fi

SPACE=${1:-please-provide-space-name-as-first-argument}
SPACE=$(echo "$SPACE" | tr '[:upper:]' '[:lower:]')
assertCurSpace $SPACE
shift

DB_BACKUP_SERVICE_NAME=${1:-please-provide-db-backup-service-name-as-second-argument}
DB_BACKUP_SERVICE_GUID=$(getServiceGUID $DB_BACKUP_SERVICE_NAME)
if [ -z "$DB_BACKUP_SERVICE_GUID" ]; then
   echo "Could not find service $DB_BACKUP_SERVICE_NAME in space $SPACE"
   exit 1
fi
shift

DB_BACKUP_CREDS=$(cf curl "/v3/service_instances/$DB_BACKUP_SERVICE_GUID/credentials" | jq .)
if [ -z "$DB_BACKUP_CREDS" -o "$DB_BACKUP_CREDS" = "null" ]; then
   echo "Could not retrieve credentials for service $DB_BACKUP_SERVICE_NAME"
   exit 1
fi

echo "Retrieved credentials for service $DB_BACKUP_SERVICE_NAME"

DBHOST=$(echo $DB_BACKUP_CREDS | jq -r '.host')
DBPORT=$(echo $DB_BACKUP_CREDS | jq -r '.port')
DBUSER=$(echo $DB_BACKUP_CREDS | jq -r '.username')
DBPASS=$(echo $DB_BACKUP_CREDS | jq -r '.password')
DBNAME=$(echo $DB_BACKUP_CREDS | jq -r '.db_name') # | sed -r 's/-restore$//' | tr -d '-')

CREDSFILE=$(mktemp)

echo "Writing creds to $CREDSFILE"

cat <<EOF >$CREDSFILE
[client]
user="${DBUSER}"
password="${DBPASS}"

EOF

cat $CREDSFILE | cf ssh cms -c "cat - > /tmp/restore.creds.cnf"
rm $CREDSFILE

# The mariadb-dump options here follow what `drush sql:dump` does:
echo "Dumping database to cms:/tmp/restore.sql:"
cf ssh cms -c "mariadb-dump --defaults-file=/tmp/restore.creds.cnf ${DBNAME} --port=${DBPORT} --host=${DBHOST} --no-autocommit --single-transaction --skip-ssl --opt -Q | sed 's/^\/\*\!999999\\\-.*$//' > /tmp/restore.sql"
cf ssh cms -c "rm -f /tmp/restore.creds.cnf"

echo "Restoring database dump to cms via drush sql-cli:"
cf ssh cms -c "if [ -f /tmp/restore.sql ]; then . /etc/profile; drush sql-drop -y; cat /tmp/restore.sql | drush sql-cli; drush cr; fi"
cf ssh cms -c "rm -f /tmp/restore.sql"
