#!/bin/sh

# Tome Backup Manager
# Provides utilities for managing automatic backups created by tome-sync.sh

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

# Function to show usage
show_usage() {
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Static Site & Public Files Commands:"
    echo "  list                     List all automatic backups"
    echo "  list-old [days]          List backups older than N days (default: 7)"
    echo "  clean [days]             Remove backups older than N days (default: 7)"
    echo "  restore <backup_tag>     Restore from a specific backup"
    echo "  info <backup_tag>        Show information about a specific backup"
    echo ""
    echo "Database Commands:"
    echo "  list-db                  List all database backups"
    echo "  list-db-old [days]       List database backups older than N days (default: 30)"
    echo "  clean-db [days]          Remove database backups older than N days (default: 30)"
    echo "  backup-db                Create an immediate database backup"
    echo "  info-db <backup_tag>     Show information about a database backup"
    echo ""
    echo "Examples:"
    echo "  $0 list"
    echo "  $0 list-db"
    echo "  $0 clean 30"
    echo "  $0 clean-db 60"
    echo "  $0 backup-db"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00"
    echo "  $0 info-db DB-AUTO-2024_03_15_19_00_00"
}

# Function to get S3 credentials (simplified from tome-sync.sh)
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

# Function to list all automatic backups
list_backups() {
    setup_s3_vars

    print_status $GREEN "Static Site Backups:"
    echo "===================="
    aws s3 ls s3://$BUCKET_NAME/web-backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r

    echo ""
    print_status $GREEN "Public Files Backups:"
    echo "====================="
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r

    echo ""
    print_status $YELLOW "Note: Some static site backups may not have corresponding public file backups"
    print_status $YELLOW "if the public files were unchanged (smart backup optimization)."
}

# Function to list old backups
list_old_backups() {
    local days=${1:-7}
    setup_s3_vars

    local cutoff_date=$(date -u -d "${days} days ago" '+%Y_%m_%d' 2>/dev/null || date -u -v-${days}d '+%Y_%m_%d' 2>/dev/null)

    if [ -z "$cutoff_date" ]; then
        print_status $RED "Error: Could not calculate cutoff date - advanced date calculations not supported"
        print_status $YELLOW "This environment's date command doesn't support relative date calculations."
        print_status $YELLOW "Manual backup management will be required."
        exit 1
    fi

    print_status $YELLOW "Backups older than ${days} days (before ${cutoff_date}):"
    echo "========================================================"

    print_status $GREEN "Static Site Backups:"
    aws s3 ls s3://$BUCKET_NAME/web-backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            echo "$line"
        fi
    done

    echo ""
    print_status $GREEN "Public Files Backups:"
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
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
        print_status $RED "Error: Could not calculate cutoff date - advanced date calculations not supported"
        print_status $YELLOW "This environment's date command doesn't support relative date calculations."
        print_status $YELLOW "Use 'list' command to see backups and remove them manually with AWS CLI."
        exit 1
    fi

    print_status $YELLOW "Removing backups older than ${days} days (before ${cutoff_date})..."

    # Clean static site backups
    aws s3 ls s3://$BUCKET_NAME/web-backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            print_status $YELLOW "Removing static site backup: $backup_name"
            aws s3 rm s3://$BUCKET_NAME/web-backup/$backup_name/ --recursive $S3_EXTRA_PARAMS
        fi
    done

    # Clean public files backups
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(echo "$backup_name" | grep -o '[0-9_]*$' | head -c 10)
        if [ -n "$backup_date" ] && [ "$backup_date" \< "$cutoff_date" ]; then
            print_status $YELLOW "Removing public files backup: $backup_name"
            aws s3 rm s3://$BUCKET_NAME/public_backup/$backup_name/ --recursive $S3_EXTRA_PARAMS
        fi
    done

    print_status $GREEN "Cleanup completed."
}

# Function to get backup info
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
    aws s3 ls s3://$BUCKET_NAME/web-backup/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize

    echo ""
    echo "Public Files Backup:"
    aws s3 ls s3://$BUCKET_NAME/public_backup/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize
}

# Function to restore from backup (WARNING: This is destructive)
restore_backup() {
    local backup_tag=$1
    if [ -z "$backup_tag" ]; then
        print_status $RED "Error: Backup tag is required"
        exit 1
    fi

    setup_s3_vars

    print_status $YELLOW "WARNING: This will overwrite current static site and public files!"
    print_status $YELLOW "Are you sure you want to restore from backup: $backup_tag? (y/N)"
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "Restore cancelled."
        exit 0
    fi

    print_status $YELLOW "Restoring static site from backup: $backup_tag"
    aws s3 sync s3://$BUCKET_NAME/web-backup/$backup_tag/ s3://$BUCKET_NAME/web/ --delete $S3_EXTRA_PARAMS

    print_status $YELLOW "Restoring public files from backup: $backup_tag"
    aws s3 sync s3://$BUCKET_NAME/public_backup/$backup_tag/ s3://$BUCKET_NAME/cms/public/ --delete $S3_EXTRA_PARAMS

    print_status $GREEN "Restore completed."
}

# List database backups
list_db_backups() {
    setup_s3_vars

    echo "Database backups:"
    echo "=================="

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive | grep "$DB_BACKUP_PREFIX" | while read -r line; do
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

# List database backups older than specified days
list_old_db_backups() {
    days="${1:-30}"

    setup_s3_vars

    # Get current timestamp
    current_time=$(date +%s)

    # Calculate cutoff timestamp (days ago)
    cutoff_time=$((current_time - days * 86400))

    echo "Listing database backups older than $days days:"
    echo "=============================================="

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive | grep "$DB_BACKUP_PREFIX" | while read -r line; do
            backup_file=$(echo "$line" | awk '{print $4}' | xargs basename)

            # Extract date from backup name (YYYY_MM_DD_HH_MM_SS format)
            timestamp_part=$(echo "$backup_file" | sed "s/.*$DB_BACKUP_PREFIX-[^-]*-//")
            if [ -n "$timestamp_part" ]; then
                # Convert to epoch time for comparison
                date_str=$(echo "$timestamp_part" | sed 's/_/ /g' | sed 's/ /:/' | sed 's/ /:/' | sed 's/ / /')
                backup_time=$(date -j -f "%Y %m %d %H:%M:%S" "$date_str" +%s 2>/dev/null || echo "0")

                if [ "$backup_time" -gt 0 ] && [ "$backup_time" -lt "$cutoff_time" ]; then
                    backup_size=$(echo "$line" | awk '{print $3}')
                    backup_date=$(echo "$line" | awk '{print $1" "$2}')
                    echo "  $backup_file ($backup_size bytes) - $backup_date"
                fi
            fi
        done
    else
        print_status $RED "Error: AWS credentials not available"
    fi
}

# Clean old database backups
clean_old_db_backups() {
    days="${1:-30}"

    setup_s3_vars

    # Get current timestamp
    current_time=$(date +%s)

    # Calculate cutoff timestamp (days ago)
    cutoff_time=$((current_time - days * 86400))

    print_status $YELLOW "Cleaning database backups older than $days days..."

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        count=0
        aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive | grep "$DB_BACKUP_PREFIX" | while read -r line; do
            backup_file=$(echo "$line" | awk '{print $4}')
            backup_name=$(echo "$backup_file" | xargs basename)

            # Extract date from backup name (YYYY_MM_DD_HH_MM_SS format)
            timestamp_part=$(echo "$backup_name" | sed "s/.*$DB_BACKUP_PREFIX-[^-]*-//")
            if [ -n "$timestamp_part" ]; then
                # Convert to epoch time for comparison
                date_str=$(echo "$timestamp_part" | sed 's/_/ /g' | sed 's/ /:/' | sed 's/ /:/' | sed 's/ / /')
                backup_time=$(date -j -f "%Y %m %d %H:%M:%S" "$date_str" +%s 2>/dev/null || echo "0")

                if [ "$backup_time" -gt 0 ] && [ "$backup_time" -lt "$cutoff_time" ]; then
                    print_status $YELLOW "Deleting old database backup: $backup_name"
                    aws s3 rm s3://"$BUCKET_NAME"/"$backup_file" $S3_EXTRA_PARAMS
                    count=$((count + 1))
                fi
            fi
        done

        print_status $GREEN "Database backup cleanup completed. Processed $count old backups."
    else
        print_status $RED "Error: AWS credentials not available"
    fi
}

# Create immediate database backup
backup_db() {
    setup_s3_vars

    print_status $YELLOW "Creating immediate database backup..."

    # Use the daily backup script
    script_dir="$(dirname "$0")"
    if [ -f "$script_dir/db-backup-daily.sh" ]; then
        "$script_dir/db-backup-daily.sh"
    else
        print_status $RED "Error: Database backup script not found at $script_dir/db-backup-daily.sh"
        exit 1
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

    echo "Database Backup Information:"
    echo "============================"
    echo "Backup Name: $backup_name"

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        # Look for the backup file
        backup_info=$(aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive | grep "$backup_name")

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
        list_backups
        ;;
    "list-old")
        list_old_backups "${2:-7}"
        ;;
    "clean")
        clean_old_backups "${2:-7}"
        ;;
    "restore")
        restore_backup "$2"
        ;;
    "info")
        backup_info "$2"
        ;;
    "list-db")
        list_db_backups
        ;;
    "list-db-old")
        list_old_db_backups "${2:-30}"
        ;;
    "clean-db")
        clean_old_db_backups "${2:-30}"
        ;;
    "backup-db")
        backup_db
        ;;
    "info-db")
        db_backup_info "$2"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac