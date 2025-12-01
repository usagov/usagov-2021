#!/bin/sh

# Backup Manager
# Unified manager for all backups (static site, public files, and database)
# Handles backup creation, listing, restore, and cleanup operations

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/common.sh"

# Initialize backup system (sets PROJECT_ROOT, BACKUP_DIR, CONFIG_FILE and loads config)
init_backup_system

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

show_usage() {
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  list [types] [days|start:end]            List backups (default: all types, 30 days)"
    echo "  backup [types] [prefix] [suffix]         Create backups (default: all types, AUTO prefix)"
    echo "                [--skip-state-management|--ssm]  Use --skip-state-management (or --ssm) to skip Drupal state checks"
    echo "  clean [types] [filters] [-y]             Remove backups by date filter (default: all types, 30 days)"
    echo "  restore <tag> [--only=type,type] [--skip-state-management|--ssm]  Restore backups from tag"
    echo "  info [types] [tag]                       Show backup system info or specific backup details"
    echo "  download <tag> [type] [path] [--stream]  Download backups (default: all types, current dir)"
    echo ""
    echo "Backup Types:"
    echo "  all                      All backup types (default)"
    echo "  static                   Static site backups"
    echo "  public                   Public file backups"
    echo "  db                       Database backups"
    echo "  static,public            Multiple types (comma-separated)"
    echo ""
    echo "Clean Date Filters:"
    echo "  Retention (days-based):"
    echo "    [days]                           Keep last N days, delete older (default: 30)"
    echo "    --older-than [days]              Explicit: delete older than N days"
    echo "  "
    echo "  Date Ranges (absolute):"
    echo "    --in-range START:END             Delete backups within date range"
    echo "    --except-range START:END         Keep backups within range, delete others"
    echo "    --older-than-date DATE           Delete backups before this date"
    echo "    --newer-than-date DATE           Delete backups after this date"
    echo "  "
    echo "  Date Format: YYYY-MM-DD (e.g., 2025-10-01)"
    echo "  Range Format: START:END, START:, or :END (e.g., 2025-01-01:2025-12-31)"
    echo "  "
    echo "  Flags:"
    echo "    -y, --non-interactive            Skip confirmation prompts"
    echo "    all | 0                          Delete ALL backups (requires 'DELETE ALL')"
    echo ""
    echo "Backup Tag Format:"
    echo "  PREFIX-SPACE-CONTAINERTAG-YYYY-MM-DD[-SUFFIX]"
    echo "  Example: AUTO-dev-cf-a1b2c3-2025-10-24"
    echo "  Example: USAGOV-123-prod-cf-d4e5f6-2025-10-24-post-deploy"
    echo ""
    echo "Examples:"
    echo "  # List backups"
    echo "  $0 list                                                # List all backups from last 30 days"
    echo "  $0 list static,db                                      # List static and database backups"
    echo "  $0 list all 7                                          # List all backups from last 7 days"
    echo "  $0 list all 2025-10-01:2025-10-31                      # List October 2025 backups"
    echo "  "
    echo "  # Create backups"
    echo "  $0 backup                                              # Create all backups with AUTO prefix"
    echo "  $0 backup db                                           # Create database backup only"
    echo "  $0 backup all USAGOV-123 post-deploy                   # Custom prefix and suffix"
    echo "  "
    echo "  # Clean backups (retention-based)"
    echo "  $0 clean all 7                                         # Keep last 7 days, delete older"
    echo "  $0 clean all --older-than 30                           # Keep last 30 days (explicit)"
    echo "  $0 clean db 14 -y                                      # Non-interactive cleanup"
    echo "  "
    echo "  # Clean backups (delete specific periods)"
    echo "  $0 clean all --in-range 2024-01-01:2024-12-31          # Delete all 2024 backups"
    echo "  $0 clean static --in-range 2025-10-01:2025-10-31       # Delete October static backups"
    echo "  $0 clean db --in-range 2025-11-01: -y                  # Delete Nov onward (non-interactive)"
    echo "  "
    echo "  # Clean backups (keep specific periods)"
    echo "  $0 clean all --except-range 2025-10-01:2025-10-31      # Keep only October, delete rest"
    echo "  $0 clean all --except-range 2025-11-01:                # Keep from Nov onward, delete older"
    echo "  $0 clean db --except-range :2025-10-31                 # Keep up to Oct, delete newer"
    echo "  "
    echo "  # Clean backups (by date boundaries)"
    echo "  $0 clean all --older-than-date 2025-01-01              # Delete everything before 2025"
    echo "  $0 clean all --newer-than-date 2025-10-31              # Delete everything after Oct 31"
    echo "  "
    echo "  # Delete ALL backups (dangerous!)"
    echo "  $0 clean all 0                                         # ⚠️  DELETE ALL (requires confirmation)"
    echo "  $0 clean all all                                       # ⚠️  DELETE ALL (same as above)"
    echo "  "
    echo "  # Other commands"
    echo "  $0 info                                                # Show backup system configuration"
    echo "  $0 restore AUTO-prod-14850-2025-10-28                   # Restore all from backup"
    echo "  $0 download AUTO-prod-14850-2025-10-28 db ./backups/    # Download db to ./backups/"
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
    local custom_prefix="${2:-}"
    local custom_suffix="${3:-}"
    local skip_state_management=false

    # Check for --skip-state-management (or --ssm) flag in any position after the first 3 params
    shift 3 2>/dev/null || true
    for arg in "$@"; do
        if [ "$arg" = "--skip-state-management" ] || [ "$arg" = "--ssm" ]; then
            skip_state_management=true
        fi
    done

    local backup_types=$(parse_backup_types "$types_arg")

    # Determine backup prefix and suffix
    local backup_prefix="${custom_prefix:-$BACKUP_PREFIX}"
    local backup_suffix=""
    if [ -n "$custom_suffix" ]; then
        backup_suffix="-${custom_suffix}"
    fi

    # Validate prefix and suffix don't contain spaces
    if echo "$backup_prefix" | grep -q ' '; then
        print_status $RED "❌ Error: Backup prefix cannot contain spaces"
        print_status $YELLOW "   Use hyphens or underscores instead: 'MY-PREFIX' or 'MY_PREFIX'"
        return 1
    fi
    if [ -n "$custom_suffix" ] && echo "$custom_suffix" | grep -q ' '; then
        print_status $RED "❌ Error: Backup suffix cannot contain spaces"
        print_status $YELLOW "   Use hyphens or underscores instead: 'my-suffix' or 'my_suffix'"
        return 1
    fi

    # Generate single timestamp for this backup event (format: 2025-10-24)
    local backup_timestamp=$(date +"%Y-%m-%d")

    print_status $BLUE "📦 Creating backup: $backup_types"
    if [ "$backup_prefix" != "$BACKUP_PREFIX" ]; then
        print_status $YELLOW "Prefix: $backup_prefix"
    fi
    if [ -n "$backup_suffix" ]; then
        print_status $YELLOW "Suffix: $custom_suffix"
    fi
    print_status $YELLOW "Timestamp: $backup_timestamp"
    if [ "$skip_state_management" = "true" ]; then
        print_status $YELLOW "⚠️  Skipping Drupal state management"
    fi

    # Run static backup if requested
    if has_backup_type "$backup_types" "static"; then
        print_status $GREEN "🌐 Backing up static site..."
        create_static_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp"
    fi

    # Run public backup if requested
    if has_backup_type "$backup_types" "public"; then
        print_status $GREEN "📁 Backing up public files..."
        create_public_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp"
    fi

    # Run database backup if requested
    if has_backup_type "$backup_types" "db"; then
        print_status $GREEN "💾 Backing up database..."
        create_db_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
    fi

    print_status $BLUE "🎉 Done."
}

# Handle clean command
run_clean_command() {
    local types_arg="${1:-all}"
    shift || true

    local non_interactive=false
    local filter_type=""
    local filter_value=""
    local filter_count=0

    # Parse all arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --non-interactive|-y)
                non_interactive=true
                shift
                ;;
            --older-than)
                filter_type="days"
                filter_value="$2"
                filter_count=$((filter_count + 1))
                shift 2
                ;;
            --in-range|-r)
                filter_type="in-range"
                filter_value="$2"
                filter_count=$((filter_count + 1))
                shift 2
                ;;
            --except-range|-x)
                filter_type="except-range"
                filter_value="$2"
                filter_count=$((filter_count + 1))
                shift 2
                ;;
            --older-than-date)
                filter_type="older-date"
                filter_value="$2"
                filter_count=$((filter_count + 1))
                shift 2
                ;;
            --newer-than-date)
                filter_type="newer-date"
                filter_value="$2"
                filter_count=$((filter_count + 1))
                shift 2
                ;;
            all|0)
                filter_type="all"
                filter_value="0"
                filter_count=$((filter_count + 1))
                shift
                ;;
            *)
                # Assume it's a days value if it's a number
                if echo "$1" | grep -qE '^[0-9]+$'; then
                    filter_type="days"
                    filter_value="$1"
                    filter_count=$((filter_count + 1))
                fi
                shift
                ;;
        esac
    done

    # Check for conflicting filters
    if [ $filter_count -gt 1 ]; then
        print_status $RED "❌ Error: Cannot mix multiple date filtering methods"
        echo "   Use only ONE of: days, --older-than, --in-range, --except-range, --older-than-date, --newer-than-date"
        return 1
    fi

    # Default to 30 days if no filter specified
    if [ -z "$filter_type" ]; then
        filter_type="days"
        filter_value="30"
    fi

    # Validate date formats for date-based filters
    if [ "$filter_type" = "in-range" ] || [ "$filter_type" = "except-range" ]; then
        if ! echo "$filter_value" | grep -q ':'; then
            print_status $RED "❌ Error: Invalid date range format: $filter_value"
            echo "   Expected format: YYYY-MM-DD:YYYY-MM-DD (e.g., 2025-01-01:2025-12-31)"
            return 1
        fi
        local start_date=$(echo "$filter_value" | cut -d: -f1)
        local end_date=$(echo "$filter_value" | cut -d: -f2)
        if [ -n "$start_date" ] && [ -z "$(date_to_epoch "$start_date")" ]; then
            print_status $RED "❌ Error: Invalid start date format: $start_date"
            echo "   Expected format: YYYY-MM-DD (e.g., 2025-01-01)"
            return 1
        fi
        if [ -n "$end_date" ] && [ -z "$(date_to_epoch "$end_date")" ]; then
            print_status $RED "❌ Error: Invalid end date format: $end_date"
            echo "   Expected format: YYYY-MM-DD (e.g., 2025-12-31)"
            return 1
        fi
        # Check that start <= end if both provided
        if [ -n "$start_date" ] && [ -n "$end_date" ]; then
            local start_epoch=$(date_to_epoch "$start_date")
            local end_epoch=$(date_to_epoch "$end_date")
            if [ "$start_epoch" -gt "$end_epoch" ]; then
                print_status $RED "❌ Error: Invalid date range: $filter_value"
                echo "   Start date ($start_date) must be before or equal to end date ($end_date)"
                return 1
            fi
        fi
    elif [ "$filter_type" = "older-date" ] || [ "$filter_type" = "newer-date" ]; then
        if [ -z "$(date_to_epoch "$filter_value")" ]; then
            print_status $RED "❌ Error: Invalid date format: $filter_value"
            echo "   Expected format: YYYY-MM-DD (e.g., 2025-01-01)"
            return 1
        fi
    fi

    local backup_types=$(parse_backup_types "$types_arg")

    # Show appropriate warning based on filter type
    if [ "$filter_type" = "all" ]; then
        if [ "$non_interactive" = "false" ]; then
            echo ""
            print_status $RED "╔════════════════════════════════════════════════════════════════╗"
            print_status $RED "║                    ⚠️  DANGER ZONE  ⚠️                         ║"
            print_status $RED "║                                                                ║"
            print_status $RED "║  This will DELETE ALL $backup_types backups                    ║"
            print_status $RED "║  regardless of age!                                            ║"
            print_status $RED "║                                                                ║"
            print_status $RED "║  THIS ACTION CANNOT BE UNDONE!                                 ║"
            print_status $RED "╚════════════════════════════════════════════════════════════════╝"
            echo ""
            printf "Type 'DELETE ALL' to confirm (or anything else to cancel): "
            read -r confirm

            if [ "$confirm" != "DELETE ALL" ]; then
                print_status $GREEN "✅ Cancelled. No backups were deleted."
                return 1
            fi
        fi
    else
        if [ "$non_interactive" = "false" ]; then
            # Show context-specific warning
            case "$filter_type" in
                "days")
                    local cutoff_date=$(date -u -d "@$(get_days_ago_epoch $filter_value)" '+%Y-%m-%d' 2>/dev/null || date -u -r "$(get_days_ago_epoch $filter_value)" '+%Y-%m-%d' 2>/dev/null)
                    print_status $YELLOW "⚠️  This will delete all $backup_types backups older than $filter_value days (before $cutoff_date)"
                    ;;
                "in-range")
                    local start_date=$(echo "$filter_value" | cut -d: -f1)
                    local end_date=$(echo "$filter_value" | cut -d: -f2)
                    if [ -n "$start_date" ] && [ -n "$end_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups within the range $start_date to $end_date"
                    elif [ -n "$start_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups from $start_date onward"
                    elif [ -n "$end_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups up to $end_date"
                    fi
                    echo "   Backups OUTSIDE this range will be KEPT"
                    ;;
                "except-range")
                    local start_date=$(echo "$filter_value" | cut -d: -f1)
                    local end_date=$(echo "$filter_value" | cut -d: -f2)
                    if [ -n "$start_date" ] && [ -n "$end_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups EXCEPT those in the range $start_date to $end_date"
                    elif [ -n "$start_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups before $start_date"
                        echo "   Backups from $start_date onward will be KEPT"
                    elif [ -n "$end_date" ]; then
                        print_status $YELLOW "⚠️  This will DELETE all $backup_types backups after $end_date"
                        echo "   Backups up to $end_date will be KEPT"
                    fi
                    ;;
                "older-date")
                    print_status $YELLOW "⚠️  This will DELETE all $backup_types backups older than $filter_value (before $filter_value)"
                    echo "   Backups from $filter_value onward will be KEPT"
                    ;;
                "newer-date")
                    print_status $YELLOW "⚠️  This will DELETE all $backup_types backups newer than $filter_value (after $filter_value)"
                    echo "   Backups before $filter_value will be KEPT"
                    ;;
            esac

            printf "Continue? [y/N]: "
            read -r confirm

            if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
                print_status $RED "❌ Cancelled."
                return 1
            fi
        else
            # Non-interactive mode - just show what we're doing
            case "$filter_type" in
                "days")
                    print_status $YELLOW "⚠️ Cleaning $backup_types backups older than $filter_value days (non-interactive mode)"
                    ;;
                "in-range")
                    print_status $YELLOW "⚠️ Cleaning $backup_types backups in range: $filter_value (non-interactive mode)"
                    ;;
                "except-range")
                    print_status $YELLOW "⚠️ Cleaning $backup_types backups except range: $filter_value (non-interactive mode)"
                    ;;
                "older-date")
                    print_status $YELLOW "⚠️ Cleaning $backup_types backups older than: $filter_value (non-interactive mode)"
                    ;;
                "newer-date")
                    print_status $YELLOW "⚠️ Cleaning $backup_types backups newer than: $filter_value (non-interactive mode)"
                    ;;
            esac
        fi
    fi

    print_status $BLUE "🧹 Cleaning up backups..."

    # Clean static and public backups if requested
    if has_backup_type "$backup_types" "static" || has_backup_type "$backup_types" "public"; then
        clean_old_backups "$filter_type" "$filter_value"
    fi

    # Clean database backups if requested
    if has_backup_type "$backup_types" "db"; then
        cleanup_old_db_backups "$filter_type" "$filter_value"
    fi

    print_status $BLUE "🎉 Cleanup complete."
}

# Handle info command
run_info_command() {
    local types_arg="${1:-all}"
    local tag="${2:-}"

    local backup_types=$(parse_backup_types "$types_arg")

    if [ -n "$tag" ]; then
        # Show info for specific tag with requested types
        backup_info "$tag" "$backup_types"
    else
        # Show general backup info for requested types
        setup_s3_vars || exit 1

        print_status $BLUE "Backup System Information"
        echo "=========================="
        echo ""

        if has_backup_type "$backup_types" "static"; then
            echo "Static Site Backups:"
            echo "  Path: $AUTO_STATIC_BACKUP_PATH"
            echo "  Prefix: $BACKUP_PREFIX"
            echo "  Retention: $BACKUP_RETENTION_DAYS days"
            echo ""
        fi

        if has_backup_type "$backup_types" "public"; then
            echo "Public Files Backups:"
            echo "  Path: $AUTO_PUBLIC_BACKUP_PATH"
            echo "  Prefix: $BACKUP_PREFIX"
            echo "  Retention: $BACKUP_RETENTION_DAYS days"
            echo ""
        fi

        if has_backup_type "$backup_types" "db"; then
            echo "Database Backups:"
            echo "  Path: $AUTO_DB_BACKUP_PATH"
            echo "  Prefix: $DB_BACKUP_PREFIX"
            echo "  Retention: $DB_BACKUP_RETENTION_DAYS days"
            echo ""
        fi

        echo "S3 Bucket: $BUCKET_NAME"
        echo "Configuration: $CONFIG_FILE"
    fi
}

# ===================================================================
# DATABASE BACKUP FUNCTIONS
# ===================================================================

# Create a database backup with optional custom prefix, suffix, and timestamp
# Args:
#   $1: custom_prefix (default: DB_BACKUP_PREFIX from config)
#   $2: backup_suffix (optional, e.g., "-post-deploy")
#   $3: backup_timestamp (default: current date in YYYY-MM-DD format)
#   $4: skip_state_management (optional, "true" to skip Drupal state management)
create_db_backup() {
    local custom_prefix="${1:-$DB_BACKUP_PREFIX}"
    local backup_suffix="${2:-}"
    local backup_timestamp="${3:-$(date +"%Y-%m-%d")}"
    local skip_state_management="${4:-false}"

    setup_s3_vars || exit 1

    # Check if database backups are enabled
    if [ "$ENABLE_DB_BACKUPS" != "true" ]; then
        log_message "⚠️ Database backups disabled"
        return 0
    fi

    # Prepare Drupal state (wait for tome, disable it, enable maintenance mode)
    local drupal_state_prepared=false
    if [ "$skip_state_management" != "true" ]; then
        if prepare_drupal_for_backup 25; then
            drupal_state_prepared=true
        else
            log_message "❌ Failed to prepare Drupal state for backup"
            return 1
        fi
    fi

    # Generate backup tag with timestamp and container tag
    CONTAINER_TAG=$(get_container_tag)
    # Generate base backup tag
    CONTAINER_TAG=$(get_container_tag)
    local base_tag="${custom_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"

    # Get next available numeric suffix for same-day backups
    local numeric_suffix=$(get_next_backup_suffix "db" "$base_tag")

    # Construct final tag with user suffix (if any) and numeric suffix
    DB_BACKUP_TAG="${base_tag}${backup_suffix}-${numeric_suffix}"

    log_message "💾 Database backup: $DB_BACKUP_TAG"

    # Setup log file
    LOG_DIR="/tmp/tome-log"
    mkdir -p "$LOG_DIR"
    LOGFILE="$LOG_DIR/db-backup-${backup_timestamp}.log"

    log_message "🔄 Dumping database..." | tee -a "$LOGFILE"

    # Set working directory for drush
    local original_dir=$(pwd)
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        if ! cd /var/www 2>/dev/null; then
            log_message "❌ ERROR: Cannot change to /var/www directory" | tee -a "$LOGFILE"
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
            return 1
        fi
    elif [ "$PROJECT_ROOT" != "$original_dir" ]; then
        # Change to project root if not already there
        if ! cd "$PROJECT_ROOT" 2>/dev/null; then
            log_message "❌ ERROR: Cannot change to project root: $PROJECT_ROOT" | tee -a "$LOGFILE"
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
            return 1
        fi
    fi

    TEMP_SQL="/tmp/${DB_BACKUP_TAG}.sql"
    TEMP_GZIP="/tmp/${DB_BACKUP_TAG}.sql.gz"

    # Create database dump using drush
    if command -v drush >/dev/null 2>&1; then
        # Clear cache first, then create dump to SQL file
        drush cr 2>&1 | tee -a "$LOGFILE"
        drush sql:dump --result-file="$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"
        DUMP_EXIT_CODE=$?
    else
        log_message "❌ ERROR: drush not found" | tee -a "$LOGFILE"
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    if [ $DUMP_EXIT_CODE -ne 0 ]; then
        log_message "❌ ERROR: Database dump failed" | tee -a "$LOGFILE"
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    # Verify the SQL dump file was created and has content
    if [ ! -f "$TEMP_SQL" ] || [ ! -s "$TEMP_SQL" ]; then
        log_message "❌ ERROR: Database dump empty or missing" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    # Validate SQL dump structure
    log_message "🔍 Validating SQL dump structure..." | tee -a "$LOGFILE"
    if ! validate_sql_dump "$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"; then
        log_message "❌ ERROR: SQL dump validation failed" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    # Compress the SQL file using gzip
    log_message "🗜️ Compressing..." | tee -a "$LOGFILE"
    gzip "$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"
    GZIP_EXIT_CODE=$?

    if [ $GZIP_EXIT_CODE -ne 0 ]; then
        log_message "❌ ERROR: Compression failed" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    # Verify the compressed file was created
    if [ ! -f "$TEMP_GZIP" ] || [ ! -s "$TEMP_GZIP" ]; then
        log_message "❌ ERROR: Compressed file empty or missing" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
        return 1
    fi

    # Upload compressed file to S3
    log_message "☁️ Uploading..." | tee -a "$LOGFILE"

    S3_DB_PATH="s3://${BUCKET_NAME}/${AUTO_DB_BACKUP_PATH}/${DB_BACKUP_TAG}.sql.gz"
    log_message "📍 Target: $S3_DB_PATH" | tee -a "$LOGFILE"

    aws s3 cp "$TEMP_GZIP" "$S3_DB_PATH" --only-show-errors 2>&1 | tee -a "$LOGFILE"
    UPLOAD_EXIT_CODE=$?

    rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null

    # Restore Drupal state before checking results
    if [ "$drupal_state_prepared" = "true" ]; then
        restore_drupal_state  # Disable maintenance mode, then re-enable tome
    fi

    if [ $UPLOAD_EXIT_CODE -eq 0 ]; then
        log_message "✅ Database backup complete: $S3_DB_PATH" | tee -a "$LOGFILE"
        print_status $GREEN "✅ Database backup saved: $DB_BACKUP_TAG"

        # Upload log to S3
        if [ -f "$LOGFILE" ]; then
            aws s3 cp "$LOGFILE" "s3://$BUCKET_NAME/db-backup-logs/$(basename "$LOGFILE")" $S3_EXTRA_PARAMS >/dev/null 2>&1
        fi

        return 0
    else
        log_message "❌ ERROR: Database backup upload failed with exit code: $UPLOAD_EXIT_CODE" | tee -a "$LOGFILE"
        print_status $RED "❌ Database backup failed: $DB_BACKUP_TAG"
        return 1
    fi
}

# ===================================================================
# STATIC SITE BACKUP FUNCTIONS
# ===================================================================

# Create a static site backup with optional custom prefix, suffix, and timestamp
# Uploads the static site content (generated by Drupal Tome) to S3
# Args:
#   $1: custom_prefix (default: BACKUP_PREFIX from config)
#   $2: backup_suffix (optional, e.g., "-post-deploy")
#   $3: backup_timestamp (default: current date in YYYY-MM-DD format)
create_static_backup() {
    local custom_prefix="${1:-$BACKUP_PREFIX}"
    local backup_suffix="${2:-}"
    local backup_timestamp="${3:-$(date +"%Y-%m-%d")}"

    setup_s3_vars || exit 1

    if [ "$ENABLE_STATIC_AUTO_BACKUPS" != "true" ]; then
        log_message "⚠️ Static site backups disabled"
        return 0
    fi

    # Generate base backup tag
    CONTAINER_TAG=$(get_container_tag)
    local base_tag="${custom_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"

    # Get next available numeric suffix for same-day backups
    local numeric_suffix=$(get_next_backup_suffix "static" "$base_tag")

    # Construct final tag with user suffix (if any) and numeric suffix
    BACKUP_TAG="${base_tag}${backup_suffix}-${numeric_suffix}"

    log_message "🌐 Creating static site backup: $BACKUP_TAG"

    # Note: S3_EXTRA_PARAMS may contain multiple parameters, so we don't quote it
    if aws s3 cp --only-show-errors s3://$BUCKET_NAME/web/ s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS 2>&1; then
        local exit_code=$?
        if [ $exit_code -eq 0 ]; then
            print_status $GREEN "✅ Static site backed up: $BACKUP_TAG"
            return 0
        else
            print_status $RED "❌ Static site backup failed with exit code: $exit_code"
            return 1
        fi
    else
        print_status $RED "❌ Static site backup failed: $BACKUP_TAG"
        return 1
    fi
}

# ===================================================================
# PUBLIC FILES BACKUP FUNCTIONS
# ===================================================================

# Create a public files backup with optional custom prefix, suffix, and timestamp
# Uploads public files (media, documents, etc.) to S3 with smart detection for changes
# Args:
#   $1: custom_prefix (default: BACKUP_PREFIX from config)
#   $2: backup_suffix (optional, e.g., "-post-deploy")
#   $3: backup_timestamp (default: current date in YYYY-MM-DD format)
create_public_backup() {
    local custom_prefix="${1:-$BACKUP_PREFIX}"
    local backup_suffix="${2:-}"
    local backup_timestamp="${3:-$(date +"%Y-%m-%d")}"

    setup_s3_vars || exit 1

    if [ "$ENABLE_PUBLIC_AUTO_BACKUPS" != "true" ]; then
        log_message "⚠️ Public files backups disabled"
        return 0
    fi

    # Generate base backup tag
    CONTAINER_TAG=$(get_container_tag)
    local base_tag="${custom_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"

    # Get next available numeric suffix for same-day backups
    local numeric_suffix=$(get_next_backup_suffix "public" "$base_tag")

    # Construct final tag with user suffix (if any) and numeric suffix
    BACKUP_TAG="${base_tag}${backup_suffix}-${numeric_suffix}"

    # Smart backup check if enabled
    PUBLIC_BACKUP_NEEDED=true

    if [ "$ENABLE_SMART_PUBLIC_BACKUP" = "true" ]; then
        log_message "🔍 Checking if public files backup needed..."

        # Find the most recent automatic public files backup
        LATEST_PUBLIC_BACKUP=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "${BACKUP_PREFIX}-${APP_SPACE}-" | sort -r | head -n1 | awk '{print $2}' | tr -d '/')

        if [ -n "$LATEST_PUBLIC_BACKUP" ]; then
            log_message "🔄 Comparing with latest backup: $LATEST_PUBLIC_BACKUP"

            # Get checksums of current public files and latest backup
            CURRENT_PUBLIC_CHECKSUM=$(aws s3 ls --recursive s3://$BUCKET_NAME/cms/public/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5sum | awk '{print $1}' 2>/dev/null || aws s3 ls --recursive s3://$BUCKET_NAME/cms/public/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5 2>/dev/null)
            BACKUP_PUBLIC_CHECKSUM=$(aws s3 ls --recursive s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$LATEST_PUBLIC_BACKUP/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5sum | awk '{print $1}' 2>/dev/null || aws s3 ls --recursive s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$LATEST_PUBLIC_BACKUP/ $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3 " " $4}' | sort | md5 2>/dev/null)

            if [ -n "$CURRENT_PUBLIC_CHECKSUM" ] && [ -n "$BACKUP_PUBLIC_CHECKSUM" ] && [ "$CURRENT_PUBLIC_CHECKSUM" = "$BACKUP_PUBLIC_CHECKSUM" ]; then
                log_message "⏭️ Public files unchanged, skipping backup"
                PUBLIC_BACKUP_NEEDED=false
            fi
        fi
    fi

    if [ "$PUBLIC_BACKUP_NEEDED" = "true" ]; then
        log_message "📁 Creating public files backup: $BACKUP_TAG"
        # Note: S3_EXTRA_PARAMS may contain multiple parameters, so we don't quote it
        if aws s3 cp --only-show-errors s3://$BUCKET_NAME/cms/public/ s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS 2>&1; then
            local exit_code=$?
            if [ $exit_code -eq 0 ]; then
                print_status $GREEN "✅ Public files backed up: $BACKUP_TAG"
                return 0
            else
                print_status $RED "❌ Public files backup failed with exit code: $exit_code"
                return 1
            fi
        else
            print_status $RED "❌ Public files backup failed: $BACKUP_TAG"
            return 1
        fi
    else
        print_status $YELLOW "⚠️ Public files unchanged - skipped"
        return 0
    fi
}

# Create all backups
backup_all() {
    local custom_prefix="${1:-$BACKUP_PREFIX}"
    local custom_suffix="${2:-}"

    # Prepare backup suffix with proper formatting
    local backup_suffix=""
    if [ -n "$custom_suffix" ]; then
        backup_suffix="-${custom_suffix}"
    fi

    # Generate single timestamp for this backup event (format: 2025-10-24)
    local backup_timestamp=$(date +"%Y-%m-%d")

    print_status $BLUE "📦 Creating all backups..."

    success_count=0
    total_count=0

    # Create static backup
    if [ "$ENABLE_STATIC_AUTO_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_static_backup "$custom_prefix" "$backup_suffix" "$backup_timestamp"; then
            success_count=$((success_count + 1))
        fi
    fi

    # Create public backup
    if [ "$ENABLE_PUBLIC_AUTO_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_public_backup "$custom_prefix" "$backup_suffix" "$backup_timestamp"; then
            success_count=$((success_count + 1))
        fi
    fi

    # Create database backup
    if [ "$ENABLE_DB_BACKUPS" = "true" ]; then
        total_count=$((total_count + 1))
        if create_db_backup "$custom_prefix" "$backup_suffix" "$backup_timestamp"; then
            success_count=$((success_count + 1))
        fi
    fi

    if [ $success_count -eq $total_count ]; then
        print_status $GREEN "✅ All backups complete ($success_count/$total_count)"
        # Run cleanup
        cleanup_all_old_backups
    else
        print_status $RED "❌ Some backups failed ($success_count/$total_count ok)"
        return 1
    fi
}

list_backups() {
    local types_arg="${1:-all}"
    local filter_arg="${2:-}"

    local backup_types=$(parse_backup_types "$types_arg")

    # If a filter argument (days or date range) is provided, use list_old_backups
    if [ -n "$filter_arg" ]; then
        list_old_backups "$filter_arg"
        return 0
    fi

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
    setup_s3_vars || exit 1

    print_status $GREEN "Static Site Backups:"
    echo "===================="

    # Get list of backup directories with metadata
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | while read -r line; do
        backup_tag=$(echo "$line" | awk '{print $2}' | tr -d '/')

        # Get total size and first file date from backup directory
        first_file=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | head -1)
        backup_size=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | awk '{sum+=$3} END {print sum}')
        backup_date=$(echo "$first_file" | awk '{print $1" "$2}')

        if [ -z "$backup_size" ]; then
            backup_size="0"
        fi

        if [ -z "$backup_date" ]; then
            backup_date="unknown"
        fi

        local formatted_size=$(format_file_size "$backup_size")
        echo "  $backup_tag ($formatted_size) - $backup_date"
    done
}

list_public_backups() {
    setup_s3_vars || exit 1

    print_status $GREEN "Public Files Backups:"
    echo "====================="

    # Get list of backup directories with metadata
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | while read -r line; do
        backup_tag=$(echo "$line" | awk '{print $2}' | tr -d '/')

        # Get total size and first file date from backup directory
        first_file=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | head -1)
        backup_size=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | awk '{sum+=$3} END {print sum}')
        backup_date=$(echo "$first_file" | awk '{print $1" "$2}')

        if [ -z "$backup_size" ]; then
            backup_size="0"
        fi

        if [ -z "$backup_date" ]; then
            backup_date="unknown"
        fi

        local formatted_size=$(format_file_size "$backup_size")
        echo "  $backup_tag ($formatted_size) - $backup_date"
    done
}

list_db_backups() {
    setup_s3_vars || exit 1

    print_status $GREEN "Database Backups:"
    echo "=================="

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | sort -r | while read -r line; do
            # Extract backup name from S3 listing
            backup_file=$(echo "$line" | awk '{print $4}' | xargs basename)
            backup_size=$(echo "$line" | awk '{print $3}')
            backup_date=$(echo "$line" | awk '{print $1" "$2}')

            local formatted_size=$(format_file_size "$backup_size")
            echo "  $backup_file ($formatted_size) - $backup_date"
        done
    else
        print_status $RED "❌ Error: AWS credentials not available"
    fi
}

list_all_backups() {
    setup_s3_vars || exit 1

    print_status $BLUE "Backups by Restore Tag"
    echo ""

    # Create temporary files to collect backup data
    static_list="/tmp/static_backups_$$"
    public_list="/tmp/public_backups_$$"
    db_list="/tmp/db_backups_$$"

    # Get all backup lists (don't filter by prefix to show all backups including manual ones)
    # Sort in chronological order (oldest first, newest last)
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | awk '{print $2}' | tr -d '/' | sort > "$static_list" 2>/dev/null
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | awk '{print $2}' | tr -d '/' | sort > "$public_list" 2>/dev/null
    aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | awk '{print $4}' | xargs -I {} basename {} | sort > "$db_list" 2>/dev/null

    # Create unified list of all backup tags (timestamps)
    # Sort in chronological order (oldest first, newest last)
    all_tags="/tmp/all_backup_tags_$$"
    (
        cat "$static_list" 2>/dev/null
        cat "$public_list" 2>/dev/null
        cat "$db_list" 2>/dev/null | sed 's/\.sql\.gz$//'
    ) | sort -u > "$all_tags"

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
                has_static="✅"
            else
                has_static="❌"
            fi

            # Check public backup
            if grep -q "^$tag$" "$public_list" 2>/dev/null; then
                has_public="✅"
            else
                has_public="❌"
            fi

            # Check database backup
            db_tag="${tag}.sql.gz"
            if grep -q "^$db_tag$" "$db_list" 2>/dev/null; then
                has_database="✅"
            else
                has_database="❌"
            fi

            # Format restore command
            restore_cmd="restore $tag"

            printf "%-32s %-8s %-8s %-8s %s\n" "$tag" "$has_static" "$has_public" "$has_database" "$restore_cmd"
        fi
    done < "$all_tags"

    rm -f "$static_list" "$public_list" "$db_list" "$all_tags" 2>/dev/null

    echo ""
    print_status $YELLOW "✅ = Available    ❌ = Missing (smart fallback may apply)"
}

# ===================================================================
# BACKUP CLEANUP FUNCTIONS
# ===================================================================

# Clean up old database backups based on retention days
# Removes backups older than the specified number of days from S3
# Special case: days=0 deletes ALL database backups (requires confirmation)
# Args:
#   $1: days - Number of days to retain (default: DB_BACKUP_RETENTION_DAYS)
cleanup_old_db_backups() {
    local filter_type="${1:-days}"
    local filter_value="${2:-$DB_BACKUP_RETENTION_DAYS}"

    if [ "$ENABLE_DB_AUTO_CLEANUP" != "true" ]; then
        log_message "⚠️ Database automatic cleanup is disabled"
        return 0
    fi

    setup_s3_vars || exit 1

    # Special handling for deleting ALL database backups
    if [ "$filter_type" = "all" ]; then
        log_message "$(show_filter_message "$filter_type" "$filter_value" "database backups")"

        # Delete ALL database backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | grep "\.sql\.gz$" | while read -r line; do
            backup_path=$(echo "$line" | awk '{print $4}')
            if [ -n "$backup_path" ]; then
                log_message "🗑️ Removing database backup: $backup_path"
                aws s3 rm "s3://$BUCKET_NAME/$backup_path" $S3_EXTRA_PARAMS 2>&1
            fi
        done
        log_message "✅ All database backups removed"
        return 0
    fi

    # Display what we're doing using consolidated helper
    log_message "$(show_filter_message "$filter_type" "$filter_value" "database backups")"

    # List and delete database backups matching filter
    aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | grep "\.sql\.gz$" | while read -r line; do
        backup_path=$(echo "$line" | awk '{print $4}')
        backup_date=$(extract_date_from_backup_name "$backup_path")

        if [ -n "$backup_date" ]; then
            if matches_clean_filter "$backup_date" "$filter_type" "$filter_value"; then
                log_message "🗑️ Removing old database backup: $backup_path (date: $backup_date)"
                aws s3 rm "s3://$BUCKET_NAME/$backup_path" $S3_EXTRA_PARAMS 2>&1
            fi
        fi
    done
    log_message "✅ Database backup cleanup complete"
}

# List backups older than specified days or within date range
# Displays static, public, and database backups matching date criteria
# Args:
#   $1: days or date range (format: YYYY-MM-DD:YYYY-MM-DD, YYYY-MM-DD:, or :YYYY-MM-DD)
#       - days: Number of days threshold (default: 7)
#       - date range: Filter backups within specified range (inclusive)
list_old_backups() {
    local filter_arg=${1:-7}
    setup_s3_vars || exit 1

    local start_date=""
    local end_date=""
    local cutoff_epoch=""
    local filter_mode=""

    # Check if argument contains a colon (date range format)
    if echo "$filter_arg" | grep -q ':'; then
        filter_mode="range"
        start_date=$(echo "$filter_arg" | cut -d: -f1)
        end_date=$(echo "$filter_arg" | cut -d: -f2)

        # Convert to epochs if dates provided
        local start_epoch=""
        local end_epoch=""
        if [ -n "$start_date" ]; then
            start_epoch=$(date_to_epoch "$start_date")
            if [ -z "$start_epoch" ]; then
                print_status $RED "Invalid start date: $start_date (use YYYY-MM-DD)"
                return 1
            fi
        fi
        if [ -n "$end_date" ]; then
            end_epoch=$(date_to_epoch "$end_date")
            if [ -z "$end_epoch" ]; then
                print_status $RED "Invalid end date: $end_date (use YYYY-MM-DD)"
                return 1
            fi
        fi

        # Display header
        if [ -n "$start_date" ] && [ -n "$end_date" ]; then
            print_status $YELLOW "Backups from ${start_date} to ${end_date}:"
        elif [ -n "$start_date" ]; then
            print_status $YELLOW "Backups from ${start_date} onward:"
        elif [ -n "$end_date" ]; then
            print_status $YELLOW "Backups up to ${end_date}:"
        else
            print_status $YELLOW "All backups:"
        fi
    else
        # Days-based filtering (original behavior)
        filter_mode="days"
        cutoff_epoch=$(get_days_ago_epoch "$filter_arg")
        cutoff_display=$(date -u -d "@$cutoff_epoch" '+%Y-%m-%d' 2>/dev/null || date -u -r "$cutoff_epoch" '+%Y-%m-%d' 2>/dev/null)
        print_status $YELLOW "Backups older than ${filter_arg} days (before ${cutoff_display}):"
    fi

    echo "========================================================"

    # Helper function to check if backup matches filter
    check_backup_date() {
        local backup_date="$1"
        if [ "$filter_mode" = "range" ]; then
            is_date_in_range "$backup_date" "$start_date" "$end_date"
            return $?
        else
            # Days-based: check if older than cutoff
            local backup_epoch=$(date -u -d "$backup_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$backup_date" '+%s' 2>/dev/null)
            if [ -n "$backup_epoch" ] && [ "$backup_epoch" -lt "$cutoff_epoch" ]; then
                return 0
            fi
            return 1
        fi
    }

    print_status $GREEN "Static Site Backups:"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(extract_date_from_backup_name "$backup_name")

        if [ -n "$backup_date" ]; then
            if check_backup_date "$backup_date"; then
                echo "$line"
            fi
        fi
    done

    echo ""
    print_status $GREEN "Public Files Backups:"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(extract_date_from_backup_name "$backup_name")

        if [ -n "$backup_date" ]; then
            if check_backup_date "$backup_date"; then
                echo "$line"
            fi
        fi
    done
}

# Clean up old static and public backups based on filter criteria
# Args:
#   $1: filter_type - Type of filter (days, in-range, except-range, older-date, newer-date, all)
#   $2: filter_value - Filter value (days number, date range, or date)
clean_old_backups() {
    local filter_type="${1:-days}"
    local filter_value="${2:-7}"
    setup_s3_vars || exit 1

    # Special handling for deleting ALL backups
    if [ "$filter_type" = "all" ]; then
        print_status $YELLOW "$(show_filter_message "$filter_type" "$filter_value" "static/public backups")"

        # Clean ALL static site backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
            backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            if [ -n "$backup_name" ]; then
                print_status $YELLOW "Removing static site backup: $backup_name"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
            fi
        done

        # Clean ALL public files backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
            backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            if [ -n "$backup_name" ]; then
                print_status $YELLOW "Removing public files backup: $backup_name"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
            fi
        done

        print_status $GREEN "✅ All static/public backups removed."
        return 0
    fi

    # Display what we're doing using consolidated helper
    print_status $YELLOW "$(show_filter_message "$filter_type" "$filter_value" "static/public backups")"

    # Clean static site backups
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(extract_date_from_backup_name "$backup_name")

        if [ -n "$backup_date" ]; then
            if matches_clean_filter "$backup_date" "$filter_type" "$filter_value"; then
                print_status $YELLOW "Removing static site backup: $backup_name (date: $backup_date)"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
            fi
        fi
    done

    # Clean public files backups
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
        backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        backup_date=$(extract_date_from_backup_name "$backup_name")

        if [ -n "$backup_date" ]; then
            if matches_clean_filter "$backup_date" "$filter_type" "$filter_value"; then
                print_status $YELLOW "Removing public files backup: $backup_name (date: $backup_date)"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_name/ --recursive $S3_EXTRA_PARAMS
            fi
        fi
    done

    print_status $GREEN "✅ Static/public backup cleanup completed."
}


# Clean all backup types
cleanup_all_old_backups() {
    local filter_type=${1:-days}
    local filter_value=${2:-$BACKUP_RETENTION_DAYS}

    print_status $BLUE "🧹 Cleaning up all old automatic backups..."

    clean_old_backups "$filter_type" "$filter_value"
    cleanup_old_db_backups "$filter_type" "$filter_value"

    print_status $GREEN "✅ All backup cleanup completed."
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
    # Extract date from static backup tag (format: PREFIX-SPACE-CONTAINERTAG-YYYY-MM-DD)
    static_date=$(extract_date_from_backup_name "$static_backup_tag")

    if [ -z "$static_date" ]; then
        return 1
    fi

    # Convert to epoch for comparison
    static_epoch=$(date -u -d "$static_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$static_date" '+%s' 2>/dev/null)
    if [ -z "$static_epoch" ]; then
        return 1
    fi

    # Use a temp file to avoid subshell variable issues
    temp_list="/tmp/public_backup_search_$$"
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " > "$temp_list" 2>/dev/null

    best_public_backup=""
    best_epoch=0

    while read -r line; do
        if [ -n "$line" ]; then
            public_backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            public_date=$(extract_date_from_backup_name "$public_backup_name")

            if [ -n "$public_date" ]; then
                public_epoch=$(date -u -d "$public_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$public_date" '+%s' 2>/dev/null)

                # Find most recent backup at or before static backup time
                if [ -n "$public_epoch" ] && [ "$public_epoch" -le "$static_epoch" ] && [ "$public_epoch" -gt "$best_epoch" ]; then
                    best_public_backup="$public_backup_name"
                    best_epoch="$public_epoch"
                fi
            fi
        fi
    done < "$temp_list"

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

    # Extract date from static backup tag (format: PREFIX-SPACE-CONTAINERTAG-YYYY-MM-DD)
    static_date=$(extract_date_from_backup_name "$static_backup_tag")

    if [ -z "$static_date" ]; then
        return 1
    fi

    # Convert to epoch for comparison
    static_epoch=$(date -u -d "$static_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$static_date" '+%s' 2>/dev/null)
    if [ -z "$static_epoch" ]; then
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
    aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | awk '{print $4}' | xargs -I {} basename {} > "$temp_list" 2>/dev/null

    best_db_backup=""
    best_epoch=0

    while read -r line; do
        if [ -n "$line" ]; then
            # Extract date from database backup name (PREFIX-SPACE-CONTAINERTAG-YYYY-MM-DD.sql.gz)
            db_date=$(extract_date_from_backup_name "$line")

            if [ -n "$db_date" ]; then
                db_epoch=$(date -u -d "$db_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$db_date" '+%s' 2>/dev/null)

                # Find most recent backup at or before static backup time
                if [ -n "$db_epoch" ] && [ "$db_epoch" -le "$static_epoch" ] && [ "$db_epoch" -gt "$best_epoch" ]; then
                    best_db_backup="$line"
                    best_epoch="$db_epoch"
                fi
            fi
        fi
    done < "$temp_list"

    rm -f "$temp_list" 2>/dev/null

    if [ -n "$best_db_backup" ]; then
        echo "$best_db_backup"
        return 0
    else
        return 1
    fi
}

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
                    print_status $RED "❌ Error: --only requires a value (e.g., --only=static,public)"
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

restore_backup() {
    local backup_tag=""
    local restore_types=""
    local skip_state_management=false

    # Parse arguments
    if [ $# -eq 0 ]; then
        print_status $RED "❌ Error: Backup tag is required"
        print_status $YELLOW "⚠️ Usage: restore <backup_tag> [--only=static,public,database] [--skip-state-management|--ssm]"
        exit 1
    fi

    # Check for --skip-state-management or --ssm flag
    for arg in "$@"; do
        if [ "$arg" = "--skip-state-management" ] || [ "$arg" = "--ssm" ]; then
            skip_state_management=true
        fi
    done

    # Parse options and get backup tag
    restore_types=$(parse_restore_options "$@" 2>&1 >/dev/null | tail -n1)
    backup_tag=$(parse_restore_options "$@" 2>/dev/null | head -n1)

    if [ -z "$backup_tag" ]; then
        print_status $RED "❌ Error: Backup tag is required"
        exit 1
    fi

    setup_s3_vars || exit 1

    # Determine what to restore
    restore_static=$(echo "$restore_types" | grep -q "static" && echo "yes" || echo "no")
    restore_public=$(echo "$restore_types" | grep -q "public" && echo "yes" || echo "no")
    restore_database=$(echo "$restore_types" | grep -q "database" && echo "yes" || echo "no")

    print_status $BLUE "🔄 Checking restore options"
    echo ""

    # Find appropriate backups for each type
    static_backup_tag=""
    public_backup_tag=""
    db_backup_tag=""

    # Static site backup analysis
    if [ "$restore_static" = "yes" ]; then
        if aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
            static_backup_tag="$backup_tag"
            print_status $GREEN "✅ Static site backup found: $static_backup_tag"
        else
            print_status $RED "❌ Static site backup not found: $backup_tag"
            exit 1
        fi
    fi

    # Public files backup analysis
    if [ "$restore_public" = "yes" ]; then
        public_backup_tag=$(find_corresponding_public_backup "$backup_tag")

        if [ -n "$public_backup_tag" ]; then
            if [ "$public_backup_tag" = "$backup_tag" ]; then
                print_status $GREEN "✅ Public backup found: $public_backup_tag"
            else
                print_status $YELLOW "⚠️ Using closest public backup: $public_backup_tag"
            fi
        else
            print_status $YELLOW "⚠️ No public backup found - files will stay as-is"
        fi
    fi

    # Database backup analysis
    if [ "$restore_database" = "yes" ]; then
        db_backup_tag=$(find_corresponding_db_backup "$backup_tag")

        if [ -n "$db_backup_tag" ]; then
            # Convert to expected database tag format for comparison
            expected_db_tag="${backup_tag}.sql.gz"
            if [ "$db_backup_tag" = "$expected_db_tag" ]; then
                print_status $GREEN "✅ Database backup found: $db_backup_tag"
            else
                print_status $YELLOW "⚠️ Using closest database backup: $db_backup_tag"
            fi
        else
            print_status $YELLOW "⚠️ No database backup found - database will stay as-is"
        fi
    fi

    echo ""
    print_status $YELLOW "Restore plan:"

    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        echo "Static site:   $static_backup_tag"
    fi
    if [ "$restore_public" = "yes" ]; then
        if [ -n "$public_backup_tag" ]; then
            echo "Public files:  $public_backup_tag"
        else
            echo "Public files:  skip (no backup)"
        fi
    fi
    if [ "$restore_database" = "yes" ]; then
        if [ -n "$db_backup_tag" ]; then
            echo "Database:      $db_backup_tag"
        else
            echo "Database:      skip (no backup)"
        fi
    fi

    echo ""
    print_status $RED "This will overwrite current data!"
    printf "Continue with restore? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Cancelled."
        exit 0
    fi

    echo ""
    print_status $BLUE "🔄 Restoring..."

    # Restore static site
    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring static site..."
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$static_backup_tag/ s3://$BUCKET_NAME/web/ --delete $S3_EXTRA_PARAMS; then
            print_status $GREEN "✅ Static site restored"
        else
            print_status $RED "❌ ERROR: Static site restore failed"
            exit 1
        fi
    fi

    # Restore public files
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring public files..."
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$public_backup_tag/ s3://$BUCKET_NAME/cms/public/ --delete $S3_EXTRA_PARAMS; then
            print_status $GREEN "✅ Public files restored"
        else
            print_status $RED "❌ ERROR: Public files restore failed"
            exit 1
        fi
    fi

    # Restore database
    if [ "$restore_database" = "yes" ] && [ -n "$db_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring database..."

        # Prepare Drupal state (wait for tome, disable it, enable maintenance mode)
        local drupal_state_prepared=false
        if [ "$skip_state_management" != "true" ]; then
            if prepare_drupal_for_backup 25; then
                drupal_state_prepared=true
            else
                print_status $RED "❌ Failed to prepare Drupal state for restore"
                exit 1
            fi
        fi

        # Download and restore database backup (use secure temp files)
        temp_db_file="$(mktemp /tmp/restore_db.XXXXXX.sql.gz)"
        temp_sql_file="${temp_db_file%.gz}"

        if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_backup_tag "$temp_db_file" $S3_EXTRA_PARAMS; then
            if gunzip "$temp_db_file" 2>/dev/null; then
                if command -v drush >/dev/null 2>&1; then
                    # Use drush for database import
                    if drush sql:drop -y && drush sql:cli < "$temp_sql_file"; then
                        # Restore Drupal state before success message
                        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
                        print_status $GREEN "✅ Database restored"
                    else
                        print_status $RED "❌ ERROR: Database import failed"
                        rm -f "$temp_sql_file" 2>/dev/null
                        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
                        exit 1
                    fi
                else
                    print_status $RED "❌ ERROR: Drush not available for database restore"
                    rm -f "$temp_sql_file" 2>/dev/null
                    [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
                    exit 1
                fi
            else
                    print_status $RED "❌ ERROR: Failed to decompress database backup"
                rm -f "$temp_db_file" 2>/dev/null
                [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
                exit 1
            fi
        else
            print_status $RED "❌ ERROR: Failed to download database backup"
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state
            exit 1
        fi

        rm -f "$temp_sql_file" 2>/dev/null
    fi

    echo ""
    print_status $GREEN "🎉 Restore complete!"

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

backup_info() {
    local backup_tag=$1
    local backup_types=${2:-"all"}
    local static_exists="no"
    local public_exists="no"

    if [ -z "$backup_tag" ]; then
        print_status $RED "Error: Backup tag is required"
        exit 1
    fi

    setup_s3_vars || exit 1

    print_status $GREEN "Backup Information for: $backup_tag"
    echo "======================================"
    echo ""

    # Check static site backup
    if has_backup_type "$backup_types" "static"; then
        echo "Static Site Backup:"
        local static_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
        if echo "$static_output" | grep -q "Total Objects:"; then
            static_exists="yes"
            # Extract first file's creation date
            local first_file=$(echo "$static_output" | grep -v "Total" | head -1)
            local static_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$static_date" ]; then
                echo "  Created: $static_date"
            fi
            # Extract and format summary lines
            format_s3_summary "$static_output" | sed 's/^/  /'
        else
            echo "  No static site backup found with this tag"
        fi
        echo ""
    fi

    # Check public files backup
    if has_backup_type "$backup_types" "public"; then
        echo "Public Files Backup:"
        local public_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
        if echo "$public_output" | grep -q "Total Objects:"; then
            public_exists="yes"
            # Extract first file's creation date
            local first_file=$(echo "$public_output" | grep -v "Total" | head -1)
            local public_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$public_date" ]; then
                echo "  Created: $public_date"
            fi
            # Extract and format summary lines
            format_s3_summary "$public_output" | sed 's/^/  /'
        else
            echo "  No public files backup found with this tag"

            # If static exists but public doesn't, show the smart relationship
            if [ "$static_exists" = "yes" ]; then
                echo ""
                print_status $YELLOW "  Smart Backup Analysis:"
                echo "  This static site backup has no corresponding public files backup."
                echo "  This is normal when public files were unchanged (smart optimization)."
                echo ""

                local corresponding_public=$(find_corresponding_public_backup "$backup_tag")
                if [ -n "$corresponding_public" ]; then
                    if [ "$corresponding_public" != "$backup_tag" ]; then
                        print_status $GREEN "  Restore would use public backup: $corresponding_public"
                        echo ""
                        echo "  Public Files Backup (would be used for restore):"
                        local corr_public_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$corresponding_public/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
                        local corr_first_file=$(echo "$corr_public_output" | grep -v "Total" | head -1)
                        local corr_date=$(echo "$corr_first_file" | awk '{print $1" "$2}')
                        if [ -n "$corr_date" ]; then
                            echo "    Created: $corr_date"
                        fi
                        format_s3_summary "$corr_public_output" | sed 's/^/    /'
                    fi
                else
                    print_status $YELLOW "  No suitable public backup found for this time period."
                fi
            fi
        fi
        echo ""
    fi

    # Check database backup
    if has_backup_type "$backup_types" "db"; then
        db_backup_info "${backup_tag}.sql.gz"
        echo ""
    fi
}

# Show database backup information
db_backup_info() {
    local backup_name="$1"

    if [ -z "$backup_name" ]; then
        print_status $RED "Error: Backup name required"
        return 1
    fi

    setup_s3_vars || return 1

    echo "Database Backup:"

    if [ -n "$AWS_ACCESS_KEY_ID" ]; then
        # Look for the backup file
        local db_file_info=$(aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$backup_name")

        if [ -n "$db_file_info" ]; then
            local backup_size=$(echo "$db_file_info" | awk '{print $3}')
            local backup_date=$(echo "$db_file_info" | awk '{print $1" "$2}')
            local backup_file=$(echo "$db_file_info" | awk '{print $4}')

            local formatted_size=$(format_file_size "$backup_size")
            echo "  File: $backup_name"
            echo "  Size: $formatted_size"
            echo "  Created: $backup_date"
            echo "  Path: s3://$BUCKET_NAME/$backup_file"
        else
            echo "  No database backup found with this tag"
        fi
    else
        echo "  Error: AWS credentials not available"
    fi
}

# ===================================================================
# DOWNLOAD FUNCTIONS
# ===================================================================

# Download a backup to local disk or stream to stdout
# Can download to current machine or stream for remote download
download_backup() {
    local backup_tag=$1
    local backup_type=${2:-all}
    local output_path=$3
    local stream_mode=${4:-false}

    # Check if streaming mode (output_path is "-" or --stream flag is present)
    if [ "$output_path" = "-" ] || [ "$stream_mode" = "--stream" ] || [ "$output_path" = "--stream" ]; then
        stream_mode=true
        output_path=""
    else
        stream_mode=false
    fi

    setup_s3_vars || return 1

    # Parse backup types (handle comma-separated list)
    local types_to_download=""
    if [ "$backup_type" = "all" ]; then
        types_to_download="db,static,public"
    else
        types_to_download="$backup_type"
    fi

    # Process each type
    local failed=0
    local IFS=','
    for type in $types_to_download; do
        # Trim whitespace
        type=$(echo "$type" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

        case "$type" in
            db|static|public)
                download_single_backup "$backup_tag" "$type" "$output_path" "$stream_mode" || failed=$((failed + 1))
                ;;
            *)
                log_message "❌ Error: Invalid backup type: $type" >&2
                log_message "Valid types: db, static, public, all" >&2
                failed=$((failed + 1))
                ;;
        esac
    done

    if [ $failed -gt 0 ]; then
        log_message "⚠️  Download completed with $failed error(s)"
        return 1
    fi
    return 0
}

# Download a single backup type (internal function)
download_single_backup() {
    local backup_tag=$1
    local backup_type=$2
    local output_path=$3
    local stream_mode=$4

    case "$backup_type" in
        "db")
            # Find database backup file
            db_file=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ $S3_EXTRA_PARAMS 2>/dev/null | grep "$backup_tag" | awk '{print $4}')

            if [ -z "$db_file" ]; then
                log_message "❌ Error: Database backup not found for tag: $backup_tag" >&2
                return 1
            fi

            if [ "$stream_mode" = true ]; then
                # Stream mode: output to stdout
                log_message "📥 Streaming database backup: $db_file" >&2
                aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_file - $S3_EXTRA_PARAMS 2>/dev/null
            else
                # Local download mode - default to current working directory
                output_dir=${output_path:-$(pwd)}
                mkdir -p "$output_dir"
                output_file="$output_dir/${backup_tag}-database.sql.gz"

                log_message "📥 Downloading database backup: $db_file"
                if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_file "$output_file" $S3_EXTRA_PARAMS; then
                    log_message "✅ Database backup saved: $output_file"
                    return 0
                else
                    log_message "❌ Database backup download failed"
                    return 1
                fi
            fi
            ;;

        "static")
            # Check if static backup exists
            if ! aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                log_message "❌ Error: Static backup not found for tag: $backup_tag" >&2
                return 1
            fi

            if [ "$stream_mode" = true ]; then
                # Stream mode: create tar.gz and output to stdout
                log_message "📥 Streaming static backup: $backup_tag" >&2

                # Download to temp dir, create tar, stream, cleanup
                temp_dir=$(mktemp -d)
                aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" $S3_EXTRA_PARAMS >/dev/null 2>&1
                tar -czf - -C "$temp_dir" .
                rm -rf "$temp_dir"
            else
                # Local download mode - default to current working directory
                output_dir=${output_path:-$(pwd)}
                mkdir -p "$output_dir"
                output_file="$output_dir/${backup_tag}-static.tar.gz"

                log_message "📥 Downloading static backup: $backup_tag"

                # Download to temp dir, create tar.gz, move to output
                temp_dir=$(mktemp -d)
                if aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" $S3_EXTRA_PARAMS; then
                    tar -czf "$output_file" -C "$temp_dir" .
                    rm -rf "$temp_dir"
                    log_message "✅ Static backup saved: $output_file"
                    return 0
                else
                    rm -rf "$temp_dir"
                    log_message "❌ Static backup download failed"
                    return 1
                fi
            fi
            ;;

        "public")
            # Check if public backup exists
            if ! aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                log_message "❌ Error: Public backup not found for tag: $backup_tag" >&2
                return 1
            fi

            if [ "$stream_mode" = true ]; then
                # Stream mode: create tar.gz and output to stdout
                log_message "📥 Streaming public backup: $backup_tag" >&2

                # Download to temp dir, create tar, stream, cleanup
                temp_dir=$(mktemp -d)
                aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" $S3_EXTRA_PARAMS >/dev/null 2>&1
                tar -czf - -C "$temp_dir" .
                rm -rf "$temp_dir"
            else
                # Local download mode - default to current working directory
                output_dir=${output_path:-$(pwd)}
                mkdir -p "$output_dir"
                output_file="$output_dir/${backup_tag}-public.tar.gz"

                log_message "📥 Downloading public backup: $backup_tag"

                # Download to temp dir, create tar.gz, move to output
                temp_dir=$(mktemp -d)
                if aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" $S3_EXTRA_PARAMS; then
                    tar -czf "$output_file" -C "$temp_dir" .
                    rm -rf "$temp_dir"
                    log_message "✅ Public backup saved: $output_file"
                    return 0
                else
                    rm -rf "$temp_dir"
                    log_message "❌ Public backup download failed"
                    return 1
                fi
            fi
            ;;        *)
            log_message "❌ Error: Invalid backup type: $backup_type" >&2
            log_message "Valid types: db, static, public" >&2
            return 1
            ;;
    esac
}

# ===================================================================
# MAIN SCRIPT LOGIC
# ===================================================================

case "${1:-}" in
    "-h"|"--help"|"help")
        show_usage
        exit 0
        ;;
    "list")
        # list [types] [days] - e.g., "list static,db" or "list all 7"
        list_backups "$2" "$3"
        ;;
    "backup")
        # backup [types] [prefix] [suffix] [--skip-state-management|--ssm] - e.g., "backup db" or "backup all USAGOV-123 post-deploy"
        run_backup_command "$2" "$3" "$4" "$5"
        ;;
    "clean")
        # clean [types] [days] [-y|--non-interactive] - e.g., "clean all 30" or "clean db 7 -y"
        run_clean_command "$2" "$3" "$4"
        ;;
    "restore")
        # restore
        shift  # Remove the 'restore' command
        restore_backup "$@"  # Pass all remaining arguments
        ;;
    "info")
        # info [types] <tag> - e.g., "info db" or "info all backup-tag"
        run_info_command "$2" "$3"
        ;;
    "download")
        # download <tag> <type> [output-path] [--stream]
        # e.g., "download AUTO-prod-14850-2025-10-28 db ./backups/" or "download AUTO-prod-14850-2025-10-28 db - --stream"
        download_backup "$2" "$3" "$4" "$5"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac