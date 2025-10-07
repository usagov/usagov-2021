#!/bin/sh

# Automated Database Backup Script
# Runs daily to create database snapshots

SCRIPT_PATH=$(dirname "$0")

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to log messages with timestamp
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $1"
}

# Load configuration
CONFIG_FILE="$SCRIPT_PATH/tome-backup.conf"
if [ -f "$CONFIG_FILE" ]; then
    . "$CONFIG_FILE"
else
    log_message "ERROR: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Set defaults if not defined in config
ENABLE_DB_BACKUPS=${ENABLE_DB_BACKUPS:-true}
DB_BACKUP_RETENTION_DAYS=${DB_BACKUP_RETENTION_DAYS:-30}
DB_BACKUP_PREFIX=${DB_BACKUP_PREFIX:-DB-AUTO}

# Check if database backups are enabled
if [ "$ENABLE_DB_BACKUPS" != "true" ]; then
    log_message "Database backups are disabled in configuration"
    exit 0
fi

# Generate backup tag with timestamp
TIMESTAMP=$(date +"%Y_%m_%d_%H_%M_%S")
DB_BACKUP_TAG="${DB_BACKUP_PREFIX}-${TIMESTAMP}"

# Get environment info
APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name' 2>/dev/null)
APP_SPACE=${APP_SPACE:-local}

log_message "Starting database backup for environment: $APP_SPACE"
log_message "Backup tag: $DB_BACKUP_TAG"

# Setup log file
LOG_DIR="/tmp/tome-log"
mkdir -p "$LOG_DIR"
LOGFILE="$LOG_DIR/db-backup-${TIMESTAMP}.log"

# Function to setup S3 environment (similar to tome-sync.sh)
setup_s3_env() {
    if [ -n "$VCAP_SERVICES" ]; then
        export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket' 2>/dev/null)
        export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region' 2>/dev/null)
        export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id' 2>/dev/null)
        export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key' 2>/dev/null)
        export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname' 2>/dev/null)
        if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
            export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint' 2>/dev/null)
        fi
        
        # Set S3 parameters for local/minio support
        if [ "${APP_SPACE}" = "local" ]; then
            S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
        else
            S3_EXTRA_PARAMS=""
        fi
    else
        log_message "WARNING: VCAP_SERVICES not found - not in Cloud Foundry environment"
        S3_EXTRA_PARAMS=""
    fi
    
    if [ -z "$BUCKET_NAME" ]; then
        log_message "ERROR: Could not determine S3 bucket name"
        exit 1
    fi
}

# Function to create database backup
create_db_backup() {
    log_message "Creating database backup..." | tee -a "$LOGFILE"
    
    # Use the existing database backup script from bin/snapshot-backups
    if [ -f "$SCRIPT_PATH/../bin/snapshot-backups/db-dump-to-snapshot" ]; then
        log_message "Using existing db-dump-to-snapshot script" | tee -a "$LOGFILE"
        
        # The db-dump-to-snapshot script expects a tag parameter
        "$SCRIPT_PATH/../bin/snapshot-backups/db-dump-to-snapshot" "$DB_BACKUP_TAG" 2>&1 | tee -a "$LOGFILE"
        
        if [ $? -eq 0 ]; then
            log_message "Database backup completed successfully" | tee -a "$LOGFILE"
            return 0
        else
            log_message "ERROR: Database backup failed" | tee -a "$LOGFILE"
            return 1
        fi
    else
        log_message "ERROR: Database backup script not found" | tee -a "$LOGFILE"
        return 1
    fi
}

# Function to cleanup old database backups
cleanup_old_db_backups() {
    if [ "$ENABLE_AUTO_CLEANUP" != "true" ]; then
        log_message "Automatic cleanup is disabled" | tee -a "$LOGFILE"
        return 0
    fi
    
    log_message "Cleaning up database backups older than $DB_BACKUP_RETENTION_DAYS days..." | tee -a "$LOGFILE"
    
    # Calculate cutoff date
    CUTOFF_DATE=$(date -u -d "${DB_BACKUP_RETENTION_DAYS} days ago" '+%Y_%m_%d' 2>/dev/null || date -u -v-${DB_BACKUP_RETENTION_DAYS}d '+%Y_%m_%d' 2>/dev/null)
    
    if [ -n "$CUTOFF_DATE" ]; then
        # List and delete old database backups
        aws s3 ls s3://$BUCKET_NAME/db_backup/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | while read -r line; do
            backup_path=$(echo "$line" | awk '{print $4}')
            backup_date=$(echo "$backup_path" | grep -o "${DB_BACKUP_PREFIX}-[0-9_]*" | sed 's/.*-\([0-9_]*\).*/\1/' | cut -c 1-10)
            if [ -n "$backup_date" ] && [ "$backup_date" \< "$CUTOFF_DATE" ]; then
                backup_prefix=$(echo "$backup_path" | cut -d'/' -f1-2)
                log_message "Removing old database backup: $backup_prefix" | tee -a "$LOGFILE"
                aws s3 rm s3://$BUCKET_NAME/$backup_prefix --recursive $S3_EXTRA_PARAMS 2>&1 | tee -a "$LOGFILE"
            fi
        done
        log_message "Database backup cleanup completed" | tee -a "$LOGFILE"
    else
        log_message "WARNING: Could not determine cutoff date for database backup cleanup" | tee -a "$LOGFILE"
    fi
}

# Function to upload log to S3
upload_log() {
    if [ -f "$LOGFILE" ] && [ -n "$BUCKET_NAME" ]; then
        log_message "Uploading backup log to S3..." | tee -a "$LOGFILE"
        aws s3 cp "$LOGFILE" "s3://$BUCKET_NAME/db-backup-logs/$(basename "$LOGFILE")" $S3_EXTRA_PARAMS 2>&1 | tee -a "$LOGFILE"
        
        if [ $? -eq 0 ]; then
            log_message "Log uploaded successfully" | tee -a "$LOGFILE"
        else
            log_message "WARNING: Failed to upload log to S3" | tee -a "$LOGFILE"
        fi
    fi
}

# Main execution
main() {
    log_message "=== Database Backup Started ===" | tee -a "$LOGFILE"
    
    # Setup environment
    setup_s3_env
    
    # Create database backup
    if create_db_backup; then
        print_status $GREEN "Database backup successful: $DB_BACKUP_TAG"
        
        # Cleanup old backups
        cleanup_old_db_backups
        
        # Upload log
        upload_log
        
        log_message "=== Database Backup Completed Successfully ===" | tee -a "$LOGFILE"
        exit 0
    else
        print_status $RED "Database backup failed"
        
        # Upload log even on failure
        upload_log
        
        log_message "=== Database Backup Failed ===" | tee -a "$LOGFILE"
        exit 1
    fi
}

# Run main function
main "$@"