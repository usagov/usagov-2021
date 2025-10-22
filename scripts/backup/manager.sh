#!/bin/sh

# Backup Manager
# Unified manager for all backups (static site, public files, and database)
# Handles backup creation, listing, restore, and cleanup operations

# Find the script directory and project root
SCRIPT_PATH=$(dirname "$0")
if [ "$(basename "$SCRIPT_PATH")" = "backup" ]; then
    # Running from scripts/backup directory
    PROJECT_ROOT="$(cd "$SCRIPT_PATH/../.." && pwd)"
    BACKUP_DIR="$SCRIPT_PATH"
elif [ -d "scripts/backup" ]; then
    # Running from project root
    PROJECT_ROOT="$(pwd)"
    BACKUP_DIR="$PROJECT_ROOT/scripts/backup"
else
    # Try to find the project root by looking for scripts/backup
    current_dir="$(pwd)"
    while [ "$current_dir" != "/" ]; do
        if [ -d "$current_dir/scripts/backup" ]; then
            PROJECT_ROOT="$current_dir"
            BACKUP_DIR="$current_dir/scripts/backup"
            break
        fi
        current_dir=$(dirname "$current_dir")
    done

    if [ -z "$PROJECT_ROOT" ]; then
        echo "ERROR: Cannot find scripts/backup directory. Please run from project root or scripts/backup directory."
        exit 1
    fi
fi

# Load configuration
CONFIG_FILE="$BACKUP_DIR/backup-system.conf"
if [ -f "$CONFIG_FILE" ]; then
    . "$CONFIG_FILE"
else
    echo "ERROR: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Set defaults if not defined in config
BACKUP_PREFIX=${BACKUP_PREFIX:-AUTO}
DB_BACKUP_PREFIX=${DB_BACKUP_PREFIX:-AUTO}
BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-7}
DB_BACKUP_RETENTION_DAYS=${DB_BACKUP_RETENTION_DAYS:-30}
AUTO_STATIC_BACKUP_PATH=${AUTO_STATIC_BACKUP_PATH:-auto-backups/web-backup}
AUTO_PUBLIC_BACKUP_PATH=${AUTO_PUBLIC_BACKUP_PATH:-auto-backups/public_backup}
AUTO_DB_BACKUP_PATH=${AUTO_DB_BACKUP_PATH:-auto-backups/database}
ENABLE_DB_BACKUPS=${ENABLE_DB_BACKUPS:-true}
ENABLE_DB_AUTO_CLEANUP=${ENABLE_DB_AUTO_CLEANUP:-true}
DB_BACKUP_TIME=${DB_BACKUP_TIME:-"19:00"}
ENABLE_SMART_PUBLIC_BACKUP=${ENABLE_SMART_PUBLIC_BACKUP:-true}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $1"
}

show_usage() {
    echo "Usage: $0 <command> [backup_types] [options]"
    echo ""
    echo "Main Commands:"
    echo "  list [types] [days]      List backups (default: all types, 30 days)"
    echo "  backup [types]           Create backups (default: all types)"
    echo "  clean [types] [days]     Remove old backups (default: all types, 30 days)"
    echo "  restore <tag> [--only=type,type]  Restore backups (unchanged)"
    echo "  info [types] <tag>       Show backup details (default: all types)"
    echo ""
    echo "Backup Types:"
    echo "  all                      All backup types (default)"
    echo "  static                   Static site backups"
    echo "  public                   Public file backups"
    echo "  db                       Database backups"
    echo "  static,public            Multiple types (comma-separated)"
    echo ""
    echo "Examples:"
    echo "  $0 list                  # List all backups from last 30 days"
    echo "  $0 list static,db        # List static and database backups"
    echo "  $0 list all 7            # List all backups from last 7 days"
    echo "  $0 backup                # Create all backups"
    echo "  $0 backup db             # Create database backup only"
    echo "  $0 clean                 # Clean all old backups (30 days)"
    echo "  $0 clean static 7        # Clean static backups older than 7 days"
    echo "  $0 info static AUTO-dev-2024_03_15_14_30_00"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00 --only=static,db"
}

get_container_tag() {
    # Try to get container tag from /etc/motd if in CF environment
    if [ -n "$VCAP_APPLICATION" ] && [ -f "/etc/motd" ]; then
        CONTAINER_TAG=$(grep containertag /etc/motd 2>/dev/null | sed 's/containertag\:[[:space:]]*//' | sed 's/^[[:space:]]*//')
        if [ -n "$CONTAINER_TAG" ]; then
            echo "$CONTAINER_TAG"
            return 0
        fi
    fi

    # Fallback: try to get from environment variable or git if available
    if [ -n "$CONTAINER_TAG" ]; then
        echo "$CONTAINER_TAG"
        return 0
    elif command -v git >/dev/null 2>&1; then
        # Use short git commit hash as fallback
        git_hash=$(git rev-parse --short HEAD 2>/dev/null)
        if [ -n "$git_hash" ]; then
            echo "git-$git_hash"
            return 0
        fi
    fi

    # Final fallback: use "unknown"
    echo "unknown"
    return 0
}

setup_s3_vars() {
    if [ -z "$BUCKET_NAME" ]; then
        export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket')
        export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region')
        export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id')
        export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key')
        export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname')
        if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" == "null" ]; then
            export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint')
        fi

        # grab the cloudgov space we are hosted in
        APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name')
        APP_SPACE=${APP_SPACE:-local}

        # endpoint and ssl specifications only necessary on local for minio support
        S3_EXTRA_PARAMS=""
        if [ "${APP_SPACE}" = "local" ]; then
            S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
        fi
    fi

    if [ -z "$BUCKET_NAME" ]; then
        print_status $RED "Error: Could not determine S3 bucket name. Make sure VCAP_SERVICES is set."
        exit 1
    fi
}

# ===================================================================
# ARGUMENT PARSING FUNCTIONS
# ===================================================================

# Parse backup types from argument (e.g., "static,db" or "all" or empty)
parse_backup_types() {
    local types_arg="$1"

    # Default to all if empty
    if [ -z "$types_arg" ] || [ "$types_arg" = "all" ]; then
        echo "static,public,db"
        return 0
    fi

    # Return the types as provided
    echo "$types_arg"
}

# Check if a backup type is in the list
has_backup_type() {
    local backup_types="$1"
    local check_type="$2"

    echo "$backup_types" | grep -q "$check_type"
}

# Get days argument with default
get_days_arg() {
    local days_arg="$1"
    local default_days="$2"

    if [ -z "$days_arg" ] || ! echo "$days_arg" | grep -q '^[0-9]\+$' ; then
        echo "$default_days"
    else
        echo "$days_arg"
    fi
}

# Handle backup command
run_backup_command() {
    local types_arg="${1:-all}"
    local backup_types=$(parse_backup_types "$types_arg")

    print_status $BLUE "Starting automatic backups for: $backup_types"

    # Run static backup if requested
    if has_backup_type "$backup_types" "static"; then
        print_status $GREEN "Creating static site backup..."
        create_static_backup
    fi

    # Run public backup if requested
    if has_backup_type "$backup_types" "public"; then
        print_status $GREEN "Creating public files backup..."
        create_public_backup
    fi

    # Run database backup if requested
    if has_backup_type "$backup_types" "db"; then
        print_status $GREEN "Creating database backup..."
        create_db_backup
    fi

    print_status $BLUE "Backup operations completed."
}

# Handle clean command
run_clean_command() {
    local types_arg="${1:-all}"
    local days_arg="${2:-30}"

    local backup_types=$(parse_backup_types "$types_arg")
    local days=$(get_days_arg "$days_arg" "30")

    print_status $YELLOW "WARNING: This will delete backups older than $days days for: $backup_types"
    printf "Are you sure you want to continue? [y/N]: "
    read -r confirm

    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        print_status $RED "Clean operation cancelled."
        return 1
    fi

    print_status $BLUE "Cleaning backups older than $days days..."

    # Clean static and public backups if requested (they share the same function)
    if has_backup_type "$backup_types" "static" || has_backup_type "$backup_types" "public"; then
        print_status $GREEN "Cleaning static/public backups..."
        clean_old_backups "$days"
    fi

    # Clean database backups if requested
    if has_backup_type "$backup_types" "db"; then
        print_status $GREEN "Cleaning database backups..."
        cleanup_old_db_backups "$days"
    fi

    print_status $BLUE "Clean operations completed."
}

# Handle info command
run_info_command() {
    local types_arg="${1:-all}"
    local tag="${2:-}"

    local backup_types=$(parse_backup_types "$types_arg")

    if [ -n "$tag" ]; then
        # Show info for specific tag
        backup_info "$tag"
    else
        # Show general backup info for requested types
        print_status $BLUE "Backup System Information"
        echo "========================"
        echo

        if has_backup_type "$backup_types" "static"; then
            echo "Static Site Backups:"
            echo "  Path: $AUTO_STATIC_BACKUP_PATH"
            echo "  Prefix: $BACKUP_PREFIX"
            echo "  Retention: $BACKUP_RETENTION_DAYS days"
            echo
        fi

        if has_backup_type "$backup_types" "public"; then
            echo "Public Files Backups:"
            echo "  Path: $AUTO_PUBLIC_BACKUP_PATH"
            echo "  Prefix: $BACKUP_PREFIX"
            echo "  Retention: $BACKUP_RETENTION_DAYS days"
            echo
        fi

        if has_backup_type "$backup_types" "db"; then
            echo "Database Backups:"
            echo "  Path: $AUTO_DB_BACKUP_PATH"
            echo "  Prefix: $DB_BACKUP_PREFIX"
            echo "  Retention: $DB_BACKUP_RETENTION_DAYS days"
            echo
        fi

        echo "S3 Bucket: $BUCKET_NAME"
        echo "Configuration: $CONFIG_FILE"
    fi
}

# ===================================================================
# DATABASE BACKUP FUNCTIONS
# ===================================================================

create_db_backup() {
    setup_s3_vars

    # Check if database backups are enabled
    if [ "$ENABLE_DB_BACKUPS" != "true" ]; then
        log_message "Database backups disabled"
        return 0
    fi

    # Generate backup tag with timestamp and container tag
    TIMESTAMP=$(date +"%Y_%m_%d_%H_%M_%S")
    CONTAINER_TAG=$(get_container_tag)
    DB_BACKUP_TAG="${DB_BACKUP_PREFIX}-${APP_SPACE}-${CONTAINER_TAG}-${TIMESTAMP}"

    log_message "Starting database backup: $DB_BACKUP_TAG ($APP_SPACE, container: $CONTAINER_TAG)"

    # Setup log file
    LOG_DIR="/tmp/tome-log"
    mkdir -p "$LOG_DIR"
    LOGFILE="$LOG_DIR/db-backup-${TIMESTAMP}.log"

    log_message "Creating database backup..." | tee -a "$LOGFILE"

    # Set working directory for drush
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        cd /var/www
    elif [ "$PROJECT_ROOT" != "$(pwd)" ]; then
        # Change to project root if not already there
        cd "$PROJECT_ROOT"
    fi

    # Create temporary files for database backup
    TEMP_SQL="/tmp/${DB_BACKUP_TAG}.sql"
    TEMP_GZIP="/tmp/${DB_BACKUP_TAG}.sql.gz"

    # Create database dump using drush
    log_message "Creating database dump..." | tee -a "$LOGFILE"
    if command -v drush >/dev/null 2>&1; then
        # Clear cache first, then create dump to SQL file
        drush cr 2>&1 | tee -a "$LOGFILE"
        drush sql:dump --result-file="$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"
        DUMP_EXIT_CODE=$?
    else
        log_message "ERROR: drush command not found" | tee -a "$LOGFILE"
        return 1
    fi

    if [ $DUMP_EXIT_CODE -ne 0 ]; then
        log_message "ERROR: Database dump failed with exit code: $DUMP_EXIT_CODE" | tee -a "$LOGFILE"
        return 1
    fi

    # Verify the SQL dump file was created and has content
    if [ ! -f "$TEMP_SQL" ] || [ ! -s "$TEMP_SQL" ]; then
        log_message "ERROR: Database dump file was not created or is empty: $TEMP_SQL" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        return 1
    fi

    # Compress the SQL file using gzip
    log_message "Compressing dump..." | tee -a "$LOGFILE"
    gzip "$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"
    GZIP_EXIT_CODE=$?

    if [ $GZIP_EXIT_CODE -ne 0 ]; then
        log_message "ERROR: Database compression failed with exit code: $GZIP_EXIT_CODE" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        return 1
    fi

    # Verify the compressed file was created
    if [ ! -f "$TEMP_GZIP" ] || [ ! -s "$TEMP_GZIP" ]; then
        log_message "ERROR: Compressed database file was not created or is empty: $TEMP_GZIP" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        return 1
    fi

    # Upload compressed file to S3
    log_message "Uploading to S3..." | tee -a "$LOGFILE"

    S3_DB_PATH="s3://${BUCKET_NAME}/${AUTO_DB_BACKUP_PATH}/${DB_BACKUP_TAG}.sql.gz"
    log_message "Target: $S3_DB_PATH" | tee -a "$LOGFILE"

    aws s3 cp "$TEMP_GZIP" "$S3_DB_PATH" --only-show-errors 2>&1 | tee -a "$LOGFILE"
    UPLOAD_EXIT_CODE=$?

    # Clean up temporary files
    rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null

    if [ $UPLOAD_EXIT_CODE -eq 0 ]; then
        log_message "Database backup completed successfully: $S3_DB_PATH" | tee -a "$LOGFILE"
        print_status $GREEN "✓ Database backup created: $DB_BACKUP_TAG"

        # Upload log to S3
        if [ -f "$LOGFILE" ]; then
            aws s3 cp "$LOGFILE" "s3://$BUCKET_NAME/db-backup-logs/$(basename "$LOGFILE")" $S3_EXTRA_PARAMS >/dev/null 2>&1
        fi

        return 0
    else
        log_message "ERROR: Database backup upload failed with exit code: $UPLOAD_EXIT_CODE" | tee -a "$LOGFILE"
        print_status $RED "✗ Database backup failed: $DB_BACKUP_TAG"
        return 1
    fi
}

# Create static site backup
create_static_backup() {
    setup_s3_vars

    if [ "$ENABLE_STATIC_AUTO_BACKUPS" != "true" ]; then
        log_message "Static site backups disabled"
        return 0
    fi

    # Generate backup tag
    TIMESTAMP=$(date +"%Y_%m_%d_%H_%M_%S")
    CONTAINER_TAG=$(get_container_tag)
    BACKUP_TAG="${BACKUP_PREFIX}-${APP_SPACE}-${CONTAINER_TAG}-${TIMESTAMP}"

    log_message "Creating static site backup: $BACKUP_TAG"

    if aws s3 cp --only-show-errors s3://$BUCKET_NAME/web/ s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS $BACKUP_S3_EXTRA_PARAMS; then
        print_status $GREEN "✓ Static site backup created: $BACKUP_TAG"
        return 0
    else
        print_status $RED "✗ Static site backup failed: $BACKUP_TAG"
        return 1
    fi
}

# Create public files backup with smart detection
create_public_backup() {
    setup_s3_vars

    if [ "$ENABLE_PUBLIC_AUTO_BACKUPS" != "true" ]; then
        log_message "Public files backups disabled"
        return 0
    fi

    # Generate backup tag
    TIMESTAMP=$(date +"%Y_%m_%d_%H_%M_%S")
    CONTAINER_TAG=$(get_container_tag)
    BACKUP_TAG="${BACKUP_PREFIX}-${APP_SPACE}-${CONTAINER_TAG}-${TIMESTAMP}"

    # Smart backup check if enabled
    PUBLIC_BACKUP_NEEDED=true

    if [ "$ENABLE_SMART_PUBLIC_BACKUP" = "true" ]; then
        log_message "Checking if public files backup needed..."

        # Find the most recent automatic public files backup
        LATEST_PUBLIC_BACKUP=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "${BACKUP_PREFIX}-${APP_SPACE}-" | sort -r | head -n1 | awk '{print $2}' | tr -d '/')

        if [ -n "$LATEST_PUBLIC_BACKUP" ]; then
            log_message "Comparing with latest backup: $LATEST_PUBLIC_BACKUP"

            # Get checksums of current public files and latest backup
            CURRENT_PUBLIC_CHECKSUM=$(aws s3 ls --recursive s3://$BUCKET_NAME/cms/public/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5sum | awk '{print $1}' 2>/dev/null || aws s3 ls --recursive s3://$BUCKET_NAME/cms/public/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5 2>/dev/null)
            BACKUP_PUBLIC_CHECKSUM=$(aws s3 ls --recursive s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$LATEST_PUBLIC_BACKUP/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5sum | awk '{print $1}' 2>/dev/null || aws s3 ls --recursive s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$LATEST_PUBLIC_BACKUP/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5 2>/dev/null)

            if [ -n "$CURRENT_PUBLIC_CHECKSUM" ] && [ -n "$BACKUP_PUBLIC_CHECKSUM" ] && [ "$CURRENT_PUBLIC_CHECKSUM" = "$BACKUP_PUBLIC_CHECKSUM" ]; then
                log_message "Public files unchanged, skipping backup"
                PUBLIC_BACKUP_NEEDED=false
            fi
        fi
    fi

    if [ "$PUBLIC_BACKUP_NEEDED" = "true" ]; then
        log_message "Creating public files backup: $BACKUP_TAG"
        if aws s3 cp --only-show-errors s3://$BUCKET_NAME/cms/public/ s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS $BACKUP_S3_EXTRA_PARAMS; then
            print_status $GREEN "✓ Public files backup created: $BACKUP_TAG"
            return 0
        else
            print_status $RED "✗ Public files backup failed: $BACKUP_TAG"
            return 1
        fi
    else
        print_status $YELLOW "⚠ Public files backup skipped (no changes)"
        return 0
    fi
}

# Create all backups
backup_all() {
    print_status $BLUE "Creating all automatic backups..."

    success_count=0
    total_count=0

    # Create static backup
    if [ "$ENABLE_STATIC_AUTO_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_static_backup; then
            success_count=$((success_count + 1))
        fi
    fi

    # Create public backup
    if [ "$ENABLE_PUBLIC_AUTO_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_public_backup; then
            success_count=$((success_count + 1))
        fi
    fi

    # Create database backup
    if [ "$ENABLE_DB_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_db_backup; then
            success_count=$((success_count + 1))
        fi
    fi

    if [ $success_count -eq $total_count ]; then
        print_status $GREEN "✓ All backups completed successfully ($success_count/$total_count)"
        # Run cleanup
        cleanup_all_old_backups
    else
        print_status $RED "✗ Some backups failed ($success_count/$total_count succeeded)"
        return 1
    fi
}

# ===================================================================
# EXISTING FUNCTIONS FROM TOME-BACKUP-MANAGER.SH
# ===================================================================

list_backups() {
    local types_arg="${1:-all}"
    local days_arg="${2:-}"

    local backup_types=$(parse_backup_types "$types_arg")

    # If no specific types requested, show all backups with restore tags
    if [ "$types_arg" = "all" ] || [ -z "$types_arg" ]; then
        list_all_backups
        return 0
    fi

    # Show specific backup types
    if has_backup_type "$backup_types" "static"; then
        list_static_backups
        echo
    fi

    if has_backup_type "$backup_types" "public"; then
        list_public_backups
        echo
    fi

    if has_backup_type "$backup_types" "db"; then
        list_db_backups
        echo
    fi
}

list_static_backups() {
    setup_s3_vars

    print_status $GREEN "Static Site Backups:"
    echo "===================="
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r
}

list_public_backups() {
    setup_s3_vars

    print_status $GREEN "Public Files Backups:"
    echo "====================="
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r
}

list_db_backups() {
    setup_s3_vars

    print_status $GREEN "Database Backups:"
    echo "=================="

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$DB_BACKUP_PREFIX" | while read -r line; do
            # Extract backup name from S3 listing
            backup_file=$(echo "$line" | awk '{print $4}' | xargs basename)
            backup_size=$(echo "$line" | awk '{print $3}')
            backup_date=$(echo "$line" | awk '{print $1" "$2}')

            echo "  $backup_file ($backup_size bytes) - $backup_date"
        done
    else
        print_status $RED "Error: AWS credentials not available"
    fi
}

list_all_backups() {
    setup_s3_vars

    print_status $BLUE "Backups by Restore Tag"
    echo ""

    # Create temporary files to collect backup data
    static_list="/tmp/static_backups_$$"
    public_list="/tmp/public_backups_$$"
    db_list="/tmp/db_backups_$$"

    # Get all backup lists
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | awk '{print $2}' | tr -d '/' | sort -r > "$static_list" 2>/dev/null
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | awk '{print $2}' | tr -d '/' | sort -r > "$public_list" 2>/dev/null
    aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$DB_BACKUP_PREFIX" | awk '{print $4}' | xargs -I {} basename {} | sort -r > "$db_list" 2>/dev/null

    # Create unified list of all backup tags (timestamps)
    all_tags="/tmp/all_backup_tags_$$"
    (
        cat "$static_list" 2>/dev/null
        cat "$public_list" 2>/dev/null
        cat "$db_list" 2>/dev/null | sed 's/\.sql\.gz$//'
    ) | sort -ru > "$all_tags"

    printf "%-32s %-8s %-8s %-8s %s\n" "BACKUP TAG" "STATIC" "PUBLIC" "DATABASE" "RESTORE COMMAND"
    printf "%-32s %-8s %-8s %-8s %s\n" "$(printf '%*s' 32 '' | tr ' ' '-')" "$(printf '%*s' 8 '' | tr ' ' '-')" "$(printf '%*s' 8 '' | tr ' ' '-')" "$(printf '%*s' 8 '' | tr ' ' '-')" "$(printf '%*s' 20 '' | tr ' ' '-')"

    while read -r tag; do
        if [ -n "$tag" ]; then
            # Check what backup types exist for this tag
            has_static=""
            has_public=""
            has_database=""

            # Check static backup
            if grep -q "^$tag$" "$static_list" 2>/dev/null; then
                has_static="✓"
            else
                has_static="✗"
            fi

            # Check public backup
            if grep -q "^$tag$" "$public_list" 2>/dev/null; then
                has_public="✓"
            else
                has_public="✗"
            fi

            # Check database backup
            db_tag="${tag}.sql.gz"
            if grep -q "^$db_tag$" "$db_list" 2>/dev/null; then
                has_database="✓"
            else
                has_database="✗"
            fi

            # Format restore command
            restore_cmd="restore $tag"

            printf "%-32s %-8s %-8s %-8s %s\n" "$tag" "$has_static" "$has_public" "$has_database" "$restore_cmd"
        fi
    done < "$all_tags"

    # Clean up temporary files
    rm -f "$static_list" "$public_list" "$db_list" "$all_tags" 2>/dev/null

    echo ""
    print_status $YELLOW "✓ = Available    ✗ = Missing (smart fallback may apply)"
}

# Function to cleanup old database backups
cleanup_old_db_backups() {
    local days=${1:-$DB_BACKUP_RETENTION_DAYS}

    if [ "$ENABLE_DB_AUTO_CLEANUP" != "true" ]; then
        log_message "Database automatic cleanup is disabled"
        return 0
    fi

    setup_s3_vars
    log_message "Cleaning up database backups older than $days days..."

    # Calculate cutoff date
    CUTOFF_DATE=$(date -u -d "${days} days ago" '+%Y_%m_%d' 2>/dev/null || date -u -v-${days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -n "$CUTOFF_DATE" ]; then
        # List and delete old database backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | while read -r line; do
            backup_path=$(echo "$line" | awk '{print $4}')
            backup_date=$(echo "$backup_path" | grep -o "${DB_BACKUP_PREFIX}-[^-]*-[^-]*-[0-9_]*" | sed 's/.*-\([0-9_]*\).*/\1/' | cut -c 1-10)
            if [ -n "$backup_date" ] && [ "$backup_date" \< "$CUTOFF_DATE" ]; then
                log_message "Removing old database backup: $backup_path"
                aws s3 rm "s3://$BUCKET_NAME/$backup_path" $S3_EXTRA_PARAMS 2>&1
            fi
        done
        log_message "Database backup cleanup completed"
    else
        log_message "WARNING: Could not determine cutoff date for database backup cleanup"
    fi
}

# Function to list old backups
list_old_backups() {
    local days=${1:-7}
    setup_s3_vars

    local cutoff_date=$(date -u -d "${days} days ago" '+%Y_%m_%d' 2>/dev/null || date -u -v-${days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -z "$cutoff_date" ]; then
        print_status $RED "Error: Date calculation not supported on this system"
        print_status $YELLOW "Use 'list' command and manual cleanup required."
        exit 1
    fi

    print_status $YELLOW "Backups older than ${days} days (before ${cutoff_date}):"
    echo "========================================================"

    print_status $GREEN "Static Site Backups:"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            echo "$line"
        fi
    done

    echo ""
    print_status $GREEN "Public Files Backups:"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            echo "$line"
        fi
    done
}

# Function to clean old backups
clean_old_backups() {
    local days=${1:-7}
    setup_s3_vars

    local cutoff_date=$(date -u -d "${days} days ago" '+%Y_%m_%d' 2>/dev/null || date -u -v-${days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -z "$cutoff_date" ]; then
        print_status $RED "Error: Date calculation not supported on this system"
        print_status $YELLOW "Use 'list' command for manual cleanup with AWS CLI."
        exit 1
    fi

    print_status $YELLOW "Removing static/public backups older than ${days} days (before ${cutoff_date})..."

    # Clean static site backups
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            print_status $YELLOW "Removing static site backup: $backup_name"
            aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
        fi
    done

    # Clean public files backups
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            print_status $YELLOW "Removing public files backup: $backup_name"
            aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
        fi
    done

    print_status $GREEN "Static/public backup cleanup completed."
}

# Clean all backup types
cleanup_all_old_backups() {
    local static_days=${1:-$BACKUP_RETENTION_DAYS}
    local db_days=${2:-$DB_BACKUP_RETENTION_DAYS}

    print_status $BLUE "Cleaning up all old automatic backups..."

    clean_old_backups $static_days
    cleanup_old_db_backups $db_days

    print_status $GREEN "All backup cleanup completed."
}

# Find corresponding public backup for smart restore
find_corresponding_public_backup() {
    local static_backup_tag=$1

    # First, check if there's an exact match
    if aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$static_backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "$static_backup_tag"
        return 0
    fi

    # If no exact match, find the most recent public backup before or at the static backup time
    # Extract timestamp from static backup tag (format: AUTO-space-containertag-YYYY_MM_DD_HH_MM_SS)
    static_timestamp=$(echo "$static_backup_tag" | grep -o '[0-9_]*$')

    if [ -z "$static_timestamp" ]; then
        return 1
    fi

    # Use a temp file to avoid subshell variable issues
    temp_list="/tmp/public_backup_search_$$"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "${BACKUP_PREFIX}-" > "$temp_list" 2>/dev/null

    best_public_backup=""
    best_timestamp=""

    while read -r line; do
        if [ -n "$line" ]; then
            public_backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            public_timestamp=$(echo "$public_backup_name" | grep -o '[0-9_]*$')

            # Compare timestamps (lexicographic comparison works for YYYY_MM_DD_HH_MM_SS format)
            if [ -n "$public_timestamp" ] && [ "$public_timestamp" \< "$static_timestamp" ] || [ "$public_timestamp" = "$static_timestamp" ]; then
                # This public backup is at or before the static backup time
                if [ -z "$best_timestamp" ] || [ "$public_timestamp" \> "$best_timestamp" ]; then
                    best_public_backup="$public_backup_name"
                    best_timestamp="$public_timestamp"
                fi
            fi
        fi
    done < "$temp_list"

    # Clean up temp file
    rm -f "$temp_list" 2>/dev/null

    if [ -n "$best_public_backup" ]; then
        echo "$best_public_backup"
        return 0
    else
        return 1
    fi
}

# Find corresponding database backup for smart restore
find_corresponding_db_backup() {
    local static_backup_tag=$1

    # Extract timestamp from static backup tag (format: AUTO-space-containertag-YYYY_MM_DD_HH_MM_SS)
    static_timestamp=$(echo "$static_backup_tag" | grep -o '[0-9_]*$')

    if [ -z "$static_timestamp" ]; then
        return 1
    fi

    # First, check if there's an exact match
    exact_db_tag="${static_backup_tag}.sql.gz"
    if aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$exact_db_tag $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "$exact_db_tag"
        return 0
    fi

    # Use a temp file to avoid subshell variable issues
    temp_list="/tmp/db_backup_search_$$"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$DB_BACKUP_PREFIX" | awk '{print $4}' | xargs -I {} basename {} > "$temp_list" 2>/dev/null

    best_db_backup=""
    best_timestamp=""

    while read -r line; do
        if [ -n "$line" ]; then
            # Extract timestamp from database backup name (AUTO-env-containertag-YYYY_MM_DD_HH_MM_SS.sql.gz)
            db_timestamp=$(echo "$line" | sed "s/^$DB_BACKUP_PREFIX-[^-]*-[^-]*-//" | sed 's/\.sql\.gz$//')

            # Compare timestamps (lexicographic comparison works for YYYY_MM_DD_HH_MM_SS format)
            if [ -n "$db_timestamp" ] && [ "$db_timestamp" \< "$static_timestamp" ] || [ "$db_timestamp" = "$static_timestamp" ]; then
                # This database backup is at or before the static backup time
                if [ -z "$best_timestamp" ] || [ "$db_timestamp" \> "$best_timestamp" ]; then
                    best_db_backup="$line"
                    best_timestamp="$db_timestamp"
                fi
            fi
        fi
    done < "$temp_list"

    # Clean up temp file
    rm -f "$temp_list" 2>/dev/null

    if [ -n "$best_db_backup" ]; then
        echo "$best_db_backup"
        return 0
    else
        return 1
    fi
}

# Parse restore options (same as before)
parse_restore_options() {
    local restore_types="static,public,database"  # default: restore all

    while [ $# -gt 0 ]; do
        case "$1" in
            --only=*)
                restore_types="${1#--only=}"
                shift
                ;;
            --only)
                if [ -n "$2" ] && [ "${2#-}" = "$2" ]; then
                    restore_types="$2"
                    shift 2
                else
                    print_status $RED "Error: --only requires a value (e.g., --only=static,public)"
                    exit 1
                fi
                ;;
            *)
                # This should be the backup tag
                echo "$1"
                shift
                break
                ;;
        esac
    done

    # Return the restore types for the caller to use
    echo "$restore_types" >&2
}

# Unified restore function (same as before but integrated)
restore_backup() {
    local backup_tag=""
    local restore_types=""

    # Parse arguments
    if [ $# -eq 0 ]; then
        print_status $RED "Error: Backup tag is required"
        print_status $YELLOW "Usage: restore <backup_tag> [--only=static,public,database]"
        exit 1
    fi

    # Parse options and get backup tag
    restore_types=$(parse_restore_options "$@" 2>&1 >/dev/null | tail -n1)
    backup_tag=$(parse_restore_options "$@" 2>/dev/null | head -n1)

    if [ -z "$backup_tag" ]; then
        print_status $RED "Error: Backup tag is required"
        exit 1
    fi

    setup_s3_vars

    # Determine what to restore
    restore_static=$(echo "$restore_types" | grep -q "static" && echo "yes" || echo "no")
    restore_public=$(echo "$restore_types" | grep -q "public" && echo "yes" || echo "no")
    restore_database=$(echo "$restore_types" | grep -q "database" && echo "yes" || echo "no")

    print_status $BLUE "Restore Analysis"
    echo ""

    # Find appropriate backups for each type
    static_backup_tag=""
    public_backup_tag=""
    db_backup_tag=""

    # Static site backup analysis
    if [ "$restore_static" = "yes" ]; then
        if aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
            static_backup_tag="$backup_tag"
            print_status $GREEN "✓ Static site backup found: $static_backup_tag"
        else
            print_status $RED "✗ Static site backup not found: $backup_tag"
            exit 1
        fi
    fi

    # Public files backup analysis
    if [ "$restore_public" = "yes" ]; then
        print_status $BLUE "Analyzing public files backup options..."
        public_backup_tag=$(find_corresponding_public_backup "$backup_tag")

        if [ -n "$public_backup_tag" ]; then
            if [ "$public_backup_tag" = "$backup_tag" ]; then
                print_status $GREEN "✓ Exact public backup match: $public_backup_tag"
            else
                print_status $YELLOW "⚠ No exact public backup match found"
                print_status $GREEN "✓ Smart fallback public backup: $public_backup_tag"
            fi
        else
            print_status $YELLOW "⚠ No suitable public backup found for time period"
            print_status $YELLOW "  Public files will remain unchanged"
        fi
    fi

    # Database backup analysis
    if [ "$restore_database" = "yes" ]; then
        print_status $BLUE "Analyzing database backup options..."
        db_backup_tag=$(find_corresponding_db_backup "$backup_tag")

        if [ -n "$db_backup_tag" ]; then
            # Convert to expected database tag format for comparison
            expected_db_tag="${backup_tag}.sql.gz"
            if [ "$db_backup_tag" = "$expected_db_tag" ]; then
                print_status $GREEN "✓ Exact database backup match: $db_backup_tag"
            else
                print_status $YELLOW "⚠ No exact database backup match found"
                print_status $GREEN "✓ Smart fallback database backup: $db_backup_tag"
            fi
        else
            print_status $YELLOW "⚠ No suitable database backup found for time period"
            print_status $YELLOW "  Database will remain unchanged"
        fi
    fi

    echo ""
    print_status $YELLOW "RESTORE PLAN SUMMARY"
    print_status $YELLOW "===================="

    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        echo "Static Site:   $static_backup_tag"
    fi
    if [ "$restore_public" = "yes" ]; then
        if [ -n "$public_backup_tag" ]; then
            echo "Public Files:  $public_backup_tag"
        else
            echo "Public Files:  SKIP (no backup found)"
        fi
    fi
    if [ "$restore_database" = "yes" ]; then
        if [ -n "$db_backup_tag" ]; then
            echo "Database:      $db_backup_tag"
        else
            echo "Database:      SKIP (no backup found)"
        fi
    fi

    echo ""
    print_status $RED "WARNING: This will overwrite current data!"
    print_status $YELLOW "Are you sure you want to proceed with this restore? (y/N)"
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "Restore cancelled."
        exit 0
    fi

    echo ""
    print_status $BLUE "EXECUTING RESTORE"
    print_status $BLUE "================="

    # Restore static site
    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        print_status $YELLOW "Restoring static site from: $static_backup_tag"
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$static_backup_tag/ s3://$BUCKET_NAME/web/ --delete $S3_EXTRA_PARAMS; then
            print_status $GREEN "✓ Static site restore completed successfully"
        else
            print_status $RED "✗ ERROR: Static site restore failed"
            exit 1
        fi
    fi

    # Restore public files
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        print_status $YELLOW "Restoring public files from: $public_backup_tag"
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$public_backup_tag/ s3://$BUCKET_NAME/cms/public/ --delete $S3_EXTRA_PARAMS; then
            print_status $GREEN "✓ Public files restore completed successfully"
        else
            print_status $RED "✗ ERROR: Public files restore failed"
            exit 1
        fi
    fi

    # Restore database
    if [ "$restore_database" = "yes" ] && [ -n "$db_backup_tag" ]; then
        print_status $YELLOW "Restoring database from: $db_backup_tag"

        # Download and restore database backup
        temp_db_file="/tmp/restore_db_$$.sql.gz"
        temp_sql_file="/tmp/restore_db_$$.sql"

        print_status $BLUE "Downloading database backup..."
        if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_backup_tag "$temp_db_file" $S3_EXTRA_PARAMS; then
            print_status $BLUE "Decompressing database backup..."
            if gunzip "$temp_db_file" 2>/dev/null; then
                print_status $BLUE "Importing database..."
                if command -v drush >/dev/null 2>&1; then
                    # Use drush for database import
                    if drush sql:drop -y && drush sql:cli < "$temp_sql_file"; then
                        print_status $GREEN "✓ Database restore completed successfully"
                    else
                        print_status $RED "✗ ERROR: Database import failed"
                        rm -f "$temp_sql_file" 2>/dev/null
                        exit 1
                    fi
                else
                    print_status $RED "✗ ERROR: drush command not available for database restore"
                    rm -f "$temp_sql_file" 2>/dev/null
                    exit 1
                fi
            else
                print_status $RED "✗ ERROR: Failed to decompress database backup"
                rm -f "$temp_db_file" 2>/dev/null
                exit 1
            fi
        else
            print_status $RED "✗ ERROR: Failed to download database backup"
            exit 1
        fi

        # Clean up temp files
        rm -f "$temp_sql_file" 2>/dev/null
    fi

    echo ""
    print_status $GREEN "🎉 UNIFIED RESTORE COMPLETED SUCCESSFULLY!"

    # Summary of what was restored
    restored_items=""
    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        restored_items="${restored_items}static site, "
    fi
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        restored_items="${restored_items}public files, "
    fi
    if [ "$restore_database" = "yes" ] && [ -n "$db_backup_tag" ]; then
        restored_items="${restored_items}database, "
    fi

    # Remove trailing comma and space
    restored_items=$(echo "$restored_items" | sed 's/, $//')

    if [ -n "$restored_items" ]; then
        print_status $GREEN "Restored: $restored_items"
    fi
}

# Get backup info (existing function)
backup_info() {
    local backup_tag=$1
    if [ -z "$backup_tag" ]; then
        print_status $RED "Error: Backup tag is required"
        exit 1
    fi

    setup_s3_vars

    print_status $GREEN "Backup Information for: $backup_tag"
    echo "======================================"

    # Check static site backup
    echo "Static Site Backup:"
    if aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null; then
        static_exists="yes"
    else
        echo "  No static site backup found with this tag"
        static_exists="no"
    fi

    echo ""
    echo "Public Files Backup:"
    if aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null; then
        public_exists="yes"
    else
        echo "  No public files backup found with this tag"
        public_exists="no"

        # If static exists but public doesn't, show the smart relationship
        if [ "$static_exists" = "yes" ]; then
            echo ""
            print_status $YELLOW "Smart Backup Analysis:"
            echo "======================="
            echo "This static site backup has no corresponding public files backup."
            echo "This is normal when public files were unchanged (smart optimization)."
            echo ""

            corresponding_public=$(find_corresponding_public_backup "$backup_tag")
            if [ -n "$corresponding_public" ]; then
                if [ "$corresponding_public" != "$backup_tag" ]; then
                    print_status $GREEN "Restore would use public backup: $corresponding_public"
                    echo "Public Files Backup (would be used for restore):"
                    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$corresponding_public/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null
                fi
            else
                print_status $YELLOW "No suitable public backup found for this time period."
            fi
        fi
    fi
}

# Show database backup information
db_backup_info() {
    backup_name="$1"

    if [ -z "$backup_name" ]; then
        print_status $RED "Error: Backup name required"
        show_usage
        exit 1
    fi

    setup_s3_vars

    print_status $GREEN "Database Backup Information:"
    echo "============================"
    echo "Backup Name: $backup_name"

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        # Look for the backup file
        backup_info=$(aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$backup_name")

        if [ -n "$backup_info" ]; then
            backup_size=$(echo "$backup_info" | awk '{print $3}')
            backup_date=$(echo "$backup_info" | awk '{print $1" "$2}')
            backup_file=$(echo "$backup_info" | awk '{print $4}')

            echo "File Path: s3://$BUCKET_NAME/$backup_file"
            echo "Size: $backup_size bytes"
            echo "Created: $backup_date"

            # Extract date from backup name if possible
            timestamp_part=$(echo "$backup_name" | sed "s/.*$DB_BACKUP_PREFIX-[^-]*-//")
            if [ -n "$timestamp_part" ]; then
                formatted_date=$(echo "$timestamp_part" | sed 's/_/-/g' | sed 's/-/ /' | sed 's/-/ /' | sed 's/-/:/' | sed 's/-/:/')
                echo "Backup Timestamp: $formatted_date"
            fi
        else
            print_status $RED "Error: Database backup '$backup_name' not found"
            exit 1
        fi
    else
        print_status $RED "Error: AWS credentials not available"
        exit 1
    fi
}

# Main script logic
case "${1:-}" in
    "list")
        # list [types] [days] - e.g., "list static,db" or "list all 7"
        list_backups "$2" "$3"
        ;;
    "backup")
        # backup [types] - e.g., "backup db" or "backup static,public"
        run_backup_command "$2"
        ;;
    "clean")
        # clean [types] [days] - e.g., "clean all 30" or "clean db 7"
        run_clean_command "$2" "$3"
        ;;
    "restore")
        # restore (unchanged - interactive restore)
        shift  # Remove the 'restore' command
        restore_backup "$@"  # Pass all remaining arguments
        ;;
    "info")
        # info [types] <tag> - e.g., "info db" or "info all backup-tag"
        run_info_command "$2" "$3"
        ;;
    # Legacy commands for backward compatibility
    "list-static")
        list_static_backups
        ;;
    "list-public")
        list_public_backups
        ;;
    "list-db")
        list_db_backups
        ;;
    "backup-db")
        create_db_backup
        ;;
    "backup-all")
        run_backup_command "all"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac