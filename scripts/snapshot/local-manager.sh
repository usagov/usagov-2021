#!/bin/sh

# Local Manager Script
# Control the backup manager on Cloud Foundry from your local machine
# All commands are executed via 'cf ssh cms -c' - requires CF CLI and login

COMMAND="${1:-}"

show_usage() {
    echo "WARNING: For local machine use only!"
    echo ""
    echo "Local Backup Manager - Control Cloud Foundry backups from local machine"
    echo ""
    echo "Usage: local-manager.sh <command> [options]"
    echo ""
    echo "Commands:"
    echo "  list [types] [days]                    List backups on CF (default: all types, 30 days)"
    echo "  backup [types] [prefix] [suffix]       Create backups on CF (default: all types, AUTO prefix)"
    echo "  clean [types] [days|all|0]             Remove old backups on CF (default: all types, 30 days)"
    echo "  restore <tag> [--only=type,type]       Restore backups on CF (interactive)"
    echo "  info [types] [tag]                     Show backup info from CF (config or specific backup)"
    echo "  download <tag> [type] [output-dir]     Download backups to local (default: all types, current dir)"
    echo ""
    echo "Backup Types:"
    echo "  all                      All backup types (default)"
    echo "  static                   Static site backups"
    echo "  public                   Public file backups"
    echo "  db                       Database backups"
    echo "  static,public            Multiple types (comma-separated)"
    echo ""
    echo "Backup Tag Format:"
    echo "  PREFIX-SPACE-CONTAINERTAG-MMM-DD-YY[-SUFFIX]"
    echo "  Example: AUTO-prod-14850-Oct-28-25"
    echo ""
    echo "Examples:"
    echo "  local-manager.sh list                                               # List all backups (last 30 days)"
    echo "  local-manager.sh list db 7                                          # List db backups (last 7 days)"
    echo "  local-manager.sh backup all USAGOV-123 pre-deploy                   # Create backup with custom tags"
    echo "  local-manager.sh clean db 30                                        # Clean db backups older than 30 days"
    echo "  local-manager.sh info                                               # Show backup system configuration"
    echo "  local-manager.sh info db AUTO-prod-14850-Oct-28-25                  # Show specific backup details"
    echo "  local-manager.sh download AUTO-prod-14850-Oct-28-25 all ./backups/  # Download all"
    echo "  local-manager.sh download AUTO-prod-14850-Oct-28-25 db              # Download db only"
    echo "  local-manager.sh restore AUTO-prod-14850-Oct-28-25                  # Restore all (interactive)"
    echo "  local-manager.sh restore AUTO-prod-14850-Oct-28-25 --only=db        # Restore db only"
    echo ""
    echo "Note: Requires Cloud Foundry CLI (cf) installed and logged in"
    echo "      Login with: bin/cloudgov/login --sso"
}

# Check prerequisites

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

# Execute remote command on CF
remote_command() {
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh $*"
}

# Download command with local streaming
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
                if cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh download $backup_tag db - --stream" > "$output_dir/${backup_tag}-database.sql.gz" 2>/dev/null; then
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
                if cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh download $backup_tag static - --stream" > "$output_dir/${backup_tag}-static.tar.gz" 2>/dev/null; then
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
                if cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh download $backup_tag public - --stream" > "$output_dir/${backup_tag}-public.tar.gz" 2>/dev/null; then
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

# Main logic
if [ -z "$COMMAND" ]; then
    show_usage
    exit 1
fi

# Check that we're not on CF before proceeding
check_not_on_cf

# Check CF CLI for all commands
check_cf_cli

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
    *)
        echo "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac
