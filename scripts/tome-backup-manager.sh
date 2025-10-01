#!/bin/bash

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
    echo "Commands:"
    echo "  list                     List all automatic backups"
    echo "  list-old [days]          List backups older than N days (default: 7)"
    echo "  clean [days]             Remove backups older than N days (default: 7)"
    echo "  restore <backup_tag>     Restore from a specific backup"
    echo "  info <backup_tag>        Show information about a specific backup"
    echo ""
    echo "Examples:"
    echo "  $0 list"
    echo "  $0 list-old 14"
    echo "  $0 clean 30"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00"
    echo "  $0 info AUTO-prod-2024_03_15_14_30_00"
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
        print_status $RED "Error: Could not calculate cutoff date"
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
        print_status $RED "Error: Could not calculate cutoff date"
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
    *)
        show_usage
        exit 1
        ;;
esac