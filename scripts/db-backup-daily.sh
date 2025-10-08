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

    # Ensure we're in the right working directory
    # In Cloud Foundry, we should be in /var/www
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        log_message "Cloud Foundry detected, changing to /var/www directory" | tee -a "$LOGFILE"
        cd /var/www
        log_message "New working directory: $(pwd)" | tee -a "$LOGFILE"
    fi

    # Use the existing database backup script from bin/snapshot-backups
    # Try multiple possible paths to find the script
    # In Cloud Foundry, the bin directory might be in different locations
    DB_SCRIPT_PATHS="
        $SCRIPT_PATH/../bin/snapshot-backups/db-dump-to-snapshot
        ./bin/snapshot-backups/db-dump-to-snapshot
        /var/www/bin/snapshot-backups/db-dump-to-snapshot
        $(pwd)/bin/snapshot-backups/db-dump-to-snapshot
        /home/vcap/app/bin/snapshot-backups/db-dump-to-snapshot
        /home/vcap/deps/0/bin/snapshot-backups/db-dump-to-snapshot
        /usr/local/bin/db-dump-to-snapshot
        /opt/bin/snapshot-backups/db-dump-to-snapshot
    "

    log_message "Current working directory: $(pwd)" | tee -a "$LOGFILE"
    log_message "SCRIPT_PATH: $SCRIPT_PATH" | tee -a "$LOGFILE"

    DB_SCRIPT_PATH=""
    for path in $DB_SCRIPT_PATHS; do
        log_message "Checking for database script at: $path" | tee -a "$LOGFILE"
        if [ -f "$path" ]; then
            DB_SCRIPT_PATH="$path"
            log_message "Found database script at: $DB_SCRIPT_PATH" | tee -a "$LOGFILE"
            break
        fi
    done

    if [ -n "$DB_SCRIPT_PATH" ] && [ -f "$DB_SCRIPT_PATH" ]; then
        log_message "Using database backup script: $DB_SCRIPT_PATH" | tee -a "$LOGFILE"

        # The db-dump-to-snapshot script expects a tag parameter
        "$DB_SCRIPT_PATH" "$DB_BACKUP_TAG" 2>&1 | tee -a "$LOGFILE"

        if [ $? -eq 0 ]; then
            log_message "Database backup completed successfully" | tee -a "$LOGFILE"
            return 0
        else
            log_message "ERROR: Database backup failed" | tee -a "$LOGFILE"
            return 1
        fi
    else
        log_message "ERROR: Database backup script not found in any expected location" | tee -a "$LOGFILE"
        log_message "Searched paths:" | tee -a "$LOGFILE"
        for path in $DB_SCRIPT_PATHS; do
            if [ -f "$path" ]; then
                log_message "  $path - EXISTS" | tee -a "$LOGFILE"
            else
                log_message "  $path - NOT FOUND" | tee -a "$LOGFILE"
            fi
        done

        log_message "Debugging directory structure:" | tee -a "$LOGFILE"
        log_message "Current working directory contents:" | tee -a "$LOGFILE"
        ls -la "." 2>&1 | head -10 | tee -a "$LOGFILE"

        # Search for any bin directories in the system
        log_message "Searching for bin directories in Cloud Foundry container:" | tee -a "$LOGFILE"
        find /var/www -name "bin" -type d 2>/dev/null | head -5 | tee -a "$LOGFILE" || log_message "No bin directories found in /var/www" | tee -a "$LOGFILE"
        find /home/vcap -name "bin" -type d 2>/dev/null | head -5 | tee -a "$LOGFILE" || log_message "No bin directories found in /home/vcap" | tee -a "$LOGFILE"
        
        # Search specifically for the db-dump-to-snapshot script anywhere
        log_message "Searching for db-dump-to-snapshot script anywhere in container:" | tee -a "$LOGFILE"
        find /var/www -name "db-dump-to-snapshot" -type f 2>/dev/null | tee -a "$LOGFILE" || log_message "db-dump-to-snapshot not found in /var/www" | tee -a "$LOGFILE"
        find /home/vcap -name "db-dump-to-snapshot" -type f 2>/dev/null | tee -a "$LOGFILE" || log_message "db-dump-to-snapshot not found in /home/vcap" | tee -a "$LOGFILE"
        find /usr -name "db-dump-to-snapshot" -type f 2>/dev/null | tee -a "$LOGFILE" || log_message "db-dump-to-snapshot not found in /usr" | tee -a "$LOGFILE"
        find /opt -name "db-dump-to-snapshot" -type f 2>/dev/null | tee -a "$LOGFILE" || log_message "db-dump-to-snapshot not found in /opt" | tee -a "$LOGFILE"
        
        # Show environment variables that might help locate scripts
        log_message "Relevant environment variables:" | tee -a "$LOGFILE"
        env | grep -E "(PATH|HOME|VCAP)" | head -10 | tee -a "$LOGFILE"

        log_message "Attempting to resolve path issue..." | tee -a "$LOGFILE"
        log_message "Note: Ensure you're running this from the project root directory (/var/www in CF)" | tee -a "$LOGFILE"
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