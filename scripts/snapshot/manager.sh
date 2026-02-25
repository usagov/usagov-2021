#!/bin/sh

# Backup Manager
# Unified manager for all backups (static site, public files, and database)
# Handles backup creation, listing, restore, and cleanup operations

# Set restrictive permissions for all created files
umask 077

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/../common.sh"

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
BACKUP_THROTTLE_HOURS=${BACKUP_THROTTLE_HOURS:-4}

show_usage() {
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  list [types] [days|start:end]            List backups (default: all types, 30 days)"
    echo "  backup [types] [prefix] [suffix]         Create backups (default: all types, AUTO prefix)"
    echo "                [--skip-state-management|--ssm]  Use --skip-state-management (or --ssm) to skip Drupal state checks"
    echo "                [--throttle]                     Skip backup if one exists within BACKUP_THROTTLE_HOURS (default: 4)"
    echo "  clean [types] [filters] [-y]             Remove backups by date filter (default: all types, 30 days)"
    echo "  delete <tag> [tag2 tag3...] [types] [-y]    Delete specific backup(s) by tag name (default: all types)"
    echo "  restore <tag> [--only=type,type] [--skip-state-management|--ssm]  Restore backups from tag"
    echo "  info [types] [tag] [--verify] [--json]   Show backup system info or specific backup details"
  echo "                                             Use --verify to validate integrity (DB: streams from S3)"
    echo "  download <tag> [type] [path] [--stream]  Download backups (default: all types, current dir)"
    echo "  state <action> <type> [max_wait_mins]    Manage Drupal state (action: enable|disable, type: tome|sm|both)"
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
    echo "  # Delete specific backups by name"
    echo "  $0 delete TEST-dev-14913-2025-12-12-acl-test-0         # Delete all types for this tag"
    echo "  $0 delete AUTO-prod-14850-2025-10-28 static            # Delete only static backup"
    echo "  $0 delete AUTO-dev-123-2025-01-15 static,db -y         # Delete static and db (no confirm)"
    echo "  $0 delete TAG1 TAG2 TAG3 all -y                        # Delete multiple backups at once"
    echo "  "
    echo "  # Other commands"
    echo "  $0 info                                                # Show backup system configuration"
    echo "  $0 restore AUTO-prod-14850-2025-10-28                   # Restore all from backup"
    echo "  $0 download AUTO-prod-14850-2025-10-28 db ./backups/    # Download db to ./backups/"
    echo "  "
    echo "Note: Restored static files are set to public-read ACL for nginx access."
    echo "      Browser caches may take up to 15 minutes to refresh cached content."
}

# Show command-specific help
show_command_help() {
    local command="$1"

    case "$command" in
        "list")
            echo "List Backups"
            echo ""
            echo "Usage: manager.sh list [types] [days|start:end]"
            echo ""
            echo "Description:"
            echo "  List available backups by type and time range."
            echo ""
            echo "Arguments:"
            echo "  types      - Backup types: all, static, public, db, or comma-separated (default: all)"
            echo "  days       - Number of days to show (default: 30)"
            echo "  start:end  - Date range in YYYY-MM-DD:YYYY-MM-DD format"
            echo ""
            echo "Examples:"
            echo "  manager.sh list"
            echo "  manager.sh list db 7"
            echo "  manager.sh list static,public 2025-10-01:2025-10-31"
            echo ""
            ;;
        "backup")
            echo "Create Backup"
            echo ""
            echo "Usage: manager.sh backup [types] [prefix] [suffix] [options]"
            echo ""
            echo "Description:"
            echo "  Create new backups of specified types."
            echo ""
            echo "Arguments:"
            echo "  types   - Backup types: all, static, public, db, or comma-separated (default: all)"
            echo "  prefix  - Backup prefix (default: AUTO)"
            echo "  suffix  - Optional backup suffix"
            echo ""
            echo "Options:"
            echo "  --skip-state-management, --ssm  - Skip Drupal state checks"
            echo "  --throttle                      - Skip if backup exists within BACKUP_THROTTLE_HOURS"
            echo ""
            echo "Examples:"
            echo "  manager.sh backup"
            echo "  manager.sh backup db"
            echo "  manager.sh backup all USAGOV-123 post-deploy"
            echo "  manager.sh backup db AUTO '' --throttle"
            echo ""
            ;;
        "clean")
            echo "Clean Old Backups"
            echo ""
            echo "Usage: manager.sh clean [types] [filters] [-y]"
            echo ""
            echo "Description:"
            echo "  Remove backups based on retention policy or date range."
            echo ""
            echo "Arguments:"
            echo "  types    - Backup types: all, static, public, db (default: all)"
            echo "  filters  - Retention filters:"
            echo "             N (number)                  - Keep last N days"
            echo "             --older-than N              - Keep last N days"
            echo "             --in-range START:END        - Delete specific range"
            echo "  -y       - Non-interactive mode (no confirmation)"
            echo ""
            echo "Examples:"
            echo "  manager.sh clean all 7"
            echo "  manager.sh clean db --older-than 30 -y"
            echo "  manager.sh clean all --in-range 2024-01-01:2024-12-31"
            echo ""
            ;;
        "delete")
            echo "Delete Specific Backups"
            echo ""
            echo "Usage: manager.sh delete <tag> [tag2 tag3...] [types] [-y]"
            echo ""
            echo "Description:"
            echo "  Delete specific backups by tag name."
            echo ""
            echo "Arguments:"
            echo "  tag    - Backup tag(s) to delete"
            echo "  types  - Backup types to delete: all, static, public, db (default: all)"
            echo "  -y     - Non-interactive mode (no confirmation)"
            echo ""
            echo "Examples:"
            echo "  manager.sh delete AUTO-prod-14850-2025-10-28"
            echo "  manager.sh delete AUTO-prod-14850 AUTO-prod-14851 -y"
            echo "  manager.sh delete AUTO-prod-14850 db"
            echo ""
            ;;
        "restore")
            echo "Restore Backup"
            echo ""
            echo "Usage: manager.sh restore <tag> [options]"
            echo ""
            echo "Description:"
            echo "  Restore backups from specified tag."
            echo ""
            echo "Arguments:"
            echo "  tag  - Backup tag to restore"
            echo ""
            echo "Options:"
            echo "  --only=type,type              - Restore only specific types"
            echo "  --skip-state-management, --ssm - Skip Drupal state checks"
            echo ""
            echo "Examples:"
            echo "  manager.sh restore AUTO-prod-14850-2025-10-28"
            echo "  manager.sh restore AUTO-prod-14850 --only=db"
            echo "  manager.sh restore AUTO-prod-14850 --only=static,public --ssm"
            echo ""
            ;;
        "info")
            echo "Show Backup Information"
            echo ""
            echo "Usage: manager.sh info [types] [tag]"
            echo ""
            echo "Description:"
            echo "  Show backup system information or details about specific backup."
            echo ""
            echo "Arguments:"
            echo "  types  - Show info for specific types: all, static, public, db"
            echo "  tag    - Show details for specific backup tag"
            echo ""
            echo "Examples:"
            echo "  manager.sh info"
            echo "  manager.sh info db"
            echo "  manager.sh info all AUTO-prod-14850-2025-10-28"
            echo ""
            ;;
        "download")
            echo "Download Backup"
            echo ""
            echo "Usage: manager.sh download <tag> [type] [path] [--stream]"
            echo ""
            echo "Description:"
            echo "  Download backups to local filesystem or stream to stdout."
            echo ""
            echo "Arguments:"
            echo "  tag    - Backup tag to download"
            echo "  type   - Type to download: all, static, public, db (default: all)"
            echo "  path   - Output directory (default: current directory, or '-' for stdout)"
            echo "  --stream - Stream to stdout (requires path '-')"
            echo ""
            echo "Examples:"
            echo "  manager.sh download AUTO-prod-14850-2025-10-28"
            echo "  manager.sh download AUTO-prod-14850 db ./backups/"
            echo "  manager.sh download AUTO-prod-14850 db - --stream | gzip > backup.sql.gz"
            echo ""
            ;;
        "state")
            echo "Manage Drupal State"
            echo ""
            echo "Usage: manager.sh state <action> <type> [max_wait_mins]"
            echo ""
            echo "Description:"
            echo "  Enable or disable Drupal state management for backups/maintenance."
            echo ""
            echo "Arguments:"
            echo "  action         - 'enable' or 'disable'"
            echo "  type           - 'tome', 'sm' (site maintenance), or 'both' (default)"
            echo "  max_wait_mins  - Maximum minutes to wait for Tome (default: 25, only used with disable)"
            echo ""
            echo "Examples:"
            echo "  manager.sh state disable tome 30      # Disable Tome with 30 min wait"
            echo "  manager.sh state enable tome          # Re-enable Tome"
            echo "  manager.sh state disable sm           # Enable site maintenance mode"
            echo "  manager.sh state enable sm            # Disable site maintenance mode"
            echo "  manager.sh state disable both         # Disable Tome and enable maintenance"
            echo "  manager.sh state enable both          # Enable Tome and disable maintenance"
            echo ""
            ;;
        *)
            echo "No help available for command: $command"
            echo ""
            echo "Run 'manager.sh' (no args) for list of all commands"
            exit 1
            ;;
    esac
}

# ===================================================================
# ARGUMENT PARSING FUNCTIONS
# ===================================================================

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
    local custom_prefix=""
    local custom_suffix=""
    local skip_state_management=false
    local enable_throttle=false
    local use_json=false

    # Check for --json flag early
    if has_json_flag "$@"; then
        use_json=true
    fi

    # Rate limiting check - prevent backup spam (max 1 backup per 5 minutes)
    # Path is per-user (UID) so one user cannot block or spoof another's rate limit file.
    local rate_limit_file="/tmp/backup_rate_limit_$(id -u 2>/dev/null || echo 0)"
    if [ -f "$rate_limit_file" ]; then
        local last_backup=$(cat "$rate_limit_file")
        local current_time=$(date +%s)
        local time_diff=$((current_time - last_backup))
        if [ $time_diff -lt $RATE_LIMIT_SECONDS ]; then
            if [ "$use_json" = true ]; then
                local json_error="{\"status\":\"error\",\"message\":\"Rate limit exceeded\",\"wait_seconds\":$(($RATE_LIMIT_SECONDS - time_diff))}"
                format_json "$json_error"
            else
                print_status $RED "❌ Rate limit: Please wait $(($RATE_LIMIT_SECONDS - time_diff)) seconds before next backup"
            fi
            return 1
        fi
    fi

    # Update rate limit timestamp
    date +%s > "$rate_limit_file"

    # Parse all arguments to separate flags from positional params
    shift  # Remove types_arg
    while [ $# -gt 0 ]; do
        case "$1" in
            --skip-state-management|--ssm)
                skip_state_management=true
                shift
                ;;
            --throttle)
                enable_throttle=true
                shift
                ;;
            --json)
                # Already handled, just skip
                shift
                ;;
            *)
                # Positional argument - first is prefix, second is suffix
                if [ -z "$custom_prefix" ]; then
                    custom_prefix="$1"
                elif [ -z "$custom_suffix" ]; then
                    custom_suffix="$1"
                fi
                shift
                ;;
        esac
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
        if [ "$use_json" = true ]; then
            local json_error='{"status":"error","message":"Backup prefix cannot contain spaces"}'
            format_json "$json_error"
        else
            print_status $RED "❌ Error: Backup prefix cannot contain spaces"
            print_status $YELLOW "   Use hyphens or underscores instead: 'MY-PREFIX' or 'MY_PREFIX'"
        fi
        return 1
    fi
    if [ -n "$custom_suffix" ] && echo "$custom_suffix" | grep -q ' '; then
        if [ "$use_json" = true ]; then
            local json_error='{"status":"error","message":"Backup suffix cannot contain spaces"}'
            format_json "$json_error"
        else
            print_status $RED "❌ Error: Backup suffix cannot contain spaces"
            print_status $YELLOW "   Use hyphens or underscores instead: 'my-suffix' or 'my_suffix'"
        fi
        return 1
    fi

    # Validate prefix format to prevent command injection
    if ! validate_backup_tag "$backup_prefix"; then
        if [ "$use_json" = true ]; then
            local json_error='{"status":"error","message":"Invalid backup prefix format"}'
            format_json "$json_error"
        fi
        return 1
    fi
    if [ -n "$custom_suffix" ] && ! validate_backup_tag "$custom_suffix"; then
        if [ "$use_json" = true ]; then
            local json_error='{"status":"error","message":"Invalid backup suffix format"}'
            format_json "$json_error"
        fi
        return 1
    fi

    # Generate single timestamp for this backup event (format: 2025-10-24)
    local backup_timestamp=$(date +"%Y-%m-%d")

    # Initialize JSON output if needed
    local json_output=""
    if [ "$use_json" = true ]; then
        json_output='{"operation":"backup","timestamp":"'$backup_timestamp'","types":"'$backup_types'"'
        json_output="${json_output},\"prefix\":\"$backup_prefix\""
        if [ -n "$custom_suffix" ]; then
            json_output="${json_output},\"suffix\":\"$custom_suffix\""
        fi
        json_output="${json_output},\"skip_state_management\":$skip_state_management"
        json_output="${json_output},\"throttle_enabled\":$enable_throttle"
        json_output="${json_output},\"results\":{"
    else
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
    fi

    local result_count=0

    # Run static backup if requested
    if has_backup_type "$backup_types" "static"; then
        # Check throttle if enabled
        if [ "$enable_throttle" = true ]; then
            local age_hours=$(get_last_backup_age_hours "static")
            if [ "$age_hours" -lt "$BACKUP_THROTTLE_HOURS" ]; then
                if [ "$use_json" = true ]; then
                    [ $result_count -gt 0 ] && json_output="${json_output},"
                    result_count=$((result_count + 1))
                    json_output="${json_output}\"static\":{\"status\":\"skipped\",\"reason\":\"throttled\",\"age_hours\":$age_hours}"
                else
                    print_status $YELLOW "ℹ️  Skipping static backup: last backup was $age_hours hours ago (threshold: $BACKUP_THROTTLE_HOURS hours)"
                fi
            else
                if [ "$use_json" = false ]; then
                    print_status $GREEN "🌐 Backing up static site (last backup: $age_hours hours ago)..."
                fi
                create_static_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
                local backup_result=$?
                if [ "$use_json" = true ]; then
                    [ $result_count -gt 0 ] && json_output="${json_output},"
                    result_count=$((result_count + 1))
                    if [ $backup_result -eq 0 ]; then
                        json_output="${json_output}\"static\":{\"status\":\"success\",\"tag\":\"$STATIC_BACKUP_TAG\"}"
                    else
                        json_output="${json_output}\"static\":{\"status\":\"failed\"}"
                    fi
                fi
            fi
        else
            if [ "$use_json" = false ]; then
                print_status $GREEN "🌐 Backing up static site..."
            fi
            create_static_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
            local backup_result=$?
            if [ "$use_json" = true ]; then
                [ $result_count -gt 0 ] && json_output="${json_output},"
                result_count=$((result_count + 1))
                if [ $backup_result -eq 0 ]; then
                    json_output="${json_output}\"static\":{\"status\":\"success\",\"tag\":\"$STATIC_BACKUP_TAG\"}"
                else
                    json_output="${json_output}\"static\":{\"status\":\"failed\"}"
                fi
            fi
        fi
    fi

    # Run public backup if requested
    if has_backup_type "$backup_types" "public"; then
        # Check throttle if enabled
        if [ "$enable_throttle" = true ]; then
            local age_hours=$(get_last_backup_age_hours "public")
            if [ "$age_hours" -lt "$BACKUP_THROTTLE_HOURS" ]; then
                if [ "$use_json" = true ]; then
                    [ $result_count -gt 0 ] && json_output="${json_output},"
                    result_count=$((result_count + 1))
                    json_output="${json_output}\"public\":{\"status\":\"skipped\",\"reason\":\"throttled\",\"age_hours\":$age_hours}"
                else
                    print_status $YELLOW "ℹ️  Skipping public backup: last backup was $age_hours hours ago (threshold: $BACKUP_THROTTLE_HOURS hours)"
                fi
            else
                if [ "$use_json" = false ]; then
                    print_status $GREEN "📁 Backing up public files (last backup: $age_hours hours ago)..."
                fi
                create_public_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
                local backup_result=$?
                if [ "$use_json" = true ]; then
                    [ $result_count -gt 0 ] && json_output="${json_output},"
                    result_count=$((result_count + 1))
                    if [ $backup_result -eq 0 ]; then
                        json_output="${json_output}\"public\":{\"status\":\"success\",\"tag\":\"$PUBLIC_BACKUP_TAG\"}"
                    else
                        json_output="${json_output}\"public\":{\"status\":\"failed\"}"
                    fi
                fi
            fi
        else
            if [ "$use_json" = false ]; then
                print_status $GREEN "📁 Backing up public files..."
            fi
            create_public_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
            local backup_result=$?
            if [ "$use_json" = true ]; then
                [ $result_count -gt 0 ] && json_output="${json_output},"
                result_count=$((result_count + 1))
                if [ $backup_result -eq 0 ]; then
                    json_output="${json_output}\"public\":{\"status\":\"success\",\"tag\":\"$PUBLIC_BACKUP_TAG\"}"
                else
                    json_output="${json_output}\"public\":{\"status\":\"failed\"}"
                fi
            fi
        fi
    fi

    # Run database backup if requested
    if has_backup_type "$backup_types" "db"; then
        if [ "$use_json" = false ]; then
            print_status $GREEN "💾 Backing up database..."
        fi
        create_db_backup "$backup_prefix" "$backup_suffix" "$backup_timestamp" "$skip_state_management"
        local backup_result=$?
        if [ "$use_json" = true ]; then
            [ $result_count -gt 0 ] && json_output="${json_output},"
            result_count=$((result_count + 1))
            if [ $backup_result -eq 0 ]; then
                json_output="${json_output}\"database\":{\"status\":\"success\",\"tag\":\"$DB_BACKUP_TAG\"}"
            else
                json_output="${json_output}\"database\":{\"status\":\"failed\"}"
            fi
        fi
    fi

    if [ "$use_json" = true ]; then
        json_output="${json_output}},\"status\":\"complete\"}"
        format_json "$json_output"
    else
        print_status $BLUE "🎉 Done."
    fi
}

# Handle clean command
run_clean_command() {
    local types_arg="${1:-all}"
    shift || true

    local non_interactive=false
    local filter_type=""
    local filter_value=""
    local filter_count=0
    local use_json=false

    # Check for --json flag early
    if has_json_flag "$@"; then
        use_json=true
        non_interactive=true  # JSON mode implies non-interactive
    fi

    # Parse all arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --non-interactive|-y)
                non_interactive=true
                shift
                ;;
            --json)
                # Already handled, just skip
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
        if [ "$use_json" = true ]; then
            local json_error='{"status":"error","message":"Cannot mix multiple date filtering methods"}'
            format_json "$json_error"
        else
            print_status $RED "❌ Error: Cannot mix multiple date filtering methods"
            echo "   Use only ONE of: days, --older-than, --in-range, --except-range, --older-than-date, --newer-than-date"
        fi
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
        if [ "$non_interactive" != "true" ]; then
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
        if [ "$non_interactive" != "true" ]; then
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

            # Only prompt if not in non-interactive mode
            if [ "$non_interactive" != "true" ]; then
                printf "Continue? [y/N]: "
                read -r confirm

                if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
                    print_status $RED "❌ Cancelled."
                    return 1
                fi
            fi
        fi

        # Show what we're doing in non-interactive mode
        if [ "$non_interactive" = "true" ]; then
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

    # Initialize JSON output if needed
    local json_output=""
    if [ "$use_json" = true ]; then
        json_output='{"operation":"clean","filter":{"type":"'$filter_type'","value":"'$filter_value'"},"types":"'$(parse_backup_types "$types_arg")'"'
    else
        print_status $BLUE "🧹 Cleaning up backups..."
    fi

    # Clean static and public backups if requested
    if has_backup_type "$(parse_backup_types "$types_arg")" "static" || has_backup_type "$(parse_backup_types "$types_arg")" "public"; then
        clean_old_backups "$filter_type" "$filter_value"
    fi

    # Clean database backups if requested
    if has_backup_type "$(parse_backup_types "$types_arg")" "db"; then
        cleanup_old_db_backups "$filter_type" "$filter_value"
    fi

    if [ "$use_json" = true ]; then
        json_output="${json_output},\"status\":\"complete\"}"
        format_json "$json_output"
    else
        print_status $BLUE "🎉 Cleanup complete."
    fi
}

# Handle info command
run_info_command() {
    local types_arg="${1:-all}"
    local tag="${2:-}"

    # Check for --json flag in remaining arguments
    shift 2 2>/dev/null
    if has_json_flag "$@"; then
        run_info_command_json "$types_arg" "$tag" "$@"
        return $?
    fi

    local backup_types=$(parse_backup_types "$types_arg")

    if [ -n "$tag" ]; then
        # Show info for specific tag with requested types
        backup_info "$tag" "$backup_types" "$@"
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

# JSON version of run_info_command
run_info_command_json() {
    local types_arg="${1:-all}"
    local tag="${2:-}"
    shift 2 2>/dev/null

    local backup_types=$(parse_backup_types "$types_arg")

    if [ -n "$tag" ]; then
        # Show info for specific tag with requested types
        backup_info_json "$tag" "$backup_types" "$@"
    else
        # Show general backup system configuration
        setup_s3_vars || exit 1

        local json_output='{"system":{'
        json_output="${json_output}\"bucket\":\"$BUCKET_NAME\""
        json_output="${json_output},\"config_file\":\"$CONFIG_FILE\""
        json_output="${json_output},\"backup_types\":{"

        local type_count=0

        if has_backup_type "$backup_types" "static"; then
            [ $type_count -gt 0 ] && json_output="${json_output},"
            type_count=$((type_count + 1))
            json_output="${json_output}\"static\":{"
            json_output="${json_output}\"path\":\"$AUTO_STATIC_BACKUP_PATH\""
            json_output="${json_output},\"prefix\":\"$BACKUP_PREFIX\""
            json_output="${json_output},\"retention_days\":$BACKUP_RETENTION_DAYS"
            json_output="${json_output}}"
        fi

        if has_backup_type "$backup_types" "public"; then
            [ $type_count -gt 0 ] && json_output="${json_output},"
            type_count=$((type_count + 1))
            json_output="${json_output}\"public\":{"
            json_output="${json_output}\"path\":\"$AUTO_PUBLIC_BACKUP_PATH\""
            json_output="${json_output},\"prefix\":\"$BACKUP_PREFIX\""
            json_output="${json_output},\"retention_days\":$BACKUP_RETENTION_DAYS"
            json_output="${json_output}}"
        fi

        if has_backup_type "$backup_types" "db"; then
            [ $type_count -gt 0 ] && json_output="${json_output},"
            type_count=$((type_count + 1))
            json_output="${json_output}\"database\":{"
            json_output="${json_output}\"path\":\"$AUTO_DB_BACKUP_PATH\""
            json_output="${json_output},\"prefix\":\"$DB_BACKUP_PREFIX\""
            json_output="${json_output},\"retention_days\":$DB_BACKUP_RETENTION_DAYS"
            json_output="${json_output}}"
        fi

        json_output="${json_output}}}"

        format_json "$json_output"
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

    # Prepare Drupal state (enable maintenance mode only for DB backups)
    local drupal_state_prepared=false
    if [ "$skip_state_management" != "true" ]; then
        if prepare_drupal_state "maintenance" 25; then
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
    if [ -n "$backup_suffix" ]; then
        DB_BACKUP_TAG="${base_tag}-${backup_suffix}-${numeric_suffix}"
    else
        DB_BACKUP_TAG="${base_tag}-${numeric_suffix}"
    fi

    # Validate final backup tag
    if ! validate_backup_tag "$DB_BACKUP_TAG"; then
        print_status $RED "❌ Invalid backup tag generated"
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    audit_log "backup_database_started" "info" "Database backup initiated" "backup_tag=$DB_BACKUP_TAG"
    log_message "💾 Database backup: $DB_BACKUP_TAG"

    # Setup log file
    LOG_DIR="/tmp/tome-log"
    mkdir -p "$LOG_DIR"
    chmod 700 "$LOG_DIR"
    LOGFILE="$LOG_DIR/db-backup-${backup_timestamp}.log"

    log_message "🔄 Dumping database..." | tee -a "$LOGFILE"

    # Set working directory for drush
    local original_dir=$(pwd)
    if [ -n "$VCAP_APPLICATION" ] && [ -d "/var/www" ]; then
        if ! cd /var/www 2>/dev/null; then
            log_message "❌ ERROR: Cannot change to /var/www directory" | tee -a "$LOGFILE"
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
            return 1
        fi
    elif [ "$PROJECT_ROOT" != "$original_dir" ]; then
        # Change to project root if not already there
        if ! cd "$PROJECT_ROOT" 2>/dev/null; then
            log_message "❌ ERROR: Cannot change to project root: $PROJECT_ROOT" | tee -a "$LOGFILE"
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
            return 1
        fi
    fi

    # Use secure temp files with mktemp (compatible with both GNU and BSD)
    TEMP_SQL=$(mktemp) && mv "$TEMP_SQL" "${TEMP_SQL}.sql" && TEMP_SQL="${TEMP_SQL}.sql"
    TEMP_GZIP=$(mktemp) && mv "$TEMP_GZIP" "${TEMP_GZIP}.sql.gz" && TEMP_GZIP="${TEMP_GZIP}.sql.gz"
    TEMP_CHECKSUM=$(mktemp) && mv "$TEMP_CHECKSUM" "${TEMP_CHECKSUM}.sha256" && TEMP_CHECKSUM="${TEMP_CHECKSUM}.sha256"
    chmod 600 "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM"

    # Ensure cleanup on exit
    trap "rm -f '$TEMP_SQL' '$TEMP_GZIP' '$TEMP_CHECKSUM'" EXIT INT TERM

    # Create database dump using drush
    if command -v drush >/dev/null 2>&1; then
        # Clear cache first, then create dump to SQL file
        drush cr 2>&1 | tee -a "$LOGFILE"
        drush sql:dump --result-file="$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"
        DUMP_EXIT_CODE=$?
        if [ $DUMP_EXIT_CODE -eq 0 ] && [ -f "$TEMP_SQL" ] && [ -s "$TEMP_SQL" ]; then
            log_message "✅ Database dump created ($(du -h "$TEMP_SQL" | cut -f1))" | tee -a "$LOGFILE"
        fi
    else
        log_message "❌ ERROR: drush not found" | tee -a "$LOGFILE"
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    if [ $DUMP_EXIT_CODE -ne 0 ]; then
        log_message "❌ ERROR: Database dump failed" | tee -a "$LOGFILE"
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Verify the SQL dump file was created and has content
    if [ ! -f "$TEMP_SQL" ] || [ ! -s "$TEMP_SQL" ]; then
        audit_log "backup_database_failed" "error" "Database dump empty or missing" "backup_tag=$DB_BACKUP_TAG"
        log_message "❌ ERROR: Database dump empty or missing" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Validate SQL dump structure
    log_message "🔍 Validating SQL dump structure..." | tee -a "$LOGFILE"
    if ! validate_sql_dump "$TEMP_SQL" 2>&1 | tee -a "$LOGFILE"; then
        audit_log "backup_database_failed" "error" "SQL dump validation failed" "backup_tag=$DB_BACKUP_TAG reason=invalid_structure"
        log_message "❌ ERROR: SQL dump validation failed" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Compress the SQL file using gzip
    log_message "🗜️ Compressing..." | tee -a "$LOGFILE"
    gzip -c "$TEMP_SQL" > "$TEMP_GZIP" 2>&1 | tee -a "$LOGFILE"
    GZIP_EXIT_CODE=$?

    # Remove uncompressed file
    rm -f "$TEMP_SQL"

    if [ $GZIP_EXIT_CODE -ne 0 ]; then
        log_message "❌ ERROR: Compression failed" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Verify the compressed file was created
    if [ ! -f "$TEMP_GZIP" ] || [ ! -s "$TEMP_GZIP" ]; then
        log_message "❌ ERROR: Compressed file empty or missing" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Generate SHA-256 checksum for integrity verification
    log_message "🔐 Generating checksum..." | tee -a "$LOGFILE"
    sha256sum "$TEMP_GZIP" | awk '{print $1}' > "$TEMP_CHECKSUM"
    if [ ! -s "$TEMP_CHECKSUM" ]; then
        log_message "⚠️ Warning: Could not generate checksum" | tee -a "$LOGFILE"
    fi

    # Upload compressed file to S3
    log_message "☁️ Uploading..." | tee -a "$LOGFILE"

    S3_DB_PATH="s3://${BUCKET_NAME}/${AUTO_DB_BACKUP_PATH}/${DB_BACKUP_TAG}.sql.gz"
    log_message "📍 Target: $S3_DB_PATH" | tee -a "$LOGFILE"

    aws s3 cp "$TEMP_GZIP" "$S3_DB_PATH" --only-show-errors 2>&1 | tee -a "$LOGFILE"
    UPLOAD_EXIT_CODE=$?

    # Upload checksum file if it exists
    if [ -s "$TEMP_CHECKSUM" ]; then
        S3_CHECKSUM_PATH="s3://${BUCKET_NAME}/${AUTO_DB_BACKUP_PATH}/${DB_BACKUP_TAG}.sql.gz.sha256"
        aws s3 cp "$TEMP_CHECKSUM" "$S3_CHECKSUM_PATH" --only-show-errors 2>&1 | tee -a "$LOGFILE"
    fi

    rm -f "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM" 2>/dev/null
    cleanup_needed=false  # Disable cleanup trap since we cleaned up manually

    # Restore Drupal state before checking results
    if [ "$drupal_state_prepared" = "true" ]; then
        restore_drupal_state "maintenance"  # Disable maintenance mode
    fi

    if [ $UPLOAD_EXIT_CODE -eq 0 ]; then
        audit_log "backup_database_success" "success" "Database backup completed and uploaded" "backup_tag=$DB_BACKUP_TAG s3_path=$S3_DB_PATH"
        log_message "✅ Database backup complete: $S3_DB_PATH" | tee -a "$LOGFILE"
        print_status $GREEN "✅ Database backup saved: $DB_BACKUP_TAG"

        # Upload log to S3
        if [ -f "$LOGFILE" ]; then
            aws s3 cp "$LOGFILE" "s3://$BUCKET_NAME/db-backup-logs/$(basename "$LOGFILE")" $S3_EXTRA_PARAMS >/dev/null 2>&1
        fi

        # Capture and upload deployment metadata (REQUIRED for all backups)
        if command -v capture_deployment_metadata >/dev/null 2>&1; then
            local metadata=$(capture_deployment_metadata "$DB_BACKUP_TAG" "$APP_SPACE")
            if ! upload_deployment_metadata "$DB_BACKUP_TAG" "$metadata"; then
                log_message "❌ ERROR: Failed to upload metadata" | tee -a "$LOGFILE"
                print_status $RED "❌ Backup metadata upload failed"
                return 1
            fi
        fi

        trap - EXIT ERR  # Clear trap before successful return
        return 0
    else
        audit_log "backup_database_failed" "error" "Database backup upload failed" "backup_tag=$DB_BACKUP_TAG exit_code=$UPLOAD_EXIT_CODE"
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
    local skip_state_management="${4:-false}"

    setup_s3_vars || exit 1

    if [ "$ENABLE_STATIC_AUTO_BACKUPS" != "true" ]; then
        log_message "⚠️ Static site backups disabled"
        return 0
    fi

    # Prepare Drupal state (disable Tome)
    local drupal_state_prepared=false
    if [ "$skip_state_management" != "true" ]; then
        if prepare_drupal_state "tome" 25; then
            drupal_state_prepared=true
        else
            print_status $RED "❌ Failed to prepare Drupal state"
            return 1
        fi
    fi

    # Generate base backup tag
    CONTAINER_TAG=$(get_container_tag)
    local base_tag="${custom_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"

    # Get next available numeric suffix for same-day backups
    local numeric_suffix=$(get_next_backup_suffix "static" "$base_tag")

    # Construct final tag with user suffix (if any) and numeric suffix
    if [ -n "$backup_suffix" ]; then
        BACKUP_TAG="${base_tag}-${backup_suffix}-${numeric_suffix}"
    else
        BACKUP_TAG="${base_tag}-${numeric_suffix}"
    fi

    audit_log "backup_static_started" "info" "Static site backup initiated" "backup_tag=$BACKUP_TAG"
    log_message "🌐 Creating static site backup: $BACKUP_TAG"

    # Note: S3_EXTRA_PARAMS may contain multiple parameters, so we don't quote it
    if aws s3 cp --only-show-errors s3://$BUCKET_NAME/web/ s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS 2>&1; then
        local exit_code=$?
        if [ $exit_code -eq 0 ]; then
            # Restore Drupal state before returning
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

            audit_log "backup_static_success" "success" "Static site backup completed" "backup_tag=$BACKUP_TAG"
            print_status $GREEN "✅ Static site backed up: $BACKUP_TAG"

            # Capture and upload deployment metadata (REQUIRED for all backups)
            if command -v capture_deployment_metadata >/dev/null 2>&1; then
                local metadata=$(capture_deployment_metadata "$BACKUP_TAG" "$APP_SPACE")
                if ! upload_deployment_metadata "$BACKUP_TAG" "$metadata"; then
                    print_status $RED "❌ Static backup metadata upload failed"
                    return 1
                fi
            fi

            return 0
        else
            # Restore Drupal state before returning error
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

            audit_log "backup_static_failed" "error" "Static site backup failed" "backup_tag=$BACKUP_TAG exit_code=$exit_code"
            print_status $RED "❌ Static site backup failed with exit code: $exit_code"
            return 1
        fi
    else
        # Restore Drupal state before returning error
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

        audit_log "backup_static_failed" "error" "Static site backup failed" "backup_tag=$BACKUP_TAG"
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
    local skip_state_management="${4:-false}"

    setup_s3_vars || exit 1

    if [ "$ENABLE_PUBLIC_AUTO_BACKUPS" != "true" ]; then
        log_message "⚠️ Public files backups disabled"
        return 0
    fi

    # Prepare Drupal state (disable Tome)
    local drupal_state_prepared=false
    if [ "$skip_state_management" != "true" ]; then
        if prepare_drupal_state "tome" 25; then
            drupal_state_prepared=true
        else
            print_status $RED "❌ Failed to prepare Drupal state"
            return 1
        fi
    fi

    # Generate base backup tag
    CONTAINER_TAG=$(get_container_tag)
    local base_tag="${custom_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"

    # Get next available numeric suffix for same-day backups
    local numeric_suffix=$(get_next_backup_suffix "public" "$base_tag")

    # Construct final tag with user suffix (if any) and numeric suffix
    if [ -n "$backup_suffix" ]; then
        BACKUP_TAG="${base_tag}-${backup_suffix}-${numeric_suffix}"
    else
        BACKUP_TAG="${base_tag}-${numeric_suffix}"
    fi

    # Smart backup check if enabled
    PUBLIC_BACKUP_NEEDED=true

    if [ "$ENABLE_SMART_PUBLIC_BACKUP" = "true" ]; then
        local loader=$(show_loading "Checking if public files backup needed")

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
        audit_log "backup_public_started" "info" "Public files backup initiated" "backup_tag=$BACKUP_TAG"
        log_message "📁 Creating public files backup: $BACKUP_TAG"
        # Note: S3_EXTRA_PARAMS may contain multiple parameters, so we don't quote it
        if aws s3 cp --only-show-errors s3://$BUCKET_NAME/cms/public/ s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$BACKUP_TAG/ --recursive $S3_EXTRA_PARAMS 2>&1; then
            local exit_code=$?
            if [ $exit_code -eq 0 ]; then
                # Restore Drupal state before returning
                [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

                audit_log "backup_public_success" "success" "Public files backup completed" "backup_tag=$BACKUP_TAG"
                print_status $GREEN "✅ Public files backed up: $BACKUP_TAG"

                # Capture and upload deployment metadata
                if command -v capture_deployment_metadata >/dev/null 2>&1; then
                    local metadata=$(capture_deployment_metadata "$BACKUP_TAG" "$APP_SPACE")
                    upload_deployment_metadata "$BACKUP_TAG" "$metadata" || log_message "⚠️ Failed to upload metadata (non-critical)"
                fi

                return 0
            else
                # Restore Drupal state before returning error
                [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

                audit_log "backup_public_failed" "error" "Public files backup failed" "backup_tag=$BACKUP_TAG exit_code=$exit_code"
                print_status $RED "❌ Public files backup failed with exit code: $exit_code"
                return 1
            fi
        else
            # Restore Drupal state before returning error
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

            audit_log "backup_public_failed" "error" "Public files backup failed" "backup_tag=$BACKUP_TAG"
            print_status $RED "❌ Public files backup failed: $BACKUP_TAG"
            return 1
        fi
    else
        # Restore Drupal state even if backup was skipped
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "tome"

        audit_log "backup_public_skipped" "info" "Public files unchanged, backup skipped" "backup_tag=$BACKUP_TAG"
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

# Get age in hours of the most recent backup for a given type
# Args:
#   $1: backup_type - Type of backup to check (static, public, db)
# Returns:
#   Age in hours of most recent backup, or 999 if no backups exist
get_last_backup_age_hours() {
    local backup_type="$1"
    setup_s3_vars || return 999

    local s3_path=""
    case "$backup_type" in
        static)
            s3_path="s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/"
            ;;
        public)
            s3_path="s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/"
            ;;
        db)
            s3_path="s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/"
            ;;
        *)
            return 999
            ;;
    esac

# Get the most recent AUTO backup for the current space
    # Filter by BACKUP_PREFIX-APP_SPACE to avoid picking up manual or other space backups
    local backup_filter="${BACKUP_PREFIX}-${APP_SPACE}-"
    local last_backup_name=""
    local last_modified=""

    if [ "$backup_type" = "db" ]; then
        # For database backups, look for .sql.gz files with matching prefix and get timestamp directly
        last_modified=$(aws s3 ls "$s3_path" $S3_EXTRA_PARAMS 2>/dev/null | grep '\.sql\.gz$' | grep "$backup_filter" | sort -r | head -n 1 | awk '{print $1, $2}')
    else
        # For static/public, directories don't show timestamps in aws s3 ls
        # We need to get the directory name, then list files inside it recursively
        # Filter by prefix-space to only get relevant backups
        last_backup_name=$(aws s3 ls "$s3_path" $S3_EXTRA_PARAMS 2>/dev/null | grep 'PRE' | grep "$backup_filter" | awk '{print $2}' | tr -d '/' | sort -r | head -n 1)

        if [ -n "$last_backup_name" ]; then
            # Get timestamp from first file in the backup directory (use --recursive to find files in subdirectories)
            last_modified=$(aws s3 ls "${s3_path}${last_backup_name}/" $S3_EXTRA_PARAMS --recursive 2>/dev/null | head -n 1 | awk '{print $1, $2}')
        fi
    fi

    if [ -z "$last_modified" ]; then
        # No backups found or no files in backup
        echo 999
        return 0
    fi

    # Parse the date and time from S3 ls output (format: "2025-12-18 14:30:00")
    # Convert to epoch seconds
    local backup_epoch=$(date -d "$last_modified" +%s 2>/dev/null || date -j -f "%Y-%m-%d %H:%M:%S" "$last_modified" +%s 2>/dev/null)
    local current_epoch=$(date +%s)

    if [ -z "$backup_epoch" ]; then
        # Date parsing failed
        echo 999
        return 0
    fi

    # Calculate age in hours
    local age_seconds=$((current_epoch - backup_epoch))
    local age_hours=$((age_seconds / 3600))

    echo "$age_hours"
}

list_backups() {
    local types_arg="${1:-all}"
    local filter_arg="${2:-}"
    local use_json=false

    # Check for --json flag in all arguments
    shift 2 2>/dev/null  # Remove first two args
    if has_json_flag "$@"; then
        use_json=true
    fi

    local backup_types=$(parse_backup_types "$types_arg")

    # If a filter argument (days or date range) is provided, filter the listing.
    # A plain number N means "last N days" (backups from N days ago to now).
    # A colon-containing value is a date range passed through as-is.
    if [ -n "$filter_arg" ]; then
        local range_arg="$filter_arg"
        if echo "$filter_arg" | grep -qE '^[0-9]+$'; then
            # Convert N days to a forward date range: "{N_days_ago}:" = from that date onward
            local since_date
            since_date=$(date -u -d "$filter_arg days ago" '+%Y-%m-%d' 2>/dev/null || \
                         date -u -v-${filter_arg}d '+%Y-%m-%d' 2>/dev/null)
            if [ -z "$since_date" ]; then
                print_status $RED "❌ Error: could not compute date for '$filter_arg days ago'"
                return 1
            fi
            range_arg="${since_date}:"
        fi
        if [ "$use_json" = true ]; then
            list_old_backups "$range_arg" --json
        else
            list_old_backups "$range_arg"
        fi
        return 0
    fi

    # If no specific types requested, show all backups with restore tags
    if [ "$types_arg" = "all" ] || [ -z "$types_arg" ]; then
        if [ "$use_json" = true ]; then
            list_all_backups_json
        else
            list_all_backups
        fi
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

    local loader=$(show_loading "Loading backup list")
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

        # Stop loader on first iteration (static backups)
        if [ -n "$loader" ]; then
            loader=""
        fi

        local formatted_size=$(format_file_size "$backup_size")
        echo "  $backup_tag ($formatted_size) - $backup_date"
    done
}

list_public_backups() {
    setup_s3_vars || exit 1

    print_status $GREEN "Public Files Backups:"
    echo "====================="

    local loader=$(show_loading "Loading backup list")
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

        # Stop loader on first iteration (public backups)
        if [ -n "$loader" ]; then
            loader=""
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
        local loader=$(show_loading "Loading backup list")
        aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | sort -r | while read -r line; do
            # Extract backup name from S3 listing
            backup_file=$(echo "$line" | awk '{print $4}' | xargs basename)
            backup_size=$(echo "$line" | awk '{print $3}')
            backup_date=$(echo "$line" | awk '{print $1" "$2}')

            # Stop loader on first iteration (db backups)
            if [ -n "$loader" ]; then
                loader=""
            fi

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

# JSON version of list_all_backups
list_all_backups_json() {
    setup_s3_vars || exit 1

    # Create temporary files to collect backup data
    static_list="/tmp/static_backups_$$"
    public_list="/tmp/public_backups_$$"
    db_list="/tmp/db_backups_$$"

    # Get all backup lists
    aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | awk '{print $2}' | tr -d '/' | sort > "$static_list" 2>/dev/null
    aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | awk '{print $2}' | tr -d '/' | sort > "$public_list" 2>/dev/null
    aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | awk '{print $4}' | xargs -I {} basename {} | sort > "$db_list" 2>/dev/null

    # Create unified list of all backup tags
    all_tags="/tmp/all_backup_tags_$$"
    (
        cat "$static_list" 2>/dev/null
        cat "$public_list" 2>/dev/null
        cat "$db_list" 2>/dev/null | sed 's/\.sql\.gz$//'
    ) | sort -u > "$all_tags"

    # Build JSON array
    local json_output='{"backups":['
    local first=true
    local count=0

    while read -r tag; do
        if [ -n "$tag" ]; then
            count=$((count + 1))

            # Check what backup types exist for this tag
            local has_static=false
            local has_public=false
            local has_database=false

            if grep -q "^$tag$" "$static_list" 2>/dev/null; then
                has_static=true
            fi

            if grep -q "^$tag$" "$public_list" 2>/dev/null; then
                has_public=true
            fi

            local db_tag="${tag}.sql.gz"
            if grep -q "^$db_tag$" "$db_list" 2>/dev/null; then
                has_database=true
            fi

            # Extract date from tag
            local tag_date=$(extract_date_from_backup_name "$tag")
            local days_ago=""
            if [ -n "$tag_date" ]; then
                local tag_epoch=$(date -d "$tag_date" +%s 2>/dev/null || date -j -f "%Y-%m-%d" "$tag_date" +%s 2>/dev/null)
                if [ -n "$tag_epoch" ]; then
                    days_ago=$(( ($(date +%s) - tag_epoch) / 86400 ))
                fi
            fi

            if [ "$first" = true ]; then
                first=false
            else
                json_output="${json_output},"
            fi

            json_output="${json_output}{\"tag\":\"$tag\",\"static\":$has_static,\"public\":$has_public,\"database\":$has_database,\"date\":\"${tag_date:-unknown}\""
            if [ -n "$days_ago" ]; then
                json_output="${json_output},\"age_days\":$days_ago"
            fi
            json_output="${json_output},\"restore_command\":\"restore $tag\"}"
        fi
    done < "$all_tags"

    json_output="${json_output}],\"count\":$count,\"bucket\":\"$BUCKET_NAME\"}"

    rm -f "$static_list" "$public_list" "$db_list" "$all_tags" 2>/dev/null

    format_json "$json_output"
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

    # Reject 'all' and '0' - use delete command for bulk deletion
    if [ "$filter_type" = "all" ] || [ "$filter_value" = "0" ] || [ "$filter_value" = "all" ]; then
        log_message "❌ Error: Minimum retention is 2 days ($RETENTION_MIN_HOURS hours)"
        log_message "   To delete all backups, clean all but 2 days worth and"
        log_message "   use the 'delete' command instead to remove remaining backups."
        log_message "   This prevents accidental deletion of recent backups."
        return 1
    fi

    # Validate minimum retention to protect deployment windows
    if [ "$filter_type" = "days" ]; then
        if [ "$filter_value" -lt 2 ]; then
            log_message "❌ Error: Minimum retention is 2 days ($RETENTION_MIN_HOURS hours)"
            return 1
        fi
    fi

    # Display what we're doing using consolidated helper
    audit_log "cleanup_database_started" "info" "Database cleanup initiated" "filter_type=$filter_type filter_value=$filter_value"
    log_message "$(show_filter_message "$filter_type" "$filter_value" "database backups")"

    # Calculate minimum retention cutoff
    local min_retention_epoch=$(date -u -v-${RETENTION_MIN_HOURS}H +%s 2>/dev/null || date -u -d "${RETENTION_MIN_HOURS} hours ago" +%s 2>/dev/null)
    if [ -z "$min_retention_epoch" ]; then
        log_message "❌ Error: Could not calculate minimum retention date"
        return 1
    fi

    # List and delete database backups matching filter
    aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS 2>/dev/null | grep "\.sql\.gz$" | while read -r line; do
        backup_path=$(echo "$line" | awk '{print $4}')
        backup_date=$(extract_date_from_backup_name "$backup_path")

        if [ -n "$backup_date" ]; then
            # Convert backup date to epoch for comparison
            local backup_epoch=$(date_to_epoch "$backup_date")

            # Skip if backup is newer than minimum retention (safety check)
            if [ -n "$backup_epoch" ] && [ "$backup_epoch" -gt "$min_retention_epoch" ]; then
                log_message "⏭️  Skipping recent backup (< $RETENTION_MIN_HOURS hours): $backup_path"
                continue
            fi

            if matches_clean_filter "$backup_date" "$filter_type" "$filter_value"; then
                audit_log "backup_database_deleted" "info" "Database backup deleted" "backup_path=$backup_path backup_date=$backup_date"
                log_message "🗑️ Removing old database backup: $backup_path (date: $backup_date)"
                aws s3 rm "s3://$BUCKET_NAME/$backup_path" $S3_EXTRA_PARAMS 2>&1
            fi
        fi
    done
    audit_log "cleanup_database_success" "success" "Database cleanup completed" "filter_type=$filter_type filter_value=$filter_value"
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
    local filter_value="${2:-$BACKUP_RETENTION_DAYS}"
    setup_s3_vars || exit 1

    # Special handling for deleting ALL backups
    if [ "$filter_type" = "all" ]; then
        print_status $YELLOW "$(show_filter_message "$filter_type" "$filter_value" "static/public backups")"

        # Clean ALL static site backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
            backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            if [ -n "$backup_name" ]; then
                print_status $YELLOW "Removing static site backup: $backup_name"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_name/ --only-show-errors --recursive $S3_EXTRA_PARAMS
            fi
        done

        # Clean ALL public files backups
        aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE " | while read -r line; do
            backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
            if [ -n "$backup_name" ]; then
                print_status $YELLOW "Removing public files backup: $backup_name"
                aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_name/ --only-show-errors --recursive $S3_EXTRA_PARAMS
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
                aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_name/ --only-show-errors --recursive $S3_EXTRA_PARAMS
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
                aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_name/ --only-show-errors --recursive $S3_EXTRA_PARAMS
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

# Delete a specific backup by tag name (supports multiple tags)
# Args: Space-separated tags, followed by optional types and flags
#   tags - One or more backup tags to delete
#   types - Backup types to delete (default: all)
#   -y|--non-interactive - Flag to skip confirmation
delete_backup() {
    # Parse arguments to separate tags from types and flags
    local tags=""
    local types_arg="all"
    local non_interactive=""

    # Collect all arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            -y|--non-interactive)
                non_interactive="$1"
                shift
                ;;
            static|public|db|all|*,*)
                # This looks like a types argument (single type or comma-separated)
                types_arg="$1"
                shift
                ;;
            *)
                # Assume it's a tag - append to space-separated list
                if [ -z "$tags" ]; then
                    tags="$1"
                else
                    tags="$tags $1"
                fi
                shift
                ;;
        esac
    done

    if [ -z "$tags" ]; then
        print_status $RED "❌ Error: At least one backup tag required"
        echo "Usage: $0 delete <tag> [tag2 tag3...] [types] [-y]"
        echo "Example: $0 delete AUTO-dev-123-2025-01-15 static,db"
        echo "Example: $0 delete TAG1 TAG2 TAG3 all -y"
        exit 1
    fi

    # Validate all tags
    for tag in $tags; do
        if ! validate_backup_tag "$tag"; then
            exit 1
        fi
    done

    setup_s3_vars || exit 1

    local backup_types=$(parse_backup_types "$types_arg")
    local total_tags=0
    local current_tag=0
    local types_deleted=0
    local types_not_found=0
    local items_to_delete=""
    local backup_tag=""

    # Count total tags
    for tag in $tags; do
        total_tags=$((total_tags + 1))
    done

    # Process each tag
    for backup_tag in $tags; do
        current_tag=$((current_tag + 1))
        types_deleted=0
        types_not_found=0

        if [ $total_tags -gt 1 ]; then
            print_status $BLUE "🗑️  Deleting backup ($current_tag/$total_tags): $backup_tag"
        else
            print_status $BLUE "🗑️  Deleting backup: $backup_tag"
        fi

        # Check what exists and confirm deletion
        items_to_delete=""

        if has_backup_type "$backup_types" "static"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                items_to_delete="${items_to_delete}  - Static site backup\n"
            fi
        fi

        if has_backup_type "$backup_types" "public"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                items_to_delete="${items_to_delete}  - Public files backup\n"
            fi
        fi

        if has_backup_type "$backup_types" "db"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                items_to_delete="${items_to_delete}  - Database backup\n"
            fi
        fi

        if [ -z "$items_to_delete" ]; then
            print_status $YELLOW "⚠️  No backups found for tag: $backup_tag"
            continue
        fi

        # Show what will be deleted
        echo ""
        print_status $YELLOW "The following backups will be deleted:"
        printf "$items_to_delete"
        echo ""

        # Confirm deletion unless non-interactive
        if [ "$non_interactive" != "-y" ] && [ "$non_interactive" != "--non-interactive" ]; then
            printf "${YELLOW}Are you sure you want to delete these backups? (y/N): ${NC}"
            read -r confirmation
            if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
                print_status $YELLOW "Deletion cancelled."
                continue
            fi
        fi

        # Delete static backup
        if has_backup_type "$backup_types" "static"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                print_status $YELLOW "Deleting static site backup..."
                if aws s3 rm s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ --only-show-errors --recursive $S3_EXTRA_PARAMS; then
                    audit_log "backup_static_deleted" "info" "Static backup deleted" "backup_tag=$backup_tag"
                    print_status $GREEN "✅ Static backup deleted"
                    types_deleted=$((types_deleted + 1))
                else
                    audit_log "backup_static_delete_failed" "error" "Failed to delete static backup" "backup_tag=$backup_tag"
                    print_status $RED "❌ Failed to delete static backup"
                fi
            else
                types_not_found=$((types_not_found + 1))
            fi
        fi

        # Delete public backup
        if has_backup_type "$backup_types" "public"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                print_status $YELLOW "Deleting public files backup..."
                if aws s3 rm s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ --only-show-errors --recursive $S3_EXTRA_PARAMS; then
                    audit_log "backup_public_deleted" "info" "Public backup deleted" "backup_tag=$backup_tag"
                    print_status $GREEN "✅ Public files backup deleted"
                    types_deleted=$((types_deleted + 1))
                else
                    audit_log "backup_public_delete_failed" "error" "Failed to delete public backup" "backup_tag=$backup_tag"
                    print_status $RED "❌ Failed to delete public files backup"
                fi
            else
                types_not_found=$((types_not_found + 1))
            fi
        fi

        # Delete database backup
        if has_backup_type "$backup_types" "db"; then
            if aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz $S3_EXTRA_PARAMS >/dev/null 2>&1; then
                print_status $YELLOW "Deleting database backup..."
                if aws s3 rm s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz --only-show-errors $S3_EXTRA_PARAMS; then
                    audit_log "backup_database_deleted" "info" "Database backup deleted" "backup_tag=$backup_tag"
                    print_status $GREEN "✅ Database backup deleted"
                    types_deleted=$((types_deleted + 1))
                else
                    audit_log "backup_database_delete_failed" "error" "Failed to delete database backup" "backup_tag=$backup_tag"
                    print_status $RED "❌ Failed to delete database backup"
                fi
            else
                types_not_found=$((types_not_found + 1))
            fi
        fi

        # Summary for this tag
        if [ $types_deleted -gt 0 ]; then
            print_status $GREEN "✅ Backup deletion completed: $types_deleted type(s) deleted"
        fi

        if [ $types_not_found -gt 0 ]; then
            print_status $YELLOW "⚠️  $types_not_found type(s) not found"
        fi

        # Add spacing between tags if processing multiple
        if [ $total_tags -gt 1 ] && [ $current_tag -lt $total_tags ]; then
            echo ""
        fi
    done
}

parse_restore_options() {
    local restore_types="static,public,db"  # default: restore all
    local backup_tag=""

    # Parse all arguments, collecting both the tag and options
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
            --skip-state-management|--ssm)
                # Skip this flag, handled elsewhere
                shift
                ;;
            *)
                # This should be the backup tag
                if [ -z "$backup_tag" ]; then
                    backup_tag="$1"
                fi
                shift
                ;;
        esac
    done

    # Return the backup tag to stdout
    echo "$backup_tag"
    # Return the restore types to stderr
    echo "$restore_types" >&2
}

restore_backup() {
    local backup_tag=""
    local restore_types=""
    local skip_state_management=false

    # Parse arguments
    if [ $# -eq 0 ]; then
        print_status $RED "❌ Error: Backup tag is required"
        print_status $YELLOW "⚠️ Usage: restore <backup_tag> [--only=static,public,db] [--skip-state-management|--ssm]"
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
    restore_database=$(echo "$restore_types" | grep -qE "\<(db|database)\>" && echo "yes" || echo "no")

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
        public_backup_tag=$(find_corresponding_backup "$backup_tag" "public")

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
        db_backup_tag=$(find_corresponding_backup "$backup_tag" "db")

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

    audit_log "restore_started" "info" "Restore operation initiated" "backup_tag=$backup_tag static=$restore_static public=$restore_public database=$restore_database"

    # Restore static site
    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring static site..."
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$static_backup_tag/ s3://$BUCKET_NAME/web/ --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS; then
            # Sync theme assets from current container to match deployed code version
            # Theme assets (logos, fonts, images) are part of the codebase, not dynamic content
            # So we need to ensure S3 has the current version from the container
            if [ -d "/var/www/web/themes/custom/usagov" ]; then
                print_status $YELLOW "🔄 Syncing theme assets from current codebase..."

                # Create temporary directory for theme assets
                THEME_TMP_DIR="/tmp/theme-assets-$$"
                mkdir -p "$THEME_TMP_DIR/themes/custom/usagov"

                # Copy current theme assets from container
                cp -rfp /var/www/web/themes/custom/usagov/fonts "$THEME_TMP_DIR/themes/custom/usagov/" 2>/dev/null || true
                cp -rfp /var/www/web/themes/custom/usagov/images "$THEME_TMP_DIR/themes/custom/usagov/" 2>/dev/null || true
                cp -rfp /var/www/web/themes/custom/usagov/assets "$THEME_TMP_DIR/themes/custom/usagov/" 2>/dev/null || true

                # Sync theme assets to S3
                if aws s3 sync "$THEME_TMP_DIR/" "s3://$BUCKET_NAME/web/" --only-show-errors --acl public-read $S3_EXTRA_PARAMS 2>&1; then
                    print_status $GREEN "✅ Theme assets synced"
                else
                    print_status $YELLOW "⚠️ Theme asset sync had issues (non-fatal)"
                fi

                # Cleanup
                rm -rf "$THEME_TMP_DIR"
            else
                print_status $YELLOW "⚠️ Theme directory not found, skipping asset sync"
            fi

            print_status $GREEN "✅ Static site restored"
            audit_log "restore_static_success" "success" "Static site restored successfully" "backup_tag=$static_backup_tag"
            print_status $YELLOW "ℹ️  Note: Browser caches may take up to 15 minutes to refresh"
        else
            audit_log "restore_static_failed" "error" "Static site restore failed" "backup_tag=$static_backup_tag"
            print_status $RED "❌ ERROR: Static site restore failed"
            exit 1
        fi
    fi

    # Restore public files
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring public files..."
        if aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$public_backup_tag/ s3://$BUCKET_NAME/cms/public/ --only-show-errors --delete $S3_EXTRA_PARAMS; then
            audit_log "restore_public_success" "success" "Public files restored successfully" "backup_tag=$public_backup_tag"
            print_status $GREEN "✅ Public files restored"
            # Refresh S3FS metadata cache so Drupal sees the restored files
            if command -v drush >/dev/null 2>&1; then
                print_status $YELLOW "🔄 Refreshing file metadata cache..."
                if drush s3fs:refresh-cache 2>/dev/null; then
                    print_status $GREEN "✅ File metadata cache refreshed"
                else
                    print_status $YELLOW "⚠️ Could not refresh file cache (drush s3fs:refresh-cache failed)"
                fi
            else
                print_status $YELLOW "⚠️ Could not refresh file cache (drush not available)"
            fi
        else
            audit_log "restore_public_failed" "error" "Public files restore failed" "backup_tag=$public_backup_tag"
            print_status $RED "❌ ERROR: Public files restore failed"
            exit 1
        fi
    fi

    # Restore database
    if [ "$restore_database" = "yes" ] && [ -n "$db_backup_tag" ]; then
        audit_log "restore_database_started" "info" "Database restore initiated" "backup_tag=$db_backup_tag"
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
        temp_db_base="$(mktemp /tmp/restore_db.XXXXXX)"
        temp_db_file="${temp_db_base}.sql.gz"
        temp_sql_file="${temp_db_base}.sql"
        temp_checksum_file="${temp_db_base}.sha256"
        chmod 600 "$temp_db_base" "$temp_db_file" "$temp_sql_file" "$temp_checksum_file"

        # Ensure cleanup
        trap "rm -f '$temp_db_base' '$temp_db_file' '$temp_sql_file' '$temp_checksum_file'" EXIT INT TERM

        if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_backup_tag "$temp_db_file" $S3_EXTRA_PARAMS; then
            # Try to download and verify checksum
            local checksum_verified=false
            if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${db_backup_tag}.sha256 "$temp_checksum_file" $S3_EXTRA_PARAMS 2>/dev/null; then
                print_status $YELLOW "🔐 Verifying backup integrity..."
                local expected_checksum=$(cat "$temp_checksum_file")
                local actual_checksum=$(sha256sum "$temp_db_file" | awk '{print $1}')

                if [ "$expected_checksum" = "$actual_checksum" ]; then
                    print_status $GREEN "✓ Checksum verified"
                    checksum_verified=true
                else
                    audit_log "restore_database_failed" "error" "Checksum mismatch detected" "backup_tag=$db_backup_tag expected=$expected_checksum actual=$actual_checksum"
                    print_status $RED "❌ Checksum mismatch! Backup may be corrupted."
                    print_status $YELLOW "   Expected: $expected_checksum"
                    print_status $YELLOW "   Got:      $actual_checksum"
                    rm -f "$temp_sql_file" "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
                    [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                    exit 1
                fi
            else
                print_status $YELLOW "⚠️  No checksum file found, skipping integrity check"
            fi

            if gunzip -c "$temp_db_file" > "$temp_sql_file" 2>/dev/null; then
                # Validate SQL content for dangerous patterns
                if ! validate_sql_content "$temp_sql_file"; then
                    audit_log "restore_database_failed" "error" "SQL content validation failed" "backup_tag=$db_backup_tag"
                    print_status $RED "❌ SQL content validation failed"
                    rm -f "$temp_sql_file" "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
                    [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                    exit 1
                fi

                if command -v drush >/dev/null 2>&1; then
                    # Use drush for database import
                    if drush sql:drop -y && drush sql:cli < "$temp_sql_file"; then
                        # Restore Drupal state before success message
                        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                        audit_log "restore_database_success" "success" "Database restored successfully" "backup_tag=$db_backup_tag"
                        print_status $GREEN "✅ Database restored"
                    else
                        audit_log "restore_database_failed" "error" "Database import failed" "backup_tag=$db_backup_tag"
                        print_status $RED "❌ ERROR: Database import failed"
                        rm -f "$temp_sql_file" "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
                        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                        exit 1
                    fi
                else
                    print_status $RED "❌ ERROR: Drush not available for database restore"
                    rm -f "$temp_sql_file" "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
                    [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                    exit 1
                fi
            else
                    print_status $RED "❌ ERROR: Failed to decompress database backup"
                rm -f "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
                [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
                exit 1
            fi
        else
            print_status $RED "❌ ERROR: Failed to download database backup"
            rm -f "$temp_db_base" "$temp_checksum_file" 2>/dev/null
            [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
            exit 1
        fi

        rm -f "$temp_sql_file" "$temp_db_file" "$temp_db_base" "$temp_checksum_file" 2>/dev/null
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
        audit_log "restore_completed" "success" "Restore operation completed successfully" "backup_tag=$backup_tag restored_types=$restored_items"
        print_status $GREEN "Restored: $restored_items"
    fi
}

backup_info() {
    local backup_tag=$1
    local backup_types=${2:-"all"}

    if [ -z "$backup_tag" ]; then
        print_status $RED "Error: Backup tag is required"
        exit 1
    fi

    # Check for --json flag in remaining arguments
    shift 2 2>/dev/null
    if has_json_flag "$@"; then
        backup_info_json "$backup_tag" "$backup_types" "$@"
        return $?
    fi

    local do_verify=false
    for arg in "$@"; do
        [ "$arg" = "--verify" ] && do_verify=true
    done

    local static_exists="no"
    local public_exists="no"
    local db_exists="no"

    setup_s3_vars || exit 1

    print_status $BLUE "╔════════════════════════════════════════════════════════════════╗"
    print_status $BLUE "║ Backup Information: $backup_tag"
    print_status $BLUE "╚════════════════════════════════════════════════════════════════╝"
    echo ""

    # Parse tag components
    echo "Tag Analysis:"
    local tag_date=$(extract_date_from_backup_name "$backup_tag")
    if [ -n "$tag_date" ]; then
        echo "  Date: $tag_date"
        # Parse date to epoch - try Linux format first, then macOS
        local tag_epoch=$(date -d "$tag_date" +%s 2>/dev/null || date -j -f "%Y-%m-%d" "$tag_date" +%s 2>/dev/null)
        if [ -n "$tag_epoch" ]; then
            local days_ago=$(( ($(date +%s) - tag_epoch) / 86400 ))
            if [ $days_ago -ge 0 ]; then
                echo "  Age: $days_ago days old"
            fi
        fi
    fi

    # Extract other tag components
    local tag_prefix=$(echo "$backup_tag" | awk -F'-' '{print $1}')
    local tag_space=$(echo "$backup_tag" | awk -F'-' '{print $2}')
    if [ -n "$tag_prefix" ]; then
        echo "  Prefix: $tag_prefix"
    fi
    if [ -n "$tag_space" ]; then
        echo "  Space: $tag_space"
    fi
    echo ""

    # Storage location
    echo "Storage Information:"
    echo "  Bucket: s3://$BUCKET_NAME"
    echo ""

    # Check static site backup
    if has_backup_type "$backup_types" "static"; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "📄 STATIC SITE BACKUP"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        local static_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
        if echo "$static_output" | grep -q "Total Objects:"; then
            static_exists="yes"

            # Extract creation time from first file
            local first_file=$(echo "$static_output" | grep -v "Total" | grep -v "^$" | head -1)
            local static_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$static_date" ]; then
                echo "  Created: $static_date UTC"
            fi

            # S3 path
            echo "  S3 Path: s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/"

            # Extract summary information
            local total_objects=$(echo "$static_output" | grep "Total Objects:" | awk '{print $3}')
            local total_size=$(echo "$static_output" | grep "Total Size:" | awk '{print $3}')

            if [ -n "$total_objects" ]; then
                echo "  Total Files: $total_objects"
            fi
            if [ -n "$total_size" ]; then
                local formatted_size=$(format_file_size "$total_size")
                echo "  Total Size: $formatted_size"
            fi

            if [ "$do_verify" = "true" ]; then
                if [ "${total_objects:-0}" -gt 0 ] 2>/dev/null && [ "${total_size:-0}" -gt 0 ] 2>/dev/null; then
                    print_status $GREEN "  Verify: ✅ VALID"
                    static_valid=true
                else
                    print_status $RED "  Verify: ❌ INVALID (exists but is empty)"
                    static_valid=false
                fi
            fi

            # Show sample files
            echo ""
            echo "  Sample Files (first 5):"
            echo "$static_output" | grep -v "Total" | grep -v "^$" | head -5 | while read -r line; do
                local file_size=$(echo "$line" | awk '{print $3}')
                local file_name=$(echo "$line" | awk '{print $4}' | sed "s|$AUTO_STATIC_BACKUP_PATH/$backup_tag/||")
                local formatted_file_size=$(format_file_size "$file_size")
                echo "    - $file_name ($formatted_file_size)"
            done
        else
            echo "  Status: ❌ Not found"
            echo "  S3 Path: s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/"
            [ "$do_verify" = "true" ] && static_valid=false
        fi
        echo ""
    fi

    # Check public files backup
    if has_backup_type "$backup_types" "public"; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "📁 PUBLIC FILES BACKUP"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        local public_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
        if echo "$public_output" | grep -q "Total Objects:"; then
            public_exists="yes"

            # Extract creation time from first file
            local first_file=$(echo "$public_output" | grep -v "Total" | grep -v "^$" | head -1)
            local public_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$public_date" ]; then
                echo "  Created: $public_date UTC"
            fi

            # S3 path
            echo "  S3 Path: s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/"

            # Extract summary information
            local total_objects=$(echo "$public_output" | grep "Total Objects:" | awk '{print $3}')
            local total_size=$(echo "$public_output" | grep "Total Size:" | awk '{print $3}')

            if [ -n "$total_objects" ]; then
                echo "  Total Files: $total_objects"
            fi
            if [ -n "$total_size" ]; then
                local formatted_size=$(format_file_size "$total_size")
                echo "  Total Size: $formatted_size"
            fi

            if [ "$do_verify" = "true" ]; then
                if [ "${total_objects:-0}" -gt 0 ] 2>/dev/null && [ "${total_size:-0}" -gt 0 ] 2>/dev/null; then
                    print_status $GREEN "  Verify: ✅ VALID"
                    public_valid=true
                else
                    print_status $RED "  Verify: ❌ INVALID (exists but is empty)"
                    public_valid=false
                fi
            fi

            # Show sample files
            echo ""
            echo "  Sample Files (first 5):"
            echo "$public_output" | grep -v "Total" | grep -v "^$" | head -5 | while read -r line; do
                local file_size=$(echo "$line" | awk '{print $3}')
                local file_name=$(echo "$line" | awk '{print $4}' | sed "s|$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/||")
                local formatted_file_size=$(format_file_size "$file_size")
                echo "    - $file_name ($formatted_file_size)"
            done
        else
            echo "  Status: ❌ Not found"
            echo "  S3 Path: s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/"
            [ "$do_verify" = "true" ] && public_valid=false

            # If static exists but public doesn't, show the smart relationship
            if [ "$static_exists" = "yes" ]; then
                echo ""
                print_status $YELLOW "  💡 Smart Backup Optimization Detected"
                echo "  ────────────────────────────────────────"
                echo "  This static site backup has no corresponding public files backup."
                echo "  This means public files were unchanged at backup time (smart optimization)."
                echo ""

                local corresponding_public=$(find_corresponding_backup "$backup_tag" "public")
                if [ -n "$corresponding_public" ]; then
                    if [ "$corresponding_public" != "$backup_tag" ]; then
                        print_status $GREEN "  📍 Linked Public Backup: $corresponding_public"
                        echo "  This is the public backup that would be used for restore operations."
                        echo ""
                        echo "  Public Files Details:"
                        local corr_public_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$corresponding_public/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)
                        local corr_first_file=$(echo "$corr_public_output" | grep -v "Total" | grep -v "^$" | head -1)
                        local corr_date=$(echo "$corr_first_file" | awk '{print $1" "$2}')
                        if [ -n "$corr_date" ]; then
                            echo "    Created: $corr_date UTC"
                        fi
                        echo "    S3 Path: s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$corresponding_public/"

                        local corr_total_objects=$(echo "$corr_public_output" | grep "Total Objects:" | awk '{print $3}')
                        local corr_total_size=$(echo "$corr_public_output" | grep "Total Size:" | awk '{print $3}')
                        if [ -n "$corr_total_objects" ]; then
                            echo "    Total Files: $(printf "%'d" $corr_total_objects)"
                        fi
                        if [ -n "$corr_total_size" ]; then
                            local corr_formatted_size=$(format_file_size "$corr_total_size")
                            echo "    Total Size: $corr_formatted_size"
                        fi
                    fi
                else
                    print_status $YELLOW "  ⚠️  No suitable public backup found for this time period."
                fi
            fi
        fi
        echo ""
    fi

    # Check database backup
    if has_backup_type "$backup_types" "db"; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "💾 DATABASE BACKUP"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

        local backup_name="${backup_tag}.sql.gz"
        local db_file_info=$(aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$backup_name")

        if [ -n "$db_file_info" ]; then
            db_exists="yes"
            local backup_size=$(echo "$db_file_info" | awk '{print $3}')
            local backup_date=$(echo "$db_file_info" | awk '{print $1" "$2}')
            local backup_file=$(echo "$db_file_info" | awk '{print $4}')

            echo "  Created: $backup_date UTC"
            echo "  S3 Path: s3://$BUCKET_NAME/$backup_file"
            echo "  File: $backup_name"

            local formatted_size=$(format_file_size "$backup_size")
            echo "  Size: $formatted_size"

            # Estimate uncompressed size (gzip typically achieves 10-20x compression for SQL)
            local uncompressed_estimate=$((backup_size * 15))
            local formatted_uncompressed=$(format_file_size "$uncompressed_estimate")
            echo "  Estimated Uncompressed: ~$formatted_uncompressed"

            # Check for checksum sidecar (stream 64-byte file into variable, no disk writes)
            local stored_checksum
            stored_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz.sha256" $S3_EXTRA_PARAMS - 2>/dev/null)
            if [ -z "$stored_checksum" ]; then
                stored_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sha256" $S3_EXTRA_PARAMS - 2>/dev/null)
            fi
            if [ -n "$stored_checksum" ]; then
                echo "  Checksum (SHA-256): $stored_checksum"
            else
                echo "  Checksum: not available"
            fi

            # Verify DB integrity if requested (streams full .sql.gz from S3, no disk writes)
            if [ "$do_verify" = "true" ]; then
                if [ -n "$stored_checksum" ]; then
                    echo "  Verifying integrity (streaming from S3)..."
                    local actual_checksum
                    actual_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz" $S3_EXTRA_PARAMS - 2>/dev/null | sha256sum | awk '{print $1}')
                    if [ "$stored_checksum" = "$actual_checksum" ]; then
                        print_status $GREEN "  Integrity: ✅ VALID"
                        db_valid=true
                    else
                        print_status $RED "  Integrity: ❌ INVALID (checksum mismatch)"
                        echo "    Expected: $stored_checksum"
                        echo "    Computed: $actual_checksum"
                        db_valid=false
                    fi
                else
                    print_status $YELLOW "  Integrity: ⚠️  cannot verify (no checksum file)"
                    db_valid=unverifiable
                fi
            fi
        else
            echo "  Status: ❌ Not found"
            echo "  S3 Path: s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz"
            [ "$do_verify" = "true" ] && db_valid=false
        fi
        echo ""
    fi

    # Overall backup summary
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📊 BACKUP SUMMARY"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    local components_found=0
    local components_expected=0

    if has_backup_type "$backup_types" "static"; then
        components_expected=$((components_expected + 1))
        [ "$static_exists" = "yes" ] && components_found=$((components_found + 1))
    fi
    if has_backup_type "$backup_types" "public"; then
        components_expected=$((components_expected + 1))
        [ "$public_exists" = "yes" ] && components_found=$((components_found + 1))
    fi
    if has_backup_type "$backup_types" "db"; then
        components_expected=$((components_expected + 1))
        [ "$db_exists" = "yes" ] && components_found=$((components_found + 1))
    fi

    echo "  Components Found: $components_found of $components_expected"
    echo ""
    echo "  Backup Components:"
    if has_backup_type "$backup_types" "static"; then
        if [ "$static_exists" = "yes" ]; then
            echo "    ✅ Static Site Backup"
        else
            echo "    ❌ Static Site Backup"
        fi
    fi
    if has_backup_type "$backup_types" "public"; then
        if [ "$public_exists" = "yes" ]; then
            echo "    ✅ Public Files Backup"
        elif [ "$static_exists" = "yes" ]; then
            echo "    🔗 Public Files Backup (using smart linked backup)"
        else
            echo "    ❌ Public Files Backup"
        fi
    fi
    if has_backup_type "$backup_types" "db"; then
        if [ "$db_exists" = "yes" ]; then
            echo "    ✅ Database Backup"
        else
            echo "    ❌ Database Backup"
        fi
    fi

    echo ""
    echo "  Backup Completeness:"
    if [ $components_found -eq $components_expected ]; then
        if [ "$static_exists" = "yes" ] && [ "$public_exists" = "no" ] && has_backup_type "$backup_types" "public"; then
            print_status $GREEN "    ✅ Complete (using smart backup optimization)"
        else
            print_status $GREEN "    ✅ Complete - all components present"
        fi
    elif [ $components_found -eq 0 ]; then
        print_status $RED "    ❌ No backup components found"
    else
        print_status $YELLOW "    ⚠️  Partial - $components_found of $components_expected components present"
    fi

    if [ "$do_verify" = "true" ]; then
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        echo "🔍 VERIFICATION RESULTS"
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
        local all_valid=true
        if has_backup_type "$backup_types" "static"; then
            case "${static_valid:-}" in
                true)  echo "  ✅ Static Site" ;;
                false) echo "  ❌ Static Site"; all_valid=false ;;
                *)     echo "  ⚠️  Static Site (not checked)" ;;
            esac
        fi
        if has_backup_type "$backup_types" "public"; then
            case "${public_valid:-}" in
                true)  echo "  ✅ Public Files" ;;
                false) echo "  ❌ Public Files"; all_valid=false ;;
                *)     echo "  ⚠️  Public Files (not checked)" ;;
            esac
        fi
        if has_backup_type "$backup_types" "db"; then
            case "${db_valid:-}" in
                true)         echo "  ✅ Database (checksum verified)" ;;
                false)        echo "  ❌ Database"; all_valid=false ;;
                unverifiable) echo "  ⚠️  Database (no checksum file on record)" ;;
                *)            echo "  ⚠️  Database (not checked)" ;;
            esac
        fi
        echo ""
        if [ "$all_valid" = "true" ]; then
            print_status $GREEN "  Overall: ✅ VALID"
        else
            print_status $RED "  Overall: ❌ INVALID"
        fi
        echo ""
    fi

    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# JSON version of backup_info
backup_info_json() {
    local backup_tag=$1
    local backup_types=${2:-"all"}
    shift 2 2>/dev/null

    local do_verify=false
    for arg in "$@"; do
        [ "$arg" = "--verify" ] && do_verify=true
    done

    # Track per-component validity for --verify summary
    local static_json_valid=true
    local public_json_valid=true
    local db_json_valid=true

    setup_s3_vars || exit 1

    # Initialize JSON structure
    local json_output='{'

    # Tag Analysis
    json_output="${json_output}\"tag\":\"$backup_tag\""

    local tag_date=$(extract_date_from_backup_name "$backup_tag")
    if [ -n "$tag_date" ]; then
        json_output="${json_output},\"date\":\"$tag_date\""
        local tag_epoch=$(date -d "$tag_date" +%s 2>/dev/null || date -j -f "%Y-%m-%d" "$tag_date" +%s 2>/dev/null)
        if [ -n "$tag_epoch" ]; then
            local days_ago=$(( ($(date +%s) - tag_epoch) / 86400 ))
            if [ $days_ago -ge 0 ]; then
                json_output="${json_output},\"age_days\":$days_ago"
            fi
        fi
    fi

    local tag_prefix=$(echo "$backup_tag" | awk -F'-' '{print $1}')
    local tag_space=$(echo "$backup_tag" | awk -F'-' '{print $2}')
    if [ -n "$tag_prefix" ]; then
        json_output="${json_output},\"prefix\":\"$tag_prefix\""
    fi
    if [ -n "$tag_space" ]; then
        json_output="${json_output},\"space\":\"$tag_space\""
    fi

    json_output="${json_output},\"bucket\":\"$BUCKET_NAME\",\"components\":{"

    local component_count=0
    local components_found=0

    # Static site backup
    if has_backup_type "$backup_types" "static"; then
        [ $component_count -gt 0 ] && json_output="${json_output},"
        component_count=$((component_count + 1))

        json_output="${json_output}\"static\":{"
        local static_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)

        if echo "$static_output" | grep -q "Total Objects:"; then
            components_found=$((components_found + 1))
            json_output="${json_output}\"exists\":true"

            local first_file=$(echo "$static_output" | grep -v "Total" | grep -v "^$" | head -1)
            local static_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$static_date" ]; then
                json_output="${json_output},\"created\":\"$static_date\""
            fi

            json_output="${json_output},\"path\":\"s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/\""

            local total_objects=$(echo "$static_output" | grep "Total Objects:" | awk '{print $3}')
            local total_size=$(echo "$static_output" | grep "Total Size:" | awk '{print $3}')

            if [ -n "$total_objects" ]; then
                json_output="${json_output},\"file_count\":$total_objects"
            fi
            if [ -n "$total_size" ]; then
                json_output="${json_output},\"size_bytes\":$total_size"
            fi
            if [ "$do_verify" = "true" ]; then
                if [ "${total_objects:-0}" -gt 0 ] 2>/dev/null && [ "${total_size:-0}" -gt 0 ] 2>/dev/null; then
                    json_output="${json_output},\"valid\":true"
                else
                    json_output="${json_output},\"valid\":false"
                    static_json_valid=false
                fi
            fi
        else
            json_output="${json_output}\"exists\":false,\"path\":\"s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/\""
            if [ "$do_verify" = "true" ]; then
                json_output="${json_output},\"valid\":false"
                static_json_valid=false
            fi
        fi
        json_output="${json_output}}"
    fi

    # Public files backup
    if has_backup_type "$backup_types" "public"; then
        [ $component_count -gt 0 ] && json_output="${json_output},"
        component_count=$((component_count + 1))

        json_output="${json_output}\"public\":{"
        local public_output=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ $S3_EXTRA_PARAMS --recursive --summarize 2>&1)

        if echo "$public_output" | grep -q "Total Objects:"; then
            components_found=$((components_found + 1))
            json_output="${json_output}\"exists\":true"

            local first_file=$(echo "$public_output" | grep -v "Total" | grep -v "^$" | head -1)
            local public_date=$(echo "$first_file" | awk '{print $1" "$2}')
            if [ -n "$public_date" ]; then
                json_output="${json_output},\"created\":\"$public_date\""
            fi

            json_output="${json_output},\"path\":\"s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/\""

            local total_objects=$(echo "$public_output" | grep "Total Objects:" | awk '{print $3}')
            local total_size=$(echo "$public_output" | grep "Total Size:" | awk '{print $3}')

            if [ -n "$total_objects" ]; then
                json_output="${json_output},\"file_count\":$total_objects"
            fi
            if [ -n "$total_size" ]; then
                json_output="${json_output},\"size_bytes\":$total_size"
            fi
            if [ "$do_verify" = "true" ]; then
                if [ "${total_objects:-0}" -gt 0 ] 2>/dev/null && [ "${total_size:-0}" -gt 0 ] 2>/dev/null; then
                    json_output="${json_output},\"valid\":true"
                else
                    json_output="${json_output},\"valid\":false"
                    public_json_valid=false
                fi
            fi
        else
            json_output="${json_output}\"exists\":false,\"path\":\"s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/\""
            if [ "$do_verify" = "true" ]; then
                json_output="${json_output},\"valid\":false"
                public_json_valid=false
            fi

            # Check for linked backup
            local corresponding_public=$(find_corresponding_backup "$backup_tag" "public")
            if [ -n "$corresponding_public" ] && [ "$corresponding_public" != "$backup_tag" ]; then
                json_output="${json_output},\"linked_backup\":\"$corresponding_public\""
            fi
        fi
        json_output="${json_output}}"
    fi

    # Database backup
    if has_backup_type "$backup_types" "db"; then
        [ $component_count -gt 0 ] && json_output="${json_output},"
        component_count=$((component_count + 1))

        json_output="${json_output}\"database\":{"
        local backup_name="${backup_tag}.sql.gz"
        local db_file_info=$(aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "$backup_name")

        if [ -n "$db_file_info" ]; then
            components_found=$((components_found + 1))
            json_output="${json_output}\"exists\":true"

            local backup_size=$(echo "$db_file_info" | awk '{print $3}')
            local backup_date=$(echo "$db_file_info" | awk '{print $1" "$2}')
            local backup_file=$(echo "$db_file_info" | awk '{print $4}')

            json_output="${json_output},\"created\":\"$backup_date\""
            json_output="${json_output},\"path\":\"s3://$BUCKET_NAME/$backup_file\""
            json_output="${json_output},\"filename\":\"$backup_name\""
            json_output="${json_output},\"size_bytes\":$backup_size"

            local uncompressed_estimate=$((backup_size * 15))
            json_output="${json_output},\"estimated_uncompressed_bytes\":$uncompressed_estimate"

            # Always check for checksum sidecar (stream 64-byte file into variable, no disk writes)
            local stored_checksum
            stored_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz.sha256" $S3_EXTRA_PARAMS - 2>/dev/null)
            if [ -z "$stored_checksum" ]; then
                stored_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sha256" $S3_EXTRA_PARAMS - 2>/dev/null)
            fi
            if [ -n "$stored_checksum" ]; then
                json_output="${json_output},\"checksum_on_file\":\"$stored_checksum\""
            else
                json_output="${json_output},\"checksum_available\":false"
            fi

            if [ "$do_verify" = "true" ]; then
                if [ -n "$stored_checksum" ]; then
                    local actual_checksum
                    actual_checksum=$(aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz" $S3_EXTRA_PARAMS - 2>/dev/null | sha256sum | awk '{print $1}')
                    json_output="${json_output},\"checksum_computed\":\"$actual_checksum\""
                    if [ "$stored_checksum" = "$actual_checksum" ]; then
                        json_output="${json_output},\"valid\":true"
                    else
                        json_output="${json_output},\"valid\":false"
                        db_json_valid=false
                    fi
                else
                    json_output="${json_output},\"valid\":null"
                fi
            fi
        else
            json_output="${json_output}\"exists\":false,\"path\":\"s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz\""
            if [ "$do_verify" = "true" ]; then
                json_output="${json_output},\"valid\":false"
                db_json_valid=false
            fi
        fi
        json_output="${json_output}}"
    fi

    json_output="${json_output}},\"summary\":{"
    json_output="${json_output}\"components_found\":$components_found"
    json_output="${json_output},\"components_expected\":$component_count"

    if [ $components_found -eq $component_count ]; then
        json_output="${json_output},\"complete\":true"
    else
        json_output="${json_output},\"complete\":false"
    fi

    if [ "$do_verify" = "true" ]; then
        local all_valid=true
        [ "$static_json_valid" = "false" ] && all_valid=false
        [ "$public_json_valid" = "false" ] && all_valid=false
        [ "$db_json_valid" = "false" ] && all_valid=false
        json_output="${json_output},\"all_valid\":$all_valid"
    fi

    json_output="${json_output}}}"

    format_json "$json_output"
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

    # Validate backup tag
    if ! validate_backup_tag "$backup_tag"; then
        return 1
    fi

    case "$backup_type" in
        "db")
            # Find database backup file
            db_file=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ $S3_EXTRA_PARAMS 2>/dev/null | grep "$backup_tag" | grep '\.sql\.gz$' | awk '{print $4}')

            if [ -z "$db_file" ]; then
                log_message "❌ Error: Database backup not found for tag: $backup_tag" >&2
                return 1
            fi

            if [ "$stream_mode" = true ]; then
                # Stream mode: output to stdout
                log_message "📥 Streaming database backup: $db_file" >&2
                aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_file - $S3_EXTRA_PARAMS 2>/dev/null
                return $?
            else
                # Local download mode - validate and normalize output path
                local validated_path
                if [ -n "$output_path" ]; then
                    validated_path=$(validate_output_path "$output_path")
                    if [ $? -ne 0 ]; then
                        log_message "❌ Invalid output path" >&2
                        return 1
                    fi
                else
                    validated_path=$(pwd)
                fi

                mkdir -p "$validated_path"
                output_file="$validated_path/${backup_tag}-database.sql.gz"

                log_message "📥 Downloading database backup: $db_file"

                # Get expected file size
                local expected_size=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_file $S3_EXTRA_PARAMS 2>/dev/null | awk '{print $3}')

                if aws s3 cp s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_file "$output_file" $S3_EXTRA_PARAMS; then
                    # Verify downloaded file size
                    local actual_size=$(stat -f%z "$output_file" 2>/dev/null || stat -c%s "$output_file" 2>/dev/null)
                    if [ -n "$expected_size" ] && [ -n "$actual_size" ] && [ "$expected_size" != "$actual_size" ]; then
                        log_message "⚠️  Warning: File size mismatch (expected: $expected_size, got: $actual_size)"
                    fi

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
                aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" --only-show-errors $S3_EXTRA_PARAMS >/dev/null 2>&1
                tar -czf - -C "$temp_dir" .
                local tar_exit=$?
                rm -rf "$temp_dir"
                return $tar_exit
            else
                # Local download mode - default to current working directory
                output_dir=${output_path:-$(pwd)}
                mkdir -p "$output_dir"
                output_file="$output_dir/${backup_tag}-static.tar.gz"

                log_message "📥 Downloading static backup: $backup_tag"

                # Download to temp dir, create tar.gz, move to output
                temp_dir=$(mktemp -d)
                if aws s3 sync s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" --only-show-errors $S3_EXTRA_PARAMS; then
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
                aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" --only-show-errors $S3_EXTRA_PARAMS >/dev/null 2>&1
                tar -czf - -C "$temp_dir" .
                local tar_exit=$?
                rm -rf "$temp_dir"
                return $tar_exit
            else
                # Local download mode - default to current working directory
                output_dir=${output_path:-$(pwd)}
                mkdir -p "$output_dir"
                output_file="$output_dir/${backup_tag}-public.tar.gz"

                log_message "📥 Downloading public backup: $backup_tag"

                # Download to temp dir, create tar.gz, move to output
                temp_dir=$(mktemp -d)
                if aws s3 sync s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ "$temp_dir/" --only-show-errors $S3_EXTRA_PARAMS; then
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

COMMAND="${1:-}"

# Handle help
if [ "$COMMAND" = "-h" ] || [ "$COMMAND" = "--help" ] || [ "$COMMAND" = "help" ] || [ -z "$COMMAND" ]; then
    show_usage
    exit 0
fi

# Check if second arg is -h/--help for command-specific help
if [ "$2" = "-h" ] || [ "$2" = "--help" ]; then
    show_command_help "$COMMAND"
    exit 0
fi

case "$COMMAND" in
    "list")
        # list [types] [days] [--json] - e.g., "list static,db" or "list all 7" or "list all 7 --json"
        shift  # Remove the 'list' command
        list_backups "$@"
        ;;
    "backup")
        # backup [types] [prefix] [suffix] [--skip-state-management|--ssm] - e.g., "backup db" or "backup all USAGOV-123 post-deploy"
        run_backup_command "$2" "$3" "$4" "$5"
        ;;
    "clean")
        # clean [types] [days] [-y|--non-interactive] - e.g., "clean all 30" or "clean db 7 -y"
        shift  # Remove the 'clean' command
        run_clean_command "$@"  # Pass all remaining arguments
        ;;
    "delete")
        # delete <tag> [tag2 tag3...] [types] [-y] - e.g., "delete AUTO-dev-123-2025-01-15" or "delete TAG1 TAG2 static -y"
        shift  # Remove the 'delete' command
        delete_backup "$@"  # Pass all remaining arguments
        ;;
    "restore")
        # restore
        shift  # Remove the 'restore' command
        restore_backup "$@"  # Pass all remaining arguments
        ;;
    "info")
        # info [types] <tag> [--json] - e.g., "info db" or "info all backup-tag" or "info all backup-tag --json"
        shift  # Remove the 'info' command
        run_info_command "$@"
        ;;
    "download")
        # download <tag> <type> [output-path] [--stream]
        # e.g., "download AUTO-prod-14850-2025-10-28 db ./backups/" or "download AUTO-prod-14850-2025-10-28 db - --stream"
        download_backup "$2" "$3" "$4" "$5"
        ;;
    "state")
        # state <action> <type> [max_wait_mins] - Manage Drupal state
        # e.g., "state disable tome 30" or "state enable both"
        action="$2"
        state_type="${3:-both}"
        max_wait="${4:-25}"

        if [ -z "$action" ]; then
            print_status $RED "❌ Error: action required (enable|disable)"
            echo "Usage: manager.sh state <action> <type> [max_wait_mins]"
            exit 1
        fi

        state_command "$action" "$state_type" "$max_wait"
        exit $?
        ;;
    *)
        print_status $RED "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac