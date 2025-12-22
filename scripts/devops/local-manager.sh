#!/bin/sh

# ===================================================================
# LOCAL BACKUP MANAGER
# ===================================================================
# Control Cloud Foundry backup operations from your local machine
# All commands execute remotely via 'cf ssh cms -c' command
# Requires: CF CLI installed and authenticated
# ===================================================================

COMMAND="${1:-}"

# ===================================================================
# USAGE DOCUMENTATION
# ===================================================================

show_usage() {
    echo "⚠️  WARNING: For local machine use only!"
    echo ""
    echo "Local Backup Manager - Control Cloud Foundry backups from local machine"
    echo ""
    echo "Usage: local-manager.sh <command> [options]"
    echo ""
    echo "Commands:"
    echo "  list [types] [days]                      List backups on CF (default: all types, 30 days)"
    echo "  backup [types] [prefix] [suffix]         Create backups on CF (default: all types, AUTO prefix)"
    echo "  clean [types] [days|all|0] [-y]          Remove old backups on CF (default: all types, 30 days)"
    echo "                                           Use -y or --non-interactive to skip confirmation"
    echo "  delete <tag> [tag2 tag3...] [types] [-y] Delete specific backup(s) by tag name (default: all types)"
    echo "  restore <tag> [--only=type,type]         Restore backups on CF (interactive)"
    echo "  info [types] [tag]                     Show backup info from CF (config or specific backup)"
    echo "  download <tag> [type] [output-dir]     Download backups to local (default: all types, current dir)"
    echo "  test                                   Run backup system test suite on CF"
    echo "  cron <subcommand>                      Manage automated database backup cron jobs"
    echo "  try-tome-disable [max_wait_mins]       Disable Drupal/Tome for backup (wait for tome, disable, enable maintenance)"
    echo "  try-tome-enable                        Re-enable Drupal/Tome (disable maintenance, re-enable tome)"
    echo ""
    echo "Cron Subcommands:"
    echo "  setup                    Configure automated database backups (uses DB_BACKUP_TIME env var)"
    echo "  remove                   Remove all cron jobs"
    echo "  status                   Show current cron jobs (default)"
    echo "  test                     Test the cron backup command"
    echo ""
    echo "Backup Types:"
    echo "  all                      All backup types (default)"
    echo "  static                   Static site backups"
    echo "  public                   Public file backups"
    echo "  db                       Database backups"
    echo "  static,public            Multiple types (comma-separated)"
    echo ""
    echo "Backup Tag Format:"
    echo "  PREFIX-SPACE-CONTAINERTAG-YYYY-MM-DD[-SUFFIX]"
    echo "  Example: AUTO-prod-14850-2025-10-28"
    echo ""
    echo "Examples:"
    echo "  local-manager.sh list                                                  # List all backups (last 30 days)"
    echo "  local-manager.sh list db 7                                             # List db backups (last 7 days)"
    echo "  local-manager.sh backup all USAGOV-123 pre-deploy                      # Create backup with custom tags"
    echo "  local-manager.sh clean db 30                                           # Clean db backups older than 30 days"
    echo "  local-manager.sh delete AUTO-dev-123-2025-01-15                        # Delete specific backup (all types)"
    echo "  local-manager.sh delete AUTO-dev-123-2025-01-15 static,db -y          # Delete static+db (no confirm)"
    echo "  local-manager.sh delete TAG1 TAG2 TAG3 all -y                          # Delete multiple backups at once"
    echo "  local-manager.sh info                                                  # Show backup system configuration"
    echo "  local-manager.sh info db AUTO-prod-14850-2025-10-28                    # Show specific backup details"
    echo "  local-manager.sh download AUTO-prod-14850-2025-10-28 all ./backups/    # Download all"
    echo "  local-manager.sh download AUTO-prod-14850-2025-10-28 db                # Download db only"
    echo "  local-manager.sh restore AUTO-prod-14850-2025-10-28                    # Restore all (interactive)"
    echo "  local-manager.sh restore AUTO-prod-14850-2025-10-28 --only=db          # Restore db only"
    echo "  local-manager.sh test                                               # Run test suite on CF"
    echo "  local-manager.sh cron status                                        # Show current cron jobs"
    echo "  local-manager.sh cron setup                                         # Setup automated db backups"
    echo "  local-manager.sh cron test                                          # Test cron backup command"
    echo "  local-manager.sh cron remove                                        # Remove all cron jobs"
    echo ""
    echo "Note: Requires Cloud Foundry CLI (cf) installed and logged in"
    echo "      Login with: bin/cloudgov/login --sso"
}

# ===================================================================
# PREREQUISITE VALIDATION
# ===================================================================

# Verify this script is not running on a Cloud Foundry container
# Exit with error if CF environment variables are detected
check_not_on_cf() {
    # Check for CF environment variables that indicate we're running on CF
    if [ -n "$VCAP_APPLICATION" ] || [ -n "$CF_INSTANCE_INDEX" ]; then
        echo "❌ ERROR: This script is for LOCAL USE ONLY!"
        echo ""
        echo "You are currently on a Cloud Foundry container."
        echo "This script uses 'cf ssh' to connect to CF remotely."
        echo ""
        echo "To manage backups on this CF container, use:"
        echo "  scripts/snapshot/manager.sh <command>"
        echo ""
        exit 1
    fi
}

# Verify Cloud Foundry CLI is installed and authenticated
# Checks: cf command availability and current authentication status
check_cf_cli() {
    if ! command -v cf >/dev/null 2>&1; then
        echo "❌ Error: Cloud Foundry CLI (cf) not found"
        echo "   Install from: https://docs.cloudfoundry.org/cf-cli/install-go-cli.html"
        exit 1
    fi

    if ! cf target >/dev/null 2>&1; then
        echo "❌ Error: Not logged in to Cloud Foundry"
        echo "   Run: bin/cloudgov/login --sso"
        exit 1
    fi
}

# ===================================================================
# REMOTE COMMAND EXECUTION
# ===================================================================

# Execute a command on Cloud Foundry via SSH
# Sources /etc/profile for environment, changes to /var/www, runs manager.sh
# Args: All arguments are passed to manager.sh on CF
remote_command() {
    cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh $*"
}

# Download backups from CF to local machine with streaming support
# Special handling: streams data through SSH to avoid using CF disk space
# Args:
#   $1: tag - Backup tag to download
#   $2: types - Backup types (default: "all")
#   $3: output_dir - Local directory for downloads (default: current directory)
download_command() {
    local backup_tag=$1
    local backup_type=${2:-all}
    local output_dir=${3:-$(pwd)}

    if [ -z "$backup_tag" ]; then
        echo "❌ Error: backup tag required"
        echo "Usage: local-manager.sh download <backup-tag> [type] [output-dir]"
        return 1
    fi

    # Create output directory
    mkdir -p "$output_dir"

    echo "📦 Downloading backup: $backup_tag"
    echo "📂 Output directory: $output_dir"
    echo "🎯 Type: $backup_type"
    echo ""

    # Track success/failure
    local failed=0

    # Parse backup types (handle comma-separated list)
    local types_to_download="$backup_type"
    if [ "$backup_type" = "all" ]; then
        types_to_download="db,static,public"
    fi

    # Download each requested type
    IFS=',' read -ra TYPES <<< "$types_to_download"
    for type in "${TYPES[@]}"; do
        # Trim whitespace
        type=$(echo "$type" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

        case "$type" in
            db)
                echo "📥 Downloading database backup..."
                if cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $backup_tag db - --stream" > "$output_dir/${backup_tag}-database.sql.gz" 2>/dev/null; then
                    size=$(du -h "$output_dir/${backup_tag}-database.sql.gz" | awk '{print $1}')
                    echo "   ✅ Database backup saved: ${backup_tag}-database.sql.gz ($size)"
                else
                    echo "   ❌ Database backup failed or not found"
                    rm -f "$output_dir/${backup_tag}-database.sql.gz"
                    failed=$((failed + 1))
                fi
                echo ""
                ;;
            static)
                echo "📥 Downloading static backup..."
                if cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $backup_tag static - --stream" > "$output_dir/${backup_tag}-static.tar.gz" 2>/dev/null; then
                    size=$(du -h "$output_dir/${backup_tag}-static.tar.gz" | awk '{print $1}')
                    echo "   ✅ Static backup saved: ${backup_tag}-static.tar.gz ($size)"
                else
                    echo "   ❌ Static backup failed or not found"
                    rm -f "$output_dir/${backup_tag}-static.tar.gz"
                    failed=$((failed + 1))
                fi
                echo ""
                ;;
            public)
                echo "📥 Downloading public backup..."
                if cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $backup_tag public - --stream" > "$output_dir/${backup_tag}-public.tar.gz" 2>/dev/null; then
                    size=$(du -h "$output_dir/${backup_tag}-public.tar.gz" | awk '{print $1}')
                    echo "   ✅ Public backup saved: ${backup_tag}-public.tar.gz ($size)"
                else
                    echo "   ❌ Public backup failed or not found"
                    rm -f "$output_dir/${backup_tag}-public.tar.gz"
                    failed=$((failed + 1))
                fi
                echo ""
                ;;
            *)
                echo "❌ Invalid backup type: $type"
                echo "   Valid types: db, static, public, all"
                failed=$((failed + 1))
                ;;
        esac
    done

    # Summary
    if [ $failed -eq 0 ]; then
        echo "✅ Download complete!"
        echo ""
        echo "Downloaded files:"
        ls -lh "$output_dir"/${backup_tag}-* 2>/dev/null
        return 0
    else
        echo "⚠️  Download completed with errors ($failed failed)"
        echo ""
        echo "Downloaded files:"
        ls -lh "$output_dir"/${backup_tag}-* 2>/dev/null
        return 1
    fi
}

# ===================================================================
# MAIN SCRIPT LOGIC
# ===================================================================

# Handle empty command or help flags
if [ -z "$COMMAND" ]; then
    show_usage
    exit 1
fi

# Handle help flags immediately (before CF checks)
if [ "$COMMAND" = "-h" ] || [ "$COMMAND" = "--help" ] || [ "$COMMAND" = "help" ]; then
    show_usage
    exit 0
fi

# Validate environment and prerequisites
check_not_on_cf
check_cf_cli

# ===================================================================
# COMMAND ROUTING
# ===================================================================

case "$COMMAND" in
    "list")
        # list [types] [days]
        remote_command list "$2" "$3"
        ;;
    "backup")
        # backup [types] [prefix] [suffix]
        remote_command backup "$2" "$3" "$4"
        ;;
    "clean")
        # clean [types] [days]
        remote_command clean "$2" "$3"
        ;;
    "delete")
        # delete <tag> [tag2 tag3...] [types] [-y]
        shift
        remote_command delete "$@"
        ;;
    "restore")
        # restore <tag> [options]
        shift
        remote_command restore "$@"
        ;;
    "info")
        # info [types] <tag>
        remote_command info "$2" "$3"
        ;;
    "download")
        # download <tag> [type] [output-dir] - special handling for local streaming
        shift
        download_command "$@"
        ;;
    "test")
        # test - run test suite on CF
        echo "🧪 Running backup system test suite on Cloud Foundry..."
        echo ""
        cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/test.sh"
        ;;
    "cron")
        # cron <subcommand> - manage cron jobs on CF
        CRON_SUBCOMMAND=${2:-status}
        case $CRON_SUBCOMMAND in
            setup|remove|status|test)
                echo "⚙️  Managing cron jobs on Cloud Foundry..."
                echo ""
                cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/setup-cron.sh $CRON_SUBCOMMAND"
                ;;
            *)
                echo "❌ Unknown cron subcommand: $CRON_SUBCOMMAND"
                echo ""
                echo "Valid subcommands: setup, remove, status, test"
                exit 1
                ;;
        esac
        ;;
    "try-tome-disable")
        # try-tome-disable [max_wait_minutes] - Disable Drupal/Tome for backup
        remote_command try-tome-disable "$2"
        ;;
    "try-tome-enable")
        # try-tome-enable - Re-enable Drupal/Tome to normal operation
        remote_command try-tome-enable
        ;;
    *)
        echo "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac
