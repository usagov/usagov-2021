#!/bin/bash

CF_SVC_USER=$1
CF_SVC_PASS=$2
LOCAL_FILE=${3:-usagov.sql}
LOCAL_PORT=3307

echo "[INFO] Logging into Cloud.gov"
cf login -a https://api.fr.cloud.gov -u $CF_SVC_USER -p $CF_SVC_PASS
# cf target -o $CF_PERSONAL_ORG -s $CF_PERSONAL_SPACE

echo "[INFO] Creating access keys"
cf create-service-key database db-dump >/dev/null 2>&1
CF_GUID=$(cf app cms --guid)
CF_INFO=$(cf curl /v2/info)
CF_DB_INFO=$(cf service-key database db-dump)

CF_DB_PORT=$(echo "$CF_DB_INFO" | grep '"port":' | sed 's/.*"port": "\([^"]*\)".*/\1/')
CF_DB_HOST=$(echo "$CF_DB_INFO" | grep '"host":' | sed 's/.*"host": "\([^"]*\)".*/\1/')
CF_DB_USER=$(echo "$CF_DB_INFO" | grep '"username":' | sed 's/.*"username": "\([^"]*\)".*/\1/')
CF_DB_PASS=$(echo "$CF_DB_INFO" | grep '"password":' | sed 's/.*"password": "\([^"]*\)".*/\1/')
CF_DB_NAME=$(echo "$CF_DB_INFO" | grep '"db_name":' | sed 's/.*"db_name": "\([^"]*\)".*/\1/')

CF_SSH_ENDPOINT=$(echo "$CF_INFO" | grep '"app_ssh_endpoint":' | sed 's/.*"app_ssh_endpoint": "\([^:]*\).*".*/\1/')
CF_SSH_CODE=$(cf ssh-code)

echo "[INFO] Creating local sql connection"
mkdir -p ~/.ssh
touch ~/.ssh/config
echo "Host *"  > ~/.ssh/config
echo "  ServerAliveInterval 15" >> ~/.ssh/config
echo "  ServerAliveCountMax 2"  >> ~/.ssh/config
nohup sshpass -p $CF_SSH_CODE ssh \
    -oStrictHostKeyChecking=no -oUserKnownHostsFile=/dev/null \
    -4 -N -p 2222 -L $LOCAL_PORT:$CF_DB_HOST:$CF_DB_PORT cf:$CF_GUID/0@$CF_SSH_ENDPOINT >/dev/null 2>&1 &
sleep 5

echo "[INFO] Estimate DB Size for progress bar"
DB_SIZE=$(mysql \
    -h127.0.0.1 \
    -P$LOCAL_PORT \
    -u$CF_DB_USER \
    -p$CF_DB_PASS \
    --silent \
    --skip-column-names \
    -e "SELECT ROUND(SUM(data_length) * 1.13554) AS 'size_bytes' \
        FROM information_schema.TABLES \
        WHERE table_schema='$CF_DB_NAME';" \
)
hsize=$(numfmt --to=iec-i --suffix=B "$DB_SIZE")

echo "[INFO] Dumping database (size=$hsize) into $LOCAL_FILE ..."
mysqldump -h127.0.0.1 -P$LOCAL_PORT -u$CF_DB_USER -p$CF_DB_PASS $CF_DB_NAME \
    --opt --hex-blob --set-gtid-purged=OFF --compression-algorithms=zlib --quick \
    | pv --size $DB_SIZE > /hostfs/$LOCAL_FILE
