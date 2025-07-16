#!/bin/sh

# Intended for restoring a database from another RDS instance. Run on the CMS.
# Assumes a user-provided service named "database-backup" containing credentials is bound to the CMS.

if [ -z "${VCAP_SERVICES:-}" ]; then
    echo "VCAP_SERVICES must be set in the environment"
    exit 1;
fi

BACKUP_CREDS=$(echo $VCAP_SERVICES | jq -r '.["user-provided"][] | select(.name == "database-backup") | .credentials')

if [ -z "$BACKUP_CREDS" ]; then
    echo 'ERROR: Backup database credentials were not found. Expected a user-provided service named "database-backup" with credentials.'
    exit 1;
fi

DBHOST=$(echo $BACKUP_CREDS | jq -r '.hostname')
DBPORT=$(echo $BACKUP_CREDS | jq -r '.port')
DBUSER=$(echo $BACKUP_CREDS | jq -r '.username')
DBPASS=$(echo $BACKUP_CREDS | jq -r '.password')
DBNAME=$(echo $BACKUP_CREDS | jq -r '.dbname' | sed -r 's/-restore$//' | tr -d '-')

TMPDIR=$(mktemp -d) # We'll put the file here.
CREDSFILE=$(mktemp)

echo "Writing creds to $CREDSFILE"

cat <<EOF >$CREDSFILE
[client]
user="${DBUSER}"
password="${DBPASS}"

EOF

# The mariadb-dump options here follow what `drush sql:dump` does:
echo "Dumping data to ${TMPDIR}/restore.sql"
mariadb-dump --defaults-file=${CREDSFILE} ${DBNAME} --port=${DBPORT} --host=${DBHOST} --no-autocommit --single-transaction --opt -Q  > ${TMPDIR}/restore.sql

ls -lh $TMPDIR

rm $CREDSFILE
