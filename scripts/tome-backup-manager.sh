#!/bin/sh

# Tome Backup Manager
# Provides utilities for managing automatic backups created by tome-sync.sh

SCRIPT_PATH=$(dirname "$0")

# Load configuration
CONFIG_FILE="$SCRIPT_PATH/auto-backup-system.conf"
if [ -f "$CONFIG_FILE" ]; then
    . "$CONFIG_FILE"
else
    echo "ERROR: Configuration file not found: $CONFIG_FILE"
    exit 1
fi

# Set defaults if not defined in config
BACKUP_PREFIX=${BACKUP_PREFIX:-AUTO}
DB_BACKUP_PREFIX=${DB_BACKUP_PREFIX:-DB-AUTO}
BACKUP_RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-7}
DB_BACKUP_RETENTION_DAYS=${DB_BACKUP_RETENTION_DAYS:-30}

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
    echo "Listing Commands:"
    echo "  list                     List ALL backups (static site, public files, and database)"
    echo "  list-static              List static site backups only"
    echo "  list-public              List public file backups only"
    echo "  list-db                  List database backups only"
    echo ""
    echo "Management Commands:"
    echo "  list-old [days]          List static/public backups older than N days (default: 7)"
    echo "  list-db-old [days]       List database backups older than N days (default: 30)"
    echo "  clean [days]             Remove static/public backups older than N days (default: 7)"
    echo "  clean-db [days]          Remove database backups older than N days (default: 30)"
    echo "  restore <backup_tag> [--only=types]  Unified restore (static+public+database)"
    echo "  backup-db                Create an immediate database backup"
    echo ""
    echo "Information Commands:"
    echo "  info <backup_tag>        Show information about a static/public backup"
    echo "  info-db <backup_tag>     Show information about a database backup"
    echo ""
    echo "Examples:"
    echo "  $0 list                  # Show all backup types organized by restore tag"
    echo "  $0 list-static           # Show only static site backups"
    echo "  $0 list-db               # Show only database backups"
    echo "  $0 clean 30              # Clean old static/public backups"
    echo "  $0 backup-db             # Create immediate database backup"
    echo ""
    echo "Unified Restore Examples:"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00                    # Restore all (static+public+database)"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00 --only=static     # Restore only static site"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00 --only=static,public  # Restore static + public"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00 --only=database   # Restore only database"
    echo "  $0 restore AUTO-dev-2024_03_15_14_30_00 --only=static,database # Restore static + database"
    echo ""
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
    # Now calls the all backups function to show everything
    list_all_backups
}

# Function to list static site backups only
list_static_backups() {
    setup_s3_vars

    print_status $GREEN "Static Site Backups:"
    echo "===================="
    aws s3 ls s3://$BUCKET_NAME/web-backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r
}

# Function to list public file backups only
list_public_backups() {
    setup_s3_vars

    print_status $GREEN "Public Files Backups:"
    echo "====================="
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | sort -r
}

# Function to list all backup types organized by restore tag
list_all_backups() {
    setup_s3_vars

    print_status $BLUE "BACKUPS ORGANIZED BY RESTORE TAG"
    print_status $BLUE "================================="
    echo ""

    # Create temporary files to collect backup data
    static_list="/tmp/static_backups_$$"
    public_list="/tmp/public_backups_$$"
    db_list="/tmp/db_backups_$$"

    # Get all backup lists
    aws s3 ls s3://$BUCKET_NAME/web-backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | awk '{print $2}' | tr -d '/' | sort -r > "$static_list" 2>/dev/null
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "AUTO-" | awk '{print $2}' | tr -d '/' | sort -r > "$public_list" 2>/dev/null
    aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive $S3_EXTRA_PARAMS | grep "$DB_BACKUP_PREFIX" | awk '{print $4}' | xargs -I {} basename {} | sort -r > "$db_list" 2>/dev/null

    # Create unified list of all backup tags (timestamps)
    all_tags="/tmp/all_backup_tags_$$"
    (
        cat "$static_list" 2>/dev/null
        cat "$public_list" 2>/dev/null
        cat "$db_list" 2>/dev/null | sed "s/^$DB_BACKUP_PREFIX-/$BACKUP_PREFIX-/" | sed 's/\.sql\.gz$//'
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

            # Check database backup (convert tag format)
            db_tag=$(echo "$tag" | sed "s/^$BACKUP_PREFIX-/$DB_BACKUP_PREFIX-/").sql.gz
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
    print_status $YELLOW "Legend:"
    print_status $YELLOW "  ✓ = Backup available    ✗ = No backup (may use smart fallback for public files)"
    print_status $YELLOW "  Database backups have independent timestamps from static/public backups"
    echo ""
    print_status $GREEN "Usage Examples:"
    echo "  ./scripts/tome-backup-manager.sh restore <BACKUP_TAG>    # Restore static + public files"
    echo "  ./scripts/tome-backup-manager.sh info <BACKUP_TAG>       # Show backup details"
    echo "  ./scripts/tome-backup-manager.sh info-db <DB_BACKUP>     # Show database backup details"
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
    if aws s3 ls s3://$BUCKET_NAME/web-backup/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null; then
        static_exists="yes"
    else
        echo "  No static site backup found with this tag"
        static_exists="no"
    fi

    echo ""
    echo "Public Files Backup:"
    if aws s3 ls s3://$BUCKET_NAME/public_backup/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null; then
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
                    aws s3 ls s3://$BUCKET_NAME/public_backup/$corresponding_public/ $S3_EXTRA_PARAMS --recursive --summarize 2>/dev/null
                fi
            else
                print_status $YELLOW "No suitable public backup found for this time period."
            fi
        fi
    fi
}

# Function to find the appropriate public backup for a static site backup
find_corresponding_public_backup() {
    local static_backup_tag=$1

    # First, check if there's an exact match
    if aws s3 ls s3://$BUCKET_NAME/public_backup/$static_backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "$static_backup_tag"
        return 0
    fi

    # If no exact match, find the most recent public backup before or at the static backup time
    # Extract timestamp from static backup tag (format: AUTO-space-YYYY_MM_DD_HH_MM_SS)
    static_timestamp=$(echo "$static_backup_tag" | grep -o '[0-9_]*$')

    if [ -z "$static_timestamp" ]; then
        return 1
    fi

    # Use a temp file to avoid subshell variable issues
    temp_list="/tmp/public_backup_search_$$"
    aws s3 ls s3://$BUCKET_NAME/public_backup/ $S3_EXTRA_PARAMS | grep "${BACKUP_PREFIX}-" > "$temp_list" 2>/dev/null

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

# Function to find the appropriate database backup for a static site backup
find_corresponding_db_backup() {
    local static_backup_tag=$1

    # Extract timestamp from static backup tag (format: AUTO-space-YYYY_MM_DD_HH_MM_SS)
    static_timestamp=$(echo "$static_backup_tag" | grep -o '[0-9_]*$')

    if [ -z "$static_timestamp" ]; then
        return 1
    fi

    # First, check if there's an exact match (convert tag format)
    exact_db_tag=$(echo "$static_backup_tag" | sed "s/^$BACKUP_PREFIX-/$DB_BACKUP_PREFIX-/").sql.gz
    if aws s3 ls s3://$BUCKET_NAME/database/$exact_db_tag $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        echo "$exact_db_tag"
        return 0
    fi

    # Use a temp file to avoid subshell variable issues
    temp_list="/tmp/db_backup_search_$$"
    aws s3 ls s3://$BUCKET_NAME/database/ --recursive $S3_EXTRA_PARAMS | grep "$DB_BACKUP_PREFIX" | awk '{print $4}' | xargs -I {} basename {} > "$temp_list" 2>/dev/null

    best_db_backup=""
    best_timestamp=""

    while read -r line; do
        if [ -n "$line" ]; then
            # Extract timestamp from database backup name (DB-AUTO-env-YYYY_MM_DD_HH_MM_SS.sql.gz)
            db_timestamp=$(echo "$line" | sed "s/^$DB_BACKUP_PREFIX-[^-]*-//" | sed 's/\.sql\.gz$//')

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

# Function to parse restore options
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

# Function to unified restore from backup (WARNING: This is destructive)
restore_backup() {
    local backup_tag=""
    local restore_types=""

    # Parse arguments
    if [ $# -eq 0 ]; then
        print_status $RED "Error: Backup tag is required"
        print_status $YELLOW "Usage: restore <backup_tag> [--only=static,public,database]"
        print_status $YELLOW "Examples:"
        print_status $YELLOW "  restore AUTO-prod-2024_10_08_14_30_00                    # Restore all (static + public + database)"
        print_status $YELLOW "  restore AUTO-prod-2024_10_08_14_30_00 --only=static     # Restore only static site"
        print_status $YELLOW "  restore AUTO-prod-2024_10_08_14_30_00 --only=static,public  # Restore static + public"
        print_status $YELLOW "  restore AUTO-prod-2024_10_08_14_30_00 --only=database   # Restore only database"
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

    print_status $BLUE "UNIFIED RESTORE ANALYSIS"
    print_status $BLUE "========================"
    echo ""

    # Find appropriate backups for each type
    static_backup_tag=""
    public_backup_tag=""
    db_backup_tag=""

    # Static site backup analysis
    if [ "$restore_static" = "yes" ]; then
        if aws s3 ls s3://$BUCKET_NAME/web-backup/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
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
            expected_db_tag=$(echo "$backup_tag" | sed "s/^$BACKUP_PREFIX-/$DB_BACKUP_PREFIX-/").sql.gz
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
        if aws s3 sync s3://$BUCKET_NAME/web-backup/$static_backup_tag/ s3://$BUCKET_NAME/web/ --delete $S3_EXTRA_PARAMS; then
            print_status $GREEN "✓ Static site restore completed successfully"
        else
            print_status $RED "✗ ERROR: Static site restore failed"
            exit 1
        fi
    fi

    # Restore public files
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        print_status $YELLOW "Restoring public files from: $public_backup_tag"
        if aws s3 sync s3://$BUCKET_NAME/public_backup/$public_backup_tag/ s3://$BUCKET_NAME/cms/public/ --delete $S3_EXTRA_PARAMS; then
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
        if aws s3 cp s3://$BUCKET_NAME/database/$db_backup_tag "$temp_db_file" $S3_EXTRA_PARAMS; then
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
                # Convert to epoch time for comparison (cross-platform)
                date_str=$(echo "$timestamp_part" | sed 's/_/ /g' | sed 's/ /:/' | sed 's/ /:/' | sed 's/ / /')
                # Try BSD/macOS date first, then GNU date
                backup_time=$(date -j -f "%Y %m %d %H:%M:%S" "$date_str" +%s 2>/dev/null || date -d "$date_str" +%s 2>/dev/null || echo "0")

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
        # Store AWS output in temp file to avoid subshell issue
        temp_list="/tmp/db_backup_list_$$"
        aws s3 ls s3://"$BUCKET_NAME"/database/ --recursive | grep "$DB_BACKUP_PREFIX" > "$temp_list" 2>/dev/null

        while read -r line; do
            backup_file=$(echo "$line" | awk '{print $4}')
            backup_name=$(echo "$backup_file" | xargs basename)

            # Extract date from backup name (YYYY_MM_DD_HH_MM_SS format)
            timestamp_part=$(echo "$backup_name" | sed "s/.*$DB_BACKUP_PREFIX-[^-]*-//")
            if [ -n "$timestamp_part" ]; then
                # Convert to epoch time for comparison (cross-platform)
                date_str=$(echo "$timestamp_part" | sed 's/_/ /g' | sed 's/ /:/' | sed 's/ /:/' | sed 's/ / /')
                # Try BSD/macOS date first, then GNU date
                backup_time=$(date -j -f "%Y %m %d %H:%M:%S" "$date_str" +%s 2>/dev/null || date -d "$date_str" +%s 2>/dev/null || echo "0")

                if [ "$backup_time" -gt 0 ] && [ "$backup_time" -lt "$cutoff_time" ]; then
                    print_status $YELLOW "Deleting old database backup: $backup_name"
                    aws s3 rm s3://"$BUCKET_NAME"/"$backup_file" $S3_EXTRA_PARAMS
                    count=$((count + 1))
                fi
            fi
        done < "$temp_list"

        rm -f "$temp_list" 2>/dev/null

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
        backup_exit_code=$?
        if [ $backup_exit_code -ne 0 ]; then
            print_status $RED "Database backup failed with exit code: $backup_exit_code"
            exit $backup_exit_code
        else
            print_status $GREEN "Database backup completed successfully"
        fi
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
    "list-static")
        list_static_backups
        ;;
    "list-public")
        list_public_backups
        ;;
    "list-db")
        list_db_backups
        ;;
    "list-old")
        list_old_backups "${2:-7}"
        ;;
    "list-db-old")
        list_old_db_backups "${2:-30}"
        ;;
    "clean")
        clean_old_backups "${2:-7}"
        ;;
    "clean-db")
        clean_old_db_backups "${2:-30}"
        ;;
    "restore")
        shift  # Remove the 'restore' command
        restore_backup "$@"  # Pass all remaining arguments
        ;;
    "backup-db")
        backup_db
        ;;
    "info")
        backup_info "$2"
        ;;
    "info-db")
        db_backup_info "$2"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac