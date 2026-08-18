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
DB_BACKUP_TIME=${DB_BACKUP_TIME:-"23:00"}
ENABLE_SMART_PUBLIC_BACKUP=${ENABLE_SMART_PUBLIC_BACKUP:-true}
BACKUP_THROTTLE_HOURS=${BACKUP_THROTTLE_HOURS:-4}
RESTORE_MIN_SOURCE_PERCENT=${RESTORE_MIN_SOURCE_PERCENT:-50}
RESTORE_POINT_PREFIX=${RESTORE_POINT_PREFIX:-PRERESTORE}

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
    echo "                [--no-recovery-point] [--force-destructive-sync]"
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
    echo "    all | 0                          Delete ALL backups of the named types"
    echo "                                     (requires 'DELETE ALL'; types must be named"
    echo "                                      explicitly and cannot include db)"
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
    echo "  # Delete ALL backups of a type (dangerous!)"
    echo "  $0 clean static,public all                             # ⚠️  DELETE ALL static/public (requires confirmation)"
    echo "  $0 clean static 0                                      # ⚠️  DELETE ALL static (same filter, one type)"
    echo "  #   Database backups are never removed by 'all': clean them by age,"
    echo "  #   then remove what remains with the 'delete' command."
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
            echo "             all | 0                     - Delete every backup of the"
            echo "                                           named types (db not allowed)"
            echo "  -y       - Non-interactive mode (no confirmation)"
            echo ""
            echo "Notes:"
            echo "  Only the types you name are touched, and each type reports its own"
            echo "  result. The command exits non-zero if any requested type did not"
            echo "  fully complete. Database backups always keep a 48-hour minimum"
            echo "  retention, so 'all'/'0' cannot be used with db."
            echo ""
            echo "Examples:"
            echo "  manager.sh clean all 7"
            echo "  manager.sh clean db --older-than 30 -y"
            echo "  manager.sh clean all --in-range 2024-01-01:2024-12-31"
            echo "  manager.sh clean static,public all -y"
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
            echo "  --skip-confirmation, --yes, -y - Skip restore confirmation"
            echo "  --no-recovery-point           - Skip the pre-restore recovery point"
            echo "                                  (a failed restore cannot be rolled back)"
            echo "  --force-destructive-sync      - Allow a backup holding far fewer objects"
            echo "                                  than live content to overwrite it"
            echo ""
            echo "How a restore proceeds:"
            echo "  1. Every requested component is verified first - including downloading,"
            echo "     checksumming, decompressing, and validating the database dump - so"
            echo "     nothing is modified until all checks pass."
            echo "  2. A pre-restore recovery point is created and verified."
            echo "  3. Components are restored. If a later phase fails, the phases already"
            echo "     applied are rolled back to that recovery point."
            echo ""
            echo "  A requested component with no matching backup is an error: the restore"
            echo "  stops rather than leaving that store on a different generation."
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
# Cleanup handler for the whole backup command.
#
# Each create_*_backup restores the state half it prepared on its own exit paths,
# but the backup command had no trap at all: a signal or a shell error between
# prepare and restore left the site in maintenance mode, or with Tome disabled,
# with nothing to put it back. Cron and CircleCI both reach this path.
#
# Idempotent, so it is harmless when the operation already restored state — the
# active flags are false by then and only temp-file removal runs.
# NIST 800-53: CP-10, AU-3
# Sequence number shared by every component of the current backup set. Empty means
# "allocate per component", which is what direct callers such as the restore's
# recovery point rely on.
BACKUP_SET_SUFFIX=""

BACKUP_CLEANUP_DONE=false
backup_cleanup() {
    local exit_code=$?

    # Runs once: the signal handlers exit explicitly, which re-triggers EXIT.
    if [ "$BACKUP_CLEANUP_DONE" = "true" ]; then
        return $exit_code
    fi
    BACKUP_CLEANUP_DONE=true

    for tmp in "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM"; do
        [ -n "$tmp" ] && rm -f "$tmp" 2>/dev/null
    done

    # Released last of all, so it is still held through the metadata commit.
    backup_lock_release

    # Cleared so a later component created directly in this process — a restore's
    # recovery point, say — allocates its own number instead of reusing this set's.
    BACKUP_SET_SUFFIX=""

    if [ "$DRUPAL_STATE_ACTIVE_MAINT" = "true" ] || [ "$DRUPAL_STATE_ACTIVE_TOME" = "true" ]; then
        print_status $YELLOW "⚠️  Backup ended with Drupal state still held — restoring it..."
        if [ "$DRUPAL_STATE_ACTIVE_MAINT" = "true" ] && [ "$DRUPAL_STATE_ACTIVE_TOME" = "true" ]; then
            restore_drupal_state "both"
        elif [ "$DRUPAL_STATE_ACTIVE_MAINT" = "true" ]; then
            restore_drupal_state "maintenance"
        else
            restore_drupal_state "tome"
        fi
    fi

    return $exit_code
}

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

    # Determine backup prefix and suffix. The suffix carries no leading delimiter:
    # prepare_backup_tag joins it. Passing "-$custom_suffix" here is what produced
    # the double delimiter that sequence discovery could never match.
    local backup_prefix="${custom_prefix:-$BACKUP_PREFIX}"
    local backup_suffix="$custom_suffix"

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

    # Armed before any state is touched, and covers every backup type below.
    arm_cleanup_traps backup_cleanup

    # Taken after the trap is armed, so an interruption between the two cannot leak
    # the lock. Contention is the expected outcome on every production instance but
    # one, so it is reported and exits 0 rather than alerting a scheduler.
    backup_lock_acquire
    local lock_result=$?
    if [ "$lock_result" -eq 1 ]; then
        if [ "$use_json" = true ]; then
            format_json '{"operation":"backup","status":"skipped","reason":"lock_held_elsewhere"}'
        fi
        return 0
    fi
    if [ "$lock_result" -ne 0 ]; then
        if [ "$use_json" = true ]; then
            format_json '{"operation":"backup","status":"error","reason":"lock_unavailable"}'
        fi
        return 1
    fi
    # One sequence number for the whole set, chosen before any component is written.
    # Allocating per component allowed a set to come out as static-3/public-1/db-1,
    # which no longer shares a tag. The backup lock above is what makes this
    # allocation safe against another instance.
    setup_s3_vars >/dev/null 2>&1
    local set_base="${backup_prefix}-${APP_SPACE}-${CONTAINER_TAG}-${backup_timestamp}"
    local set_stem="$set_base"
    local set_legacy_stem=""
    if [ -n "$backup_suffix" ]; then
        set_stem="${set_base}-${backup_suffix}"
        set_legacy_stem="${set_base}--${backup_suffix}"
    fi
    if ! allocate_backup_set_suffix "$set_stem" "$set_legacy_stem" "$backup_types"; then
        if [ "$use_json" = true ]; then
            format_json '{"operation":"backup","status":"error","reason":"suffix_unavailable"}'
        else
            print_status $RED "❌ Could not reserve a backup sequence number"
        fi
        return 1
    fi
    BACKUP_SET_SUFFIX="$NEXT_BACKUP_SUFFIX"
    if [ "$use_json" = false ]; then
        print_status $YELLOW "Sequence: $BACKUP_SET_SUFFIX"
    fi

    local result_count=0
    local failure_count=0

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
                [ $backup_result -ne 0 ] && failure_count=$((failure_count + 1))
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
            [ $backup_result -ne 0 ] && failure_count=$((failure_count + 1))
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
                [ $backup_result -ne 0 ] && failure_count=$((failure_count + 1))
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
            [ $backup_result -ne 0 ] && failure_count=$((failure_count + 1))
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
        [ $backup_result -ne 0 ] && failure_count=$((failure_count + 1))
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

    # A backup whose data landed but left the site in maintenance mode, or with
    # Tome disabled, is not a success: the site is still down or not publishing.
    # The individual restore calls above discard their status, so this flag is
    # what carries the failure out.
    local state_restore_failed=false
    if [ "$DRUPAL_STATE_RESTORE_FAILED" = "true" ]; then
        state_restore_failed=true
        failure_count=$((failure_count + 1))
        if [ "$use_json" = false ]; then
            print_status $RED "❌ Drupal state was not restored after the backup"
        fi
    fi

    local backup_status="complete"
    if [ $failure_count -gt 0 ]; then
        backup_status="failed"
        if [ $result_count -gt 0 ] && [ $failure_count -lt $result_count ]; then
            backup_status="partial"
        fi
    fi

    if [ "$use_json" = true ]; then
        json_output="${json_output}},\"status\":\"$backup_status\",\"failures\":$failure_count"
        json_output="${json_output},\"state_restore_failed\":$state_restore_failed}"
        format_json "$json_output"
    else
        if [ $failure_count -gt 0 ]; then
            print_status $RED "❌ Backup completed with $failure_count failure(s)"
            return 1
        fi
        print_status $BLUE "🎉 Done."
    fi

    [ $failure_count -eq 0 ]
}

# Handle clean command
# Report a clean-command error in the caller's requested output format
# Keeps JSON consumers from receiving plain text on validation failures.
# Args:
#   $1: use_json - true to emit JSON, anything else for text
#   $2: message - error message
#   $3..: optional hint lines (text mode only)
# Returns: 1 always, so callers can `clean_command_error ...; return 1`
clean_command_error() {
    local use_json="$1"
    local message="$2"
    shift 2

    if [ "$use_json" = true ]; then
        jq -n --arg message "$message" '{operation:"clean",status:"error",message:$message}'
    else
        print_status $RED "❌ Error: $message"
        while [ $# -gt 0 ]; do
            echo "   $1"
            shift
        done
    fi

    return 1
}

run_clean_command() {
    local types_arg="all"
    local types_explicit=false

    # Only consume the first argument as a type list when it actually names
    # types. A day count or filter flag stays with the filter parser below so a
    # value such as `clean 0` is never silently read as a type selection.
    case "${1:-}" in
        static|public|db|all|*,*)
            types_arg="$1"
            types_explicit=true
            shift
            ;;
    esac

    local non_interactive=false
    local filter_type=""
    local filter_value=""
    local filter_count=0
    local use_json=false
    local unknown_args=""

    # Check for --json flag early
    if has_json_flag "$@"; then
        use_json=true
        non_interactive=true  # JSON mode implies non-interactive
    fi

    # Parse all arguments
    while [ $# -gt 0 ]; do
        # Options that take a value must have one; otherwise the shift below
        # would consume the wrong argument or abort the shell.
        case "$1" in
            --older-than|--in-range|-r|--except-range|-x|--older-than-date|--newer-than-date)
                if [ $# -lt 2 ]; then
                    clean_command_error "$use_json" "$1 requires a value"
                    return 1
                fi
                ;;
        esac

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
                else
                    unknown_args="${unknown_args:+$unknown_args }$1"
                fi
                shift
                ;;
        esac
    done

    # Never guess at an argument this command does not understand: a typo must
    # not fall through to the default retention window and delete backups.
    if [ -n "$unknown_args" ]; then
        clean_command_error "$use_json" "Unrecognized argument(s): $unknown_args" \
            "Run '$0 clean --help' for supported types, filters, and flags"
        return 1
    fi

    # Check for conflicting filters
    if [ $filter_count -gt 1 ]; then
        clean_command_error "$use_json" "Cannot mix multiple date filtering methods" \
            "Use only ONE of: days, --older-than, --in-range, --except-range, --older-than-date, --newer-than-date"
        return 1
    fi

    # Default to 30 days if no filter specified
    if [ -z "$filter_type" ]; then
        filter_type="days"
        filter_value="30"
    fi

    # Validate date formats for date-based filters
    if [ "$filter_type" = "days" ]; then
        if ! echo "$filter_value" | grep -qE '^[0-9]+$'; then
            clean_command_error "$use_json" "Invalid retention days: $filter_value" \
                "Days must be a whole number (e.g., 30)"
            return 1
        fi
    elif [ "$filter_type" = "in-range" ] || [ "$filter_type" = "except-range" ]; then
        if ! echo "$filter_value" | grep -q ':'; then
            clean_command_error "$use_json" "Invalid date range format: $filter_value" \
                "Expected format: YYYY-MM-DD:YYYY-MM-DD (e.g., 2025-01-01:2025-12-31)"
            return 1
        fi
        local start_date=$(echo "$filter_value" | cut -d: -f1)
        local end_date=$(echo "$filter_value" | cut -d: -f2)
        if [ -n "$start_date" ] && [ -z "$(date_to_epoch "$start_date")" ]; then
            clean_command_error "$use_json" "Invalid start date format: $start_date" \
                "Expected format: YYYY-MM-DD (e.g., 2025-01-01)"
            return 1
        fi
        if [ -n "$end_date" ] && [ -z "$(date_to_epoch "$end_date")" ]; then
            clean_command_error "$use_json" "Invalid end date format: $end_date" \
                "Expected format: YYYY-MM-DD (e.g., 2025-12-31)"
            return 1
        fi
        # Check that start <= end if both provided
        if [ -n "$start_date" ] && [ -n "$end_date" ]; then
            local start_epoch=$(date_to_epoch "$start_date")
            local end_epoch=$(date_to_epoch "$end_date")
            if [ "$start_epoch" -gt "$end_epoch" ]; then
                clean_command_error "$use_json" "Invalid date range: $filter_value" \
                    "Start date ($start_date) must be before or equal to end date ($end_date)"
                return 1
            fi
        fi
    elif [ "$filter_type" = "older-date" ] || [ "$filter_type" = "newer-date" ]; then
        if [ -z "$(date_to_epoch "$filter_value")" ]; then
            clean_command_error "$use_json" "Invalid date format: $filter_value" \
                "Expected format: YYYY-MM-DD (e.g., 2025-01-01)"
            return 1
        fi
    fi

    # Resolve the requested types to an exact set before anything destructive
    # happens, so cleanup only ever touches the types the operator named.
    local backup_types=""
    backup_types=$(normalize_backup_types "$types_arg")
    if [ $? -ne 0 ]; then
        clean_command_error "$use_json" "Invalid backup types: $types_arg"
        return 1
    fi

    # 'all' deletes without regard to age. Fail closed before any deletion if the
    # request cannot be carried out for every type named.
    if [ "$filter_type" = "all" ]; then
        if [ "$types_explicit" != true ]; then
            clean_command_error "$use_json" "Deleting ALL backups requires an explicit type argument" \
                "Example: $0 clean static,public all"
            return 1
        fi
        if has_exact_backup_type "$backup_types" "db"; then
            clean_command_error "$use_json" "Database cleanup does not support 'all' (minimum retention is $RETENTION_MIN_HOURS hours)" \
                "Remove static/public backups with: $0 clean static,public all" \
                "Remove database backups with: $0 delete <tag> db"
            return 1
        fi
    fi

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

    if [ "$use_json" != true ]; then
        print_status $BLUE "🧹 Cleaning up backups: $backup_types"
    fi

    local failures=0
    local static_status="not-requested"
    local public_status="not-requested"
    local db_status="not-requested"

    # Clean static and/or public backups. The exact type set is passed through so
    # the helper only touches the namespaces that were requested.
    if has_exact_backup_type "$backup_types" "static" || has_exact_backup_type "$backup_types" "public"; then
        clean_old_backups "$filter_type" "$filter_value" "$backup_types" || failures=$((failures + 1))
    fi

    # Clean database backups if requested
    if has_exact_backup_type "$backup_types" "db"; then
        cleanup_old_db_backups "$filter_type" "$filter_value" || failures=$((failures + 1))
    fi

    # Two independent signals decide the outcome: the helper's exit status above
    # and its per-type counters below. Either one failing marks the run failed, so
    # neither a bad return code nor an uncounted namespace can slip through as
    # success. An unset counter means the type never ran, which is also a failure.
    if has_exact_backup_type "$backup_types" "static"; then
        if [ "${CLEAN_STATIC_FAILED:-1}" -eq 0 ]; then
            static_status="complete"
        else
            static_status="failed"
        fi
    fi
    if has_exact_backup_type "$backup_types" "public"; then
        if [ "${CLEAN_PUBLIC_FAILED:-1}" -eq 0 ]; then
            public_status="complete"
        else
            public_status="failed"
        fi
    fi
    if has_exact_backup_type "$backup_types" "db"; then
        if [ -n "${CLEAN_DB_SKIPPED:-}" ]; then
            db_status="skipped"
        elif [ "${CLEAN_DB_FAILED:-1}" -eq 0 ]; then
            db_status="complete"
        else
            db_status="failed"
        fi
    fi

    if [ "$static_status" = "failed" ] || [ "$public_status" = "failed" ] || [ "$db_status" = "failed" ]; then
        failures=$((failures + 1))
    fi

    local overall_status="complete"
    if [ "$failures" -gt 0 ]; then
        overall_status="failed"
    fi

    if [ "$use_json" = true ]; then
        jq -n \
            --arg filter_type "$filter_type" \
            --arg filter_value "$filter_value" \
            --arg types "$backup_types" \
            --arg status "$overall_status" \
            --arg static_status "$static_status" \
            --arg public_status "$public_status" \
            --arg db_status "$db_status" \
            --argjson static_deleted "${CLEAN_STATIC_DELETED:-0}" \
            --argjson static_failed "${CLEAN_STATIC_FAILED:-0}" \
            --argjson public_deleted "${CLEAN_PUBLIC_DELETED:-0}" \
            --argjson public_failed "${CLEAN_PUBLIC_FAILED:-0}" \
            --argjson db_deleted "${CLEAN_DB_DELETED:-0}" \
            --argjson db_failed "${CLEAN_DB_FAILED:-0}" \
            '{
                operation: "clean",
                filter: {type: $filter_type, value: $filter_value},
                types: $types,
                results: {
                    static: {status: $static_status, deleted: $static_deleted, failed: $static_failed},
                    public: {status: $public_status, deleted: $public_deleted, failed: $public_failed},
                    db: {status: $db_status, deleted: $db_deleted, failed: $db_failed}
                },
                status: $status
            }'
    elif [ "$failures" -gt 0 ]; then
        print_status $RED "❌ Cleanup did not complete for all requested types:"
        echo "   static: $static_status  public: $public_status  db: $db_status"
    else
        print_status $BLUE "🎉 Cleanup complete."
    fi

    if [ "$failures" -gt 0 ]; then
        return 1
    fi

    return 0
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

# Set NEXT_BACKUP_TAG for one component.
#
# The suffix arrives without a leading delimiter; this function owns the joining.
# Callers used to pass "-post-deploy", which produced the double delimiter in
# "<base>--post-deploy-0" and was the reason sequence discovery never matched.
#
# When BACKUP_SET_SUFFIX is set, that number is used instead of allocating one, so
# every component of a set shares a tag. It is empty for callers that create a
# single component directly, such as the restore's recovery point.
prepare_backup_tag() {
    local backup_type="$1"
    local base_tag="$2"
    local backup_suffix="$3"
    local state_type="$4"
    local state_prepared="$5"

    local stem="$base_tag"
    local legacy_stem=""
    if [ -n "$backup_suffix" ]; then
        stem="${base_tag}-${backup_suffix}"
        legacy_stem="${base_tag}--${backup_suffix}"
    fi

    if [ -n "$BACKUP_SET_SUFFIX" ]; then
        NEXT_BACKUP_SUFFIX="$BACKUP_SET_SUFFIX"
    elif ! get_next_backup_suffix "$backup_type" "$stem" "$legacy_stem"; then
        print_status $RED "❌ Failed to determine ${backup_type} backup suffix"
        [ "$state_prepared" = "true" ] && restore_drupal_state "$state_type"
        return 1
    fi

    NEXT_BACKUP_TAG="${stem}-${NEXT_BACKUP_SUFFIX}"
}

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
    # NIST 800-53: CP-10 - Preserve service state throughout recovery.
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

    if ! prepare_backup_tag "db" "$base_tag" "$backup_suffix" "maintenance" "$drupal_state_prepared"; then
        return 1
    fi
    DB_BACKUP_TAG="$NEXT_BACKUP_TAG"

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

    # No trap is installed here on purpose. This function used to set its own
    # EXIT/INT/TERM handler and then clear it with `trap - EXIT ERR` on success,
    # which silently discarded the caller's handler — the restore path had to
    # re-arm its trap after every call to work around it (N-03). The temp paths
    # are globals, so the caller's handler removes them: backup_cleanup for the
    # backup command, restore_cleanup for the restore path. Every return below
    # also removes them directly, so the handler is only the signal safety net.

    # Create database dump using drush
    if command -v drush >/dev/null 2>&1; then
        # Clear cache first, then create dump to SQL file
        drush cr >> "$LOGFILE" 2>&1
        drush sql:dump --result-file="$TEMP_SQL" >> "$LOGFILE" 2>&1
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
    if ! validate_sql_dump "$TEMP_SQL" >> "$LOGFILE" 2>&1; then
        audit_log "backup_database_failed" "error" "SQL dump validation failed" "backup_tag=$DB_BACKUP_TAG reason=invalid_structure"
        log_message "❌ ERROR: SQL dump validation failed" | tee -a "$LOGFILE"
        rm -f "$TEMP_SQL" "$TEMP_GZIP" 2>/dev/null
        [ "$drupal_state_prepared" = "true" ] && restore_drupal_state "maintenance"
        return 1
    fi

    # Compress the SQL file using gzip
    log_message "🗜️ Compressing..." | tee -a "$LOGFILE"
    gzip -c "$TEMP_SQL" > "$TEMP_GZIP" 2>> "$LOGFILE"
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

    aws s3 cp "$TEMP_GZIP" "$S3_DB_PATH" --only-show-errors $S3_EXTRA_PARAMS >> "$LOGFILE" 2>&1
    UPLOAD_EXIT_CODE=$?

    # Upload checksum file if it exists
    if [ -s "$TEMP_CHECKSUM" ]; then
        S3_CHECKSUM_PATH="s3://${BUCKET_NAME}/${AUTO_DB_BACKUP_PATH}/${DB_BACKUP_TAG}.sql.gz.sha256"
        if ! aws s3 cp "$TEMP_CHECKSUM" "$S3_CHECKSUM_PATH" --only-show-errors $S3_EXTRA_PARAMS >> "$LOGFILE" 2>&1; then
            log_message "⚠️ Warning: Checksum upload failed" | tee -a "$LOGFILE"
        fi
    fi

    rm -f "$TEMP_SQL" "$TEMP_GZIP" "$TEMP_CHECKSUM" 2>/dev/null

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

    if ! prepare_backup_tag "static" "$base_tag" "$backup_suffix" "tome" "$drupal_state_prepared"; then
        return 1
    fi
    BACKUP_TAG="$NEXT_BACKUP_TAG"

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

    if ! prepare_backup_tag "public" "$base_tag" "$backup_suffix" "tome" "$drupal_state_prepared"; then
        return 1
    fi
    BACKUP_TAG="$NEXT_BACKUP_TAG"

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
            # Convert N days to a forward date range: "{N_days_ago}:" = from that date onward.
            # Use epoch arithmetic for BusyBox date compatibility (no "N days ago" string support).
            local now_epoch since_epoch since_date
            now_epoch=$(date +%s 2>/dev/null)
            since_epoch=$((now_epoch - filter_arg * 86400))
            since_date=$(date -u -d "@${since_epoch}" '+%Y-%m-%d' 2>/dev/null || \
                         date -u -r "$since_epoch" '+%Y-%m-%d' 2>/dev/null)
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
# 'all' and '0' are rejected: bulk removal must go through the delete command
# Every list and delete result is checked and each deletion is verified as gone,
# so a failed listing or delete cannot be reported as a completed cleanup.
# Args:
#   $1: filter_type - Type of filter (days, in-range, except-range, older-date, newer-date)
#   $2: filter_value - Filter value (days number, date range, or date)
# Sets: CLEAN_DB_DELETED/CLEAN_DB_FAILED, and CLEAN_DB_SKIPPED when cleanup was
#       not attempted because ENABLE_DB_AUTO_CLEANUP is not "true"
# Returns: 0 if the requested cleanup completed, 1 otherwise
cleanup_old_db_backups() {
    local filter_type="${1:-days}"
    local filter_value="${2:-$DB_BACKUP_RETENTION_DAYS}"

    CLEAN_DB_DELETED=""
    CLEAN_DB_FAILED=""
    CLEAN_DB_SKIPPED=""

    if [ "$ENABLE_DB_AUTO_CLEANUP" != "true" ]; then
        log_message "⚠️ Database automatic cleanup is disabled (ENABLE_DB_AUTO_CLEANUP=$ENABLE_DB_AUTO_CLEANUP)"
        CLEAN_DB_SKIPPED="auto-cleanup-disabled"
        return 0
    fi

    setup_s3_vars || return 1

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
        if ! echo "$filter_value" | grep -qE '^[0-9]+$'; then
            log_message "❌ Error: Retention days must be a whole number: $filter_value"
            return 1
        fi
        if [ "$filter_value" -lt 2 ]; then
            log_message "❌ Error: Minimum retention is 2 days ($RETENTION_MIN_HOURS hours)"
            return 1
        fi
    fi

    # Display what we're doing using consolidated helper
    audit_log "cleanup_database_started" "info" "Database cleanup initiated" "filter_type=$filter_type filter_value=$filter_value"
    log_message "$(show_filter_message "$filter_type" "$filter_value" "database backups")"

    # Calculate minimum retention cutoff using epoch arithmetic for BusyBox date
    # compatibility: it supports neither BSD "-v-48H" nor GNU "-d 48 hours ago",
    # so both spellings returned empty in the CMS container and this cleanup
    # aborted before deleting anything.
    local now_epoch=$(date -u '+%s')
    if [ -z "$now_epoch" ]; then
        log_message "❌ Error: Could not read current time for retention calculation"
        return 1
    fi
    local min_retention_seconds="${RETENTION_MIN_SECONDS:-}"
    if [ -z "$min_retention_seconds" ] && [ -n "${RETENTION_MIN_HOURS:-}" ]; then
        min_retention_seconds=$((RETENTION_MIN_HOURS * 3600))
    fi

    # The floor is a safety control, so an unreadable value must stop the
    # cleanup. Never fall back to a bare arithmetic default: an unset variable
    # evaluates to 0, which would silently drop the protection window and expose
    # backups created today to deletion.
    if ! echo "$min_retention_seconds" | grep -qE '^[0-9]+$'; then
        log_message "❌ Error: Minimum retention window is not configured (RETENTION_MIN_HOURS)"
        return 1
    fi

    local min_retention_epoch=$((now_epoch - min_retention_seconds))

    local listing=""
    local list_status=0
    local backup_path=""
    local backup_date=""
    local backup_epoch=""
    local deleted=0
    local failures=0

    listing=$(s3_list_backup_namespace keys "$AUTO_DB_BACKUP_PATH")
    list_status=$?
    if [ "$list_status" -ne 0 ]; then
        audit_log "cleanup_database_list_failed" "error" "Could not list database backups for cleanup" "base_path=$AUTO_DB_BACKUP_PATH filter_type=$filter_type"
        log_message "❌ Error: Failed to list database backups (s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/) - nothing deleted"
        CLEAN_DB_DELETED=0
        CLEAN_DB_FAILED=1
        return 1
    fi

    # Fed by redirection rather than a pipe so delete failures below are counted
    # instead of being discarded with the subshell.
    while IFS= read -r backup_path; do
        case "$backup_path" in
            *.sql.gz) ;;
            *) continue ;;
        esac

        backup_date=$(extract_date_from_backup_name "$backup_path")
        [ -n "$backup_date" ] || continue

        # Convert backup date to epoch for comparison
        backup_epoch=$(date_to_epoch "$backup_date")

        # Skip if backup is newer than minimum retention (safety check)
        if [ -n "$backup_epoch" ] && [ "$backup_epoch" -gt "$min_retention_epoch" ]; then
            log_message "⏭️  Skipping recent backup (< $RETENTION_MIN_HOURS hours): $backup_path"
            continue
        fi

        matches_clean_filter "$backup_date" "$filter_type" "$filter_value" || continue

        log_message "🗑️ Removing old database backup: $backup_path (date: $backup_date)"
        if ! aws s3 rm "s3://$BUCKET_NAME/$backup_path" --only-show-errors $S3_EXTRA_PARAMS; then
            audit_log "backup_database_delete_failed" "error" "Failed to delete database backup" "backup_path=$backup_path backup_date=$backup_date"
            log_message "❌ Failed to delete database backup: $backup_path"
            failures=$((failures + 1))
            continue
        fi

        # Verify the payload is gone before counting a success.
        if aws s3api head-object --bucket "$BUCKET_NAME" --key "$backup_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
            audit_log "backup_database_delete_unverified" "error" "Database backup still present after delete" "backup_path=$backup_path"
            log_message "❌ Database backup not verified as removed: $backup_path"
            failures=$((failures + 1))
            continue
        fi

        # The checksum sidecar is optional; only a delete failure on an existing
        # sidecar counts against the cleanup.
        if aws s3api head-object --bucket "$BUCKET_NAME" --key "${backup_path}.sha256" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
            if ! aws s3 rm "s3://$BUCKET_NAME/${backup_path}.sha256" --only-show-errors $S3_EXTRA_PARAMS; then
                audit_log "backup_database_checksum_delete_failed" "error" "Failed to delete database backup checksum" "backup_path=${backup_path}.sha256"
                log_message "❌ Failed to delete database backup checksum: ${backup_path}.sha256"
                failures=$((failures + 1))
                continue
            fi
        fi

        audit_log "backup_database_deleted" "info" "Database backup deleted" "backup_path=$backup_path backup_date=$backup_date filter_type=$filter_type"
        deleted=$((deleted + 1))
    done <<EOF
$listing
EOF

    CLEAN_DB_DELETED=$deleted
    CLEAN_DB_FAILED=$failures

    if [ "$failures" -gt 0 ]; then
        audit_log "cleanup_database_failed" "error" "Database cleanup completed with failures" "filter_type=$filter_type filter_value=$filter_value deleted=$deleted failures=$failures"
        log_message "❌ Database backup cleanup incomplete: $deleted removed, $failures failed"
        return 1
    fi

    audit_log "cleanup_database_success" "success" "Database cleanup completed" "filter_type=$filter_type filter_value=$filter_value deleted=$deleted"
    log_message "✅ Database backup cleanup complete: $deleted removed"
    return 0
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

    echo ""
    print_status $GREEN "Database Backups:"
    aws s3 ls s3://"$BUCKET_NAME"/$AUTO_DB_BACKUP_PATH/ --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | while read -r line; do
        backup_key=$(echo "$line" | awk '{print $4}')
        backup_name=$(basename "$backup_key" .sql.gz)
        backup_date=$(extract_date_from_backup_name "$backup_name")

        if [ -n "$backup_date" ]; then
            if check_backup_date "$backup_date"; then
                echo "$line"
            fi
        fi
    done
}

# Clean up old static and public backups based on filter criteria
# Only the namespaces named in the requested type set are touched, so cleaning
# one type can never delete the other. Every list and delete result is checked,
# each deletion is verified as gone, and a non-zero status is returned if any
# part of the requested cleanup did not complete.
# Args:
#   $1: filter_type - Type of filter (days, in-range, except-range, older-date, newer-date, all)
#   $2: filter_value - Filter value (days number, date range, or date)
#   $3: types - Normalized type set to clean (default: static,public)
# Sets: CLEAN_STATIC_DELETED/CLEAN_STATIC_FAILED, CLEAN_PUBLIC_DELETED/CLEAN_PUBLIC_FAILED
#       (empty when the type was not requested)
# Returns: 0 if every requested namespace was fully cleaned, 1 otherwise
clean_old_backups() {
    local filter_type="${1:-days}"
    local filter_value="${2:-$BACKUP_RETENTION_DAYS}"
    local types="${3:-static,public}"

    setup_s3_vars || return 1

    local namespace=""
    local label=""
    local base_path=""
    local listing=""
    local list_status=0
    local backup_name=""
    local backup_date=""
    local remaining=""
    local verify_status=0
    local ns_deleted=0
    local ns_failures=0
    local total_failures=0

    CLEAN_STATIC_DELETED=""
    CLEAN_STATIC_FAILED=""
    CLEAN_PUBLIC_DELETED=""
    CLEAN_PUBLIC_FAILED=""

    print_status $YELLOW "$(show_filter_message "$filter_type" "$filter_value" "$(echo "$types" | tr ',' '/') backups")"

    for namespace in static public; do
        has_exact_backup_type "$types" "$namespace" || continue

        case "$namespace" in
            static)
                label="static site"
                base_path="$AUTO_STATIC_BACKUP_PATH"
                ;;
            public)
                label="public files"
                base_path="$AUTO_PUBLIC_BACKUP_PATH"
                ;;
        esac

        ns_deleted=0
        ns_failures=0

        listing=$(s3_list_backup_namespace prefixes "$base_path")
        list_status=$?
        if [ "$list_status" -ne 0 ]; then
            audit_log "cleanup_${namespace}_list_failed" "error" "Could not list backups for cleanup" "base_path=$base_path filter_type=$filter_type"
            print_status $RED "❌ Failed to list $label backups (s3://$BUCKET_NAME/$base_path/) - nothing deleted for this type"
            ns_failures=$((ns_failures + 1))
        fi

        # Fed by redirection rather than a pipe so the counters below survive the
        # loop; in a pipeline the loop body runs in a subshell and every delete
        # failure would be discarded.
        while IFS= read -r backup_name; do
            [ -n "$backup_name" ] || continue

            backup_date=$(extract_date_from_backup_name "$backup_name")

            if [ "$filter_type" != "all" ]; then
                if [ -z "$backup_date" ]; then
                    print_status $YELLOW "⏭️  Skipping $label backup with no date in its name: $backup_name"
                    continue
                fi
                matches_clean_filter "$backup_date" "$filter_type" "$filter_value" || continue
            fi

            print_status $YELLOW "Removing $label backup: $backup_name${backup_date:+ (date: $backup_date)}"
            if ! aws s3 rm "s3://$BUCKET_NAME/$base_path/$backup_name/" --only-show-errors --recursive $S3_EXTRA_PARAMS; then
                audit_log "backup_${namespace}_delete_failed" "error" "Failed to delete $label backup" "backup_tag=$backup_name filter_type=$filter_type"
                print_status $RED "❌ Failed to delete $label backup: $backup_name"
                ns_failures=$((ns_failures + 1))
                continue
            fi

            # Verify the objects are actually gone before counting a success.
            remaining=$(s3_list_backup_namespace keys "$base_path/$backup_name")
            verify_status=$?
            if [ "$verify_status" -ne 0 ] || [ -n "$remaining" ]; then
                audit_log "backup_${namespace}_delete_unverified" "error" "Objects remain after $label backup delete" "backup_tag=$backup_name"
                print_status $RED "❌ $label backup not verified as removed: $backup_name"
                ns_failures=$((ns_failures + 1))
                continue
            fi

            audit_log "backup_${namespace}_deleted" "info" "$label backup deleted" "backup_tag=$backup_name backup_date=$backup_date filter_type=$filter_type"
            ns_deleted=$((ns_deleted + 1))
        done <<EOF
$listing
EOF

        case "$namespace" in
            static)
                CLEAN_STATIC_DELETED=$ns_deleted
                CLEAN_STATIC_FAILED=$ns_failures
                ;;
            public)
                CLEAN_PUBLIC_DELETED=$ns_deleted
                CLEAN_PUBLIC_FAILED=$ns_failures
                ;;
        esac

        total_failures=$((total_failures + ns_failures))

        if [ "$ns_failures" -gt 0 ]; then
            print_status $RED "❌ $label cleanup incomplete: $ns_deleted removed, $ns_failures failed"
        else
            print_status $GREEN "✅ $label cleanup completed: $ns_deleted removed"
        fi
    done

    if [ "$total_failures" -gt 0 ]; then
        audit_log "cleanup_static_public_failed" "error" "Static/public cleanup completed with failures" "types=$types failures=$total_failures filter_type=$filter_type"
        return 1
    fi

    return 0
}


# Clean all backup types
cleanup_all_old_backups() {
    local filter_type=${1:-days}
    local filter_value=${2:-$BACKUP_RETENTION_DAYS}
    local failures=0

    print_status $BLUE "🧹 Cleaning up all old automatic backups..."

    clean_old_backups "$filter_type" "$filter_value" "static,public" || failures=$((failures + 1))
    cleanup_old_db_backups "$filter_type" "$filter_value" || failures=$((failures + 1))

    if [ "$failures" -gt 0 ]; then
        print_status $RED "❌ Backup cleanup did not complete for all types."
        return 1
    fi

    print_status $GREEN "✅ All backup cleanup completed."
    return 0
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
                    aws s3 rm s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz.sha256 --only-show-errors $S3_EXTRA_PARAMS >/dev/null 2>&1 || true
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
            --skip-confirmation|--yes|--force|-y|--non-interactive)
                # Skip this flag, handled elsewhere
                shift
                ;;
            --no-recovery-point|--force-destructive-sync)
                # Skip these flags, handled elsewhere. They must be listed here or
                # the catch-all below would treat them as the backup tag.
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

# ===================================================================
# RESTORE SAFETY: PREFLIGHT, RECOVERY POINT, COMPENSATION
# ===================================================================
#
# Restore mutates three independent stores (the static S3 tree, the public S3
# tree, and the database) which cannot be committed as one transaction. S3 has no
# atomic prefix swap, so staging a copy and "switching" would still leave a
# non-atomic window while doubling the data copied. The protection model is
# therefore:
#
#   1. Complete every check for every requested component before the first
#      mutation - including downloading, verifying, and decompressing the
#      database dump, which previously happened only after the S3 trees had
#      already been overwritten.
#   2. Capture a pre-restore recovery point and verify it exists.
#   3. Mutate, and compensate from that recovery point if a later phase fails,
#      so a partial restore converges back to a single consistent generation.
#
# NIST 800-53: CP-10, CP-9, SI-7.

# State shared with restore_cleanup() so an interrupted or failed restore cannot
# leave temp files behind or leave Drupal in maintenance mode.
RESTORE_TEMP_DIR=""
RESTORE_STATE_PREPARED=false
RESTORE_APPLIED_PHASES=""
RESTORE_POINT_STATIC=""
RESTORE_POINT_PUBLIC=""
RESTORE_POINT_DB=""
RESTORE_DB_SQL_FILE=""

# Release restore resources and Drupal state on any exit path
# Installed as an EXIT/INT/TERM trap so a signal during a long S3 sync cannot
# strand the site in maintenance mode with Tome disabled.
RESTORE_CLEANUP_DONE=false
restore_cleanup() {
    local exit_code=$?

    # Runs once: the signal handlers exit explicitly, which re-triggers EXIT.
    if [ "$RESTORE_CLEANUP_DONE" = "true" ]; then
        return $exit_code
    fi
    RESTORE_CLEANUP_DONE=true

    if [ -n "$RESTORE_TEMP_DIR" ] && [ -d "$RESTORE_TEMP_DIR" ]; then
        rm -rf "$RESTORE_TEMP_DIR"
    fi
    RESTORE_TEMP_DIR=""
    RESTORE_DB_SQL_FILE=""

    if [ "$RESTORE_STATE_PREPARED" = "true" ]; then
        RESTORE_STATE_PREPARED=false
        if ! restore_drupal_state "both"; then
            print_status $RED "❌ CRITICAL: Could not restore Drupal state"
            print_status $YELLOW "   Verify and fix with: $0 state enable both"
            audit_log "restore_state_restore_failed" "error" "Drupal state could not be restored after restore" ""
        fi
    fi

    return $exit_code
}

# Verify a static/public backup is safe to sync over live content
# The restore uses "aws s3 sync --delete", so a truncated or partially written
# backup silently deletes the live objects it does not contain. An empty backup
# would delete the entire live tree.
# Args:
#   $1: label - human-readable component name
#   $2: source_path - backup prefix (no bucket, no trailing slash)
#   $3: live_path - live prefix that would be overwritten
#   $4: force - "true" to proceed despite the shrink guard
# Returns: 0 if safe to restore, 1 otherwise
restore_preflight_s3_component() {
    local label="$1"
    local source_path="$2"
    local live_path="$3"
    local force="$4"

    local source_count=""
    local live_count=""
    local min_required=0

    source_count=$(s3_count_objects "$source_path")
    if [ $? -ne 0 ]; then
        print_status $RED "❌ Preflight failed: could not list the $label backup ($source_path)"
        return 1
    fi

    if [ "$source_count" -eq 0 ]; then
        audit_log "restore_preflight_failed" "error" "Backup component is empty" "component=$label source_path=$source_path"
        print_status $RED "❌ Preflight failed: the $label backup contains no objects"
        print_status $YELLOW "   Restoring it would delete all live $label content."
        return 1
    fi

    live_count=$(s3_count_objects "$live_path")
    if [ $? -ne 0 ]; then
        print_status $RED "❌ Preflight failed: could not list live $label content ($live_path)"
        return 1
    fi

    print_status $GREEN "✓ $label backup readable: $source_count objects (live: $live_count)"

    if [ "$RESTORE_MIN_SOURCE_PERCENT" -gt 0 ] && [ "$live_count" -gt 0 ]; then
        min_required=$((live_count * RESTORE_MIN_SOURCE_PERCENT / 100))
        if [ "$source_count" -lt "$min_required" ]; then
            print_status $RED "❌ The $label backup holds $source_count objects but $live_count are live"
            print_status $YELLOW "   Below the ${RESTORE_MIN_SOURCE_PERCENT}% floor of $min_required objects;"
            print_status $YELLOW "   'sync --delete' would remove the difference from the live site."
            if [ "$force" = "true" ]; then
                audit_log "restore_shrink_guard_overridden" "warning" "Destructive sync guard overridden" "component=$label source_count=$source_count live_count=$live_count"
                print_status $YELLOW "⚠️  --force-destructive-sync given: continuing anyway"
            else
                audit_log "restore_shrink_guard_blocked" "error" "Restore blocked by destructive sync guard" "component=$label source_count=$source_count live_count=$live_count"
                print_status $YELLOW "   Re-run with --force-destructive-sync if this is intended."
                return 1
            fi
        fi
    fi

    return 0
}

# Download and fully validate the database dump before anything is mutated
# Everything that can reject a bad dump happens here: download, checksum,
# archive integrity, free space, decompression, structure, and content. Only the
# import itself remains once mutation begins.
# Args:
#   $1: db_backup_key - object name within AUTO_DB_BACKUP_PATH
# Sets: RESTORE_DB_SQL_FILE on success
# Returns: 0 if the dump is ready to import, 1 otherwise
restore_preflight_database() {
    local db_backup_key="$1"

    local gz_file="$RESTORE_TEMP_DIR/restore.sql.gz"
    local sql_file="$RESTORE_TEMP_DIR/restore.sql"
    local checksum_file="$RESTORE_TEMP_DIR/restore.sha256"
    local expected_checksum=""
    local actual_checksum=""
    local avail_kb=0
    local needed_kb=0

    print_status $YELLOW "🔄 Downloading database backup for validation..."
    if ! aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/$db_backup_key" "$gz_file" --only-show-errors $S3_EXTRA_PARAMS; then
        audit_log "restore_preflight_failed" "error" "Database backup download failed" "backup=$db_backup_key"
        print_status $RED "❌ Preflight failed: could not download the database backup"
        return 1
    fi
    chmod 600 "$gz_file"

    if aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${db_backup_key}.sha256" "$checksum_file" --only-show-errors $S3_EXTRA_PARAMS 2>/dev/null; then
        chmod 600 "$checksum_file"
        expected_checksum=$(awk '{print $1}' "$checksum_file")
        actual_checksum=$(sha256sum "$gz_file" | awk '{print $1}')
        if [ "$expected_checksum" != "$actual_checksum" ]; then
            audit_log "restore_preflight_failed" "error" "Checksum mismatch" "backup=$db_backup_key expected=$expected_checksum actual=$actual_checksum"
            print_status $RED "❌ Preflight failed: checksum mismatch, the backup may be corrupt"
            print_status $YELLOW "   Expected: $expected_checksum"
            print_status $YELLOW "   Got:      $actual_checksum"
            return 1
        fi
        print_status $GREEN "✓ Checksum verified"
    else
        print_status $YELLOW "⚠️  No checksum sidecar found - integrity cannot be verified"
    fi

    # Archive integrity before spending disk on decompression. This is also the
    # real guard against a truncated dump: a cut-off gzip stream fails here.
    if ! gunzip -t "$gz_file" 2>/dev/null; then
        audit_log "restore_preflight_failed" "error" "Corrupt database archive" "backup=$db_backup_key"
        print_status $RED "❌ Preflight failed: the database archive is corrupt"
        return 1
    fi
    print_status $GREEN "✓ Archive integrity verified"

    # Refuse to decompress into a filesystem that cannot hold the result.
    avail_kb=$(df -P "$RESTORE_TEMP_DIR" 2>/dev/null | awk 'NR==2 {print $4}')
    needed_kb=$(gzip -l "$gz_file" 2>/dev/null | awk 'NR==2 {print int($2/1024)}')
    if [ -z "$needed_kb" ] || [ "$needed_kb" -le 0 ] 2>/dev/null; then
        # gzip -l is unavailable or unreliable here; assume a 10x ratio.
        needed_kb=$(( $(wc -c < "$gz_file") / 1024 * 10 ))
    fi
    if [ -n "$avail_kb" ] && [ "$avail_kb" -gt 0 ] 2>/dev/null; then
        if [ "$needed_kb" -gt "$avail_kb" ]; then
            audit_log "restore_preflight_failed" "error" "Insufficient disk space for restore" "needed_kb=$needed_kb available_kb=$avail_kb"
            print_status $RED "❌ Preflight failed: not enough disk space to decompress the dump"
            print_status $YELLOW "   Needs ~${needed_kb}KB, ${avail_kb}KB available"
            return 1
        fi
        print_status $GREEN "✓ Disk space sufficient (~${needed_kb}KB needed, ${avail_kb}KB free)"
    fi

    print_status $YELLOW "🔄 Decompressing and validating dump..."
    if ! gunzip -c "$gz_file" > "$sql_file" 2>/dev/null; then
        print_status $RED "❌ Preflight failed: could not decompress the database backup"
        return 1
    fi
    chmod 600 "$sql_file"

    if ! validate_sql_dump "$sql_file"; then
        audit_log "restore_preflight_failed" "error" "SQL dump structure validation failed" "backup=$db_backup_key"
        print_status $RED "❌ Preflight failed: the dump does not look like a complete SQL dump"
        return 1
    fi

    if ! validate_sql_content "$sql_file"; then
        audit_log "restore_preflight_failed" "error" "SQL content validation failed" "backup=$db_backup_key"
        print_status $RED "❌ Preflight failed: SQL content validation rejected the dump"
        return 1
    fi

    RESTORE_DB_SQL_FILE="$sql_file"
    return 0
}

# Capture a verified pre-restore recovery point for the components being mutated
# Reuses the normal backup implementations so there is only one backup code path.
# The ENABLE_* toggles and smart-skip are overridden for the duration: those
# govern routine automatic backups, and a recovery point that silently does
# nothing would leave the restore with no way back.
# Args: $1/$2/$3 - "yes" when static/public/db will be restored
# Returns: 0 if every required recovery component was created and verified
restore_create_recovery_point() {
    local want_static="$1"
    local want_public="$2"
    local want_db="$3"

    local timestamp=$(date +"%Y-%m-%d")
    local failures=0
    local saved_static="$ENABLE_STATIC_AUTO_BACKUPS"
    local saved_public="$ENABLE_PUBLIC_AUTO_BACKUPS"
    local saved_db="$ENABLE_DB_BACKUPS"
    local saved_smart="$ENABLE_SMART_PUBLIC_BACKUP"

    ENABLE_STATIC_AUTO_BACKUPS=true
    ENABLE_PUBLIC_AUTO_BACKUPS=true
    ENABLE_DB_BACKUPS=true
    ENABLE_SMART_PUBLIC_BACKUP=false

    print_status $BLUE "🛟 Creating pre-restore recovery point (prefix: $RESTORE_POINT_PREFIX)..."

    if [ "$want_static" = "yes" ]; then
        BACKUP_TAG=""
        create_static_backup "$RESTORE_POINT_PREFIX" "" "$timestamp" "true" || true
        RESTORE_POINT_STATIC="$BACKUP_TAG"
        if [ -z "$RESTORE_POINT_STATIC" ] || [ "$(s3_count_objects "$AUTO_STATIC_BACKUP_PATH/$RESTORE_POINT_STATIC")" = "0" ]; then
            print_status $RED "❌ Could not create a static recovery point"
            RESTORE_POINT_STATIC=""
            failures=$((failures + 1))
        else
            print_status $GREEN "✓ Static recovery point: $RESTORE_POINT_STATIC"
        fi
    fi

    if [ "$want_public" = "yes" ]; then
        BACKUP_TAG=""
        create_public_backup "$RESTORE_POINT_PREFIX" "" "$timestamp" "true" || true
        RESTORE_POINT_PUBLIC="$BACKUP_TAG"
        if [ -z "$RESTORE_POINT_PUBLIC" ] || [ "$(s3_count_objects "$AUTO_PUBLIC_BACKUP_PATH/$RESTORE_POINT_PUBLIC")" = "0" ]; then
            print_status $RED "❌ Could not create a public files recovery point"
            RESTORE_POINT_PUBLIC=""
            failures=$((failures + 1))
        else
            print_status $GREEN "✓ Public files recovery point: $RESTORE_POINT_PUBLIC"
        fi
    fi

    if [ "$want_db" = "yes" ]; then
        DB_BACKUP_TAG=""
        create_db_backup "$RESTORE_POINT_PREFIX" "" "$timestamp" "true" || true
        RESTORE_POINT_DB="$DB_BACKUP_TAG"
        if [ -z "$RESTORE_POINT_DB" ] || ! aws s3api head-object --bucket "$BUCKET_NAME" \
                --key "$AUTO_DB_BACKUP_PATH/${RESTORE_POINT_DB}.sql.gz" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
            print_status $RED "❌ Could not create a database recovery point"
            RESTORE_POINT_DB=""
            failures=$((failures + 1))
        else
            print_status $GREEN "✓ Database recovery point: ${RESTORE_POINT_DB}.sql.gz"
        fi
    fi

    ENABLE_STATIC_AUTO_BACKUPS="$saved_static"
    ENABLE_PUBLIC_AUTO_BACKUPS="$saved_public"
    ENABLE_DB_BACKUPS="$saved_db"
    ENABLE_SMART_PUBLIC_BACKUP="$saved_smart"

    if [ "$failures" -gt 0 ]; then
        audit_log "restore_recovery_point_failed" "error" "Pre-restore recovery point incomplete" "failures=$failures"
        return 1
    fi

    audit_log "restore_recovery_point_created" "success" "Pre-restore recovery point created" "static=$RESTORE_POINT_STATIC public=$RESTORE_POINT_PUBLIC db=$RESTORE_POINT_DB"
    return 0
}

# Roll every already-mutated component back to the pre-restore recovery point
# Called when a phase fails after earlier phases succeeded, so the environment
# converges back to one consistent generation instead of a mixed one.
# Args: $1 - space-separated list of applied phases
# Returns: 0 if every applied phase was rolled back, 1 otherwise
restore_compensate() {
    local applied="$1"
    local phase=""
    local failures=0
    local recovery_gz="$RESTORE_TEMP_DIR/recovery.sql.gz"
    local recovery_sql="$RESTORE_TEMP_DIR/recovery.sql"

    if [ -z "$applied" ]; then
        return 0
    fi

    print_status $YELLOW "↩️  Rolling back to the pre-restore recovery point..."
    audit_log "restore_compensation_started" "warning" "Rolling back partial restore" "applied_phases=$applied"

    for phase in $applied; do
        case "$phase" in
            db)
                if [ -z "$RESTORE_POINT_DB" ]; then
                    print_status $RED "❌ No database recovery point available to roll back to"
                    failures=$((failures + 1))
                    continue
                fi
                print_status $YELLOW "   Restoring database from $RESTORE_POINT_DB..."
                if aws s3 cp "s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/${RESTORE_POINT_DB}.sql.gz" "$recovery_gz" --only-show-errors $S3_EXTRA_PARAMS \
                    && gunzip -c "$recovery_gz" > "$recovery_sql" 2>/dev/null \
                    && drush sql:drop -y \
                    && drush sql:cli < "$recovery_sql"; then
                    print_status $GREEN "   ✓ Database rolled back"
                else
                    print_status $RED "   ❌ Database rollback FAILED"
                    failures=$((failures + 1))
                fi
                rm -f "$recovery_gz" "$recovery_sql" 2>/dev/null
                ;;
            public)
                if [ -z "$RESTORE_POINT_PUBLIC" ]; then
                    print_status $RED "❌ No public files recovery point available to roll back to"
                    failures=$((failures + 1))
                    continue
                fi
                print_status $YELLOW "   Restoring public files from $RESTORE_POINT_PUBLIC..."
                if aws s3 sync "s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$RESTORE_POINT_PUBLIC/" "s3://$BUCKET_NAME/cms/public/" --only-show-errors --delete $S3_EXTRA_PARAMS; then
                    print_status $GREEN "   ✓ Public files rolled back"
                else
                    print_status $RED "   ❌ Public files rollback FAILED"
                    failures=$((failures + 1))
                fi
                ;;
            static)
                if [ -z "$RESTORE_POINT_STATIC" ]; then
                    print_status $RED "❌ No static recovery point available to roll back to"
                    failures=$((failures + 1))
                    continue
                fi
                print_status $YELLOW "   Restoring static site from $RESTORE_POINT_STATIC..."
                if aws s3 sync "s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$RESTORE_POINT_STATIC/" "s3://$BUCKET_NAME/web/" --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS; then
                    print_status $GREEN "   ✓ Static site rolled back"
                else
                    print_status $RED "   ❌ Static site rollback FAILED"
                    failures=$((failures + 1))
                fi
                ;;
        esac
    done

    if [ "$failures" -gt 0 ]; then
        audit_log "restore_compensation_failed" "error" "Rollback incomplete" "applied_phases=$applied failures=$failures"
        print_status $RED "❌ CRITICAL: rollback did not fully succeed. The environment is in a MIXED state."
        print_status $YELLOW "   Recovery point tags - static: ${RESTORE_POINT_STATIC:-none}  public: ${RESTORE_POINT_PUBLIC:-none}  db: ${RESTORE_POINT_DB:-none}"
        print_status $YELLOW "   Restore them explicitly with: $0 restore <recovery-point-tag>"
        return 1
    fi

    audit_log "restore_compensation_success" "success" "Partial restore rolled back" "applied_phases=$applied"
    print_status $GREEN "✓ Rolled back to the pre-restore state"
    return 0
}

restore_backup() {
    local backup_tag=""
    local restore_types=""
    local skip_state_management=false
    local skip_confirmation=false

    local no_recovery_point=false
    local force_destructive_sync=false
    local unknown_flags=""

    # Parse arguments
    if [ $# -eq 0 ]; then
        print_status $RED "❌ Error: Backup tag is required"
        print_status $YELLOW "⚠️ Usage: restore <backup_tag> [--only=static,public,db] [--skip-state-management|--ssm]"
        print_status $YELLOW "                             [--no-recovery-point] [--force-destructive-sync] [-y]"
        exit 1
    fi

    local positional_count=0
    local extra_positionals=""
    local skip_next=false

    for arg in "$@"; do
        if [ "$skip_next" = true ]; then
            skip_next=false
            continue
        fi
        case "$arg" in
            --skip-state-management|--ssm)
                skip_state_management=true
                ;;
            --skip-confirmation|--yes|--force|-y|--non-interactive)
                skip_confirmation=true
                ;;
            --no-recovery-point)
                no_recovery_point=true
                ;;
            --force-destructive-sync)
                force_destructive_sync=true
                ;;
            --only)
                # Value form: the next argument belongs to this option
                skip_next=true
                ;;
            --only=*)
                ;;
            -*)
                # An unrecognized flag must not be silently ignored or mistaken
                # for the backup tag on a destructive command.
                unknown_flags="${unknown_flags:+$unknown_flags }$arg"
                ;;
            *)
                positional_count=$((positional_count + 1))
                if [ "$positional_count" -gt 1 ]; then
                    extra_positionals="${extra_positionals:+$extra_positionals }$arg"
                fi
                ;;
        esac
    done

    if [ -n "$unknown_flags" ]; then
        print_status $RED "❌ Error: Unrecognized option(s): $unknown_flags"
        print_status $YELLOW "   Run '$0 restore --help' for supported options"
        exit 1
    fi

    # A trailing type name was silently ignored, so a documented command such as
    # "restore <tag> db" quietly restored every component instead of just the
    # database. Reject it rather than doing more than was asked.
    if [ -n "$extra_positionals" ]; then
        print_status $RED "❌ Error: Unexpected argument(s): $extra_positionals"
        print_status $YELLOW "   Select components with --only=type,type (e.g. --only=db)"
        exit 1
    fi

    # Parse options and get backup tag
    restore_types=$(parse_restore_options "$@" 2>&1 >/dev/null | tail -n1)
    backup_tag=$(parse_restore_options "$@" 2>/dev/null | head -n1)

    if [ -z "$backup_tag" ]; then
        print_status $RED "❌ Error: Backup tag is required"
        exit 1
    fi

    # Validate the tag before it reaches any S3 path or command construction
    if ! validate_backup_tag "$backup_tag"; then
        exit 1
    fi

    # Resolve the requested types to an exact set. Substring matching would let a
    # value such as "notstatic" select static, and an unknown value would restore
    # nothing while still reporting success.
    local normalized_types=""
    normalized_types=$(normalize_backup_types "$restore_types")
    if [ $? -ne 0 ]; then
        print_status $RED "❌ Error: Invalid --only value: $restore_types"
        exit 1
    fi

    setup_s3_vars || exit 1

    # Determine what to restore
    restore_static=no
    restore_public=no
    restore_database=no
    has_exact_backup_type "$normalized_types" "static" && restore_static=yes
    has_exact_backup_type "$normalized_types" "public" && restore_public=yes
    has_exact_backup_type "$normalized_types" "db" && restore_database=yes

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
            # Fail closed: silently skipping a requested component reports a
            # successful restore while leaving public files on their current
            # generation, mixed with restored static content and database.
            print_status $RED "❌ Public files backup not found for: $backup_tag"
            print_status $YELLOW "   Restore only the components that exist, e.g. --only=static,db"
            exit 1
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
            print_status $RED "❌ Database backup not found for: $backup_tag"
            print_status $YELLOW "   Restore only the components that exist, e.g. --only=static,public"
            exit 1
        fi
    fi

    # ---------------------------------------------------------------
    # Phase 1: non-mutating preflight of the S3 components
    # ---------------------------------------------------------------
    echo ""
    print_status $BLUE "🔍 Preflight checks..."

    if [ "$restore_static" = "yes" ]; then
        if ! restore_preflight_s3_component "static site" \
                "$AUTO_STATIC_BACKUP_PATH/$static_backup_tag" "web" "$force_destructive_sync"; then
            exit 1
        fi
    fi

    if [ "$restore_public" = "yes" ]; then
        if ! restore_preflight_s3_component "public files" \
                "$AUTO_PUBLIC_BACKUP_PATH/$public_backup_tag" "cms/public" "$force_destructive_sync"; then
            exit 1
        fi
    fi

    echo ""
    print_status $YELLOW "Restore plan:"

    if [ "$restore_static" = "yes" ]; then
        echo "Static site:   $static_backup_tag"
    fi
    if [ "$restore_public" = "yes" ]; then
        echo "Public files:  $public_backup_tag"
    fi
    if [ "$restore_database" = "yes" ]; then
        echo "Database:      $db_backup_tag"
    fi
    if [ "$no_recovery_point" = "true" ]; then
        print_status $RED "Recovery point: DISABLED (--no-recovery-point) - a failed restore cannot be rolled back"
    else
        echo "Recovery point: will be created with prefix $RESTORE_POINT_PREFIX"
    fi

    if [ "$skip_confirmation" = "true" ]; then
        print_status $YELLOW "⚠️  Restore confirmation skipped"
    else
        echo ""
        print_status $RED "This will overwrite current data!"
        printf "Continue with restore? (y/N): "
        read -r confirmation

        if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
            print_status $YELLOW "❌ Cancelled."
            exit 1
        fi
    fi

    echo ""
    audit_log "restore_started" "info" "Restore operation initiated" "backup_tag=$backup_tag static=$restore_static public=$restore_public database=$restore_database recovery_point=$([ "$no_recovery_point" = "true" ] && echo disabled || echo enabled)"

    # From here on, resources and Drupal state must be released on every exit
    # path, including a signal during a long sync.
    arm_cleanup_traps restore_cleanup

    RESTORE_TEMP_DIR=$(mktemp -d "${TMPDIR:-/tmp}/restore.XXXXXX") || {
        print_status $RED "❌ Error: Could not create a temporary working directory"
        return 1
    }
    chmod 700 "$RESTORE_TEMP_DIR"

    # ---------------------------------------------------------------
    # Phase 2: download and validate the database dump - still no mutation
    # This has to complete before the S3 trees are touched. Previously a failed
    # download, checksum, decompression, or validation happened only after the
    # static and public trees had already been replaced, leaving the environment
    # with content from two different generations.
    # ---------------------------------------------------------------
    if [ "$restore_database" = "yes" ]; then
        if ! restore_preflight_database "$db_backup_tag"; then
            return 1
        fi
    fi

    print_status $GREEN "✅ Preflight complete - all requested components verified"
    echo ""

    # ---------------------------------------------------------------
    # Phase 3: Drupal state
    # ---------------------------------------------------------------
    if [ "$skip_state_management" != "true" ]; then
        if prepare_drupal_state "both" 25; then
            RESTORE_STATE_PREPARED=true
        else
            print_status $RED "❌ Failed to prepare Drupal state for restore"
            return 1
        fi
    fi

    # ---------------------------------------------------------------
    # Phase 4: pre-restore recovery point
    # ---------------------------------------------------------------
    if [ "$no_recovery_point" = "true" ]; then
        audit_log "restore_recovery_point_skipped" "warning" "Pre-restore recovery point skipped by operator" "backup_tag=$backup_tag"
        print_status $RED "⚠️  Skipping the pre-restore recovery point (--no-recovery-point)"
    else
        if ! restore_create_recovery_point "$restore_static" "$restore_public" "$restore_database"; then
            print_status $RED "❌ Aborting: could not create a complete pre-restore recovery point"
            print_status $YELLOW "   Nothing has been modified. Fix the backup path or re-run with"
            print_status $YELLOW "   --no-recovery-point to accept an unrecoverable restore."
            return 1
        fi
    fi

    echo ""
    print_status $BLUE "🔄 Restoring..."

    # Restore static site
    if [ "$restore_static" = "yes" ] && [ -n "$static_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring static site..."
        # Mark the phase before mutating: a sync that fails partway through has
        # already changed live content and must be compensated.
        RESTORE_APPLIED_PHASES="static $RESTORE_APPLIED_PHASES"
        if aws s3 sync "s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/$static_backup_tag/" "s3://$BUCKET_NAME/web/" --only-show-errors --delete --acl public-read $S3_EXTRA_PARAMS; then
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
            restore_compensate "$RESTORE_APPLIED_PHASES"
            return 1
        fi
    fi

    # Restore public files
    if [ "$restore_public" = "yes" ] && [ -n "$public_backup_tag" ]; then
        print_status $YELLOW "🔄 Restoring public files..."
        RESTORE_APPLIED_PHASES="public $RESTORE_APPLIED_PHASES"
        if aws s3 sync "s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/$public_backup_tag/" "s3://$BUCKET_NAME/cms/public/" --only-show-errors --delete $S3_EXTRA_PARAMS; then
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
            restore_compensate "$RESTORE_APPLIED_PHASES"
            return 1
        fi
    fi

    # Restore database
    if [ "$restore_database" = "yes" ] && [ -n "$db_backup_tag" ]; then
        audit_log "restore_database_started" "info" "Database restore initiated" "backup_tag=$db_backup_tag"
        print_status $YELLOW "🔄 Restoring database..."

        # The dump was downloaded, checksum-verified, decompressed, and validated
        # during preflight, so the only remaining failure is the import itself.
        if ! command -v drush >/dev/null 2>&1; then
            print_status $RED "❌ ERROR: Drush not available for database restore"
            restore_compensate "$RESTORE_APPLIED_PHASES"
            return 1
        fi

        if [ -z "$RESTORE_DB_SQL_FILE" ] || [ ! -s "$RESTORE_DB_SQL_FILE" ]; then
            print_status $RED "❌ ERROR: Validated dump is missing - refusing to drop the database"
            restore_compensate "$RESTORE_APPLIED_PHASES"
            return 1
        fi

        # Mark the phase before dropping: from this point the database needs
        # compensation even if the drop itself fails partway through.
        RESTORE_APPLIED_PHASES="db $RESTORE_APPLIED_PHASES"

        if drush sql:drop -y && drush sql:cli < "$RESTORE_DB_SQL_FILE"; then
            audit_log "restore_database_success" "success" "Database restored successfully" "backup_tag=$db_backup_tag"
            print_status $GREEN "✅ Database restored"
        else
            audit_log "restore_database_failed" "error" "Database import failed" "backup_tag=$db_backup_tag"
            print_status $RED "❌ ERROR: Database import failed - the database may be empty or partial"
            restore_compensate "$RESTORE_APPLIED_PHASES"
            return 1
        fi
    fi

    # Release Drupal state before reporting success so the site is serving again
    # by the time the operator sees the summary. The trap remains as the fallback
    # for abnormal exits.
    if [ "$RESTORE_STATE_PREPARED" = "true" ]; then
        RESTORE_STATE_PREPARED=false
        if ! restore_drupal_state "both"; then
            print_status $RED "❌ CRITICAL: Restore succeeded but Drupal state was not restored"
            print_status $YELLOW "   Fix with: $0 state enable both"
            audit_log "restore_state_restore_failed" "error" "Drupal state not restored after successful restore" "backup_tag=$backup_tag"
            return 1
        fi
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