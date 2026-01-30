#!/bin/sh

# Set restrictive permissions for all created files
umask 077

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
    echo "                [--unzip]                        Automatically unzip downloaded files"
    echo "                [--unzip=filename]               Unzip and save as specified filename"
    echo "  test                                   Run backup system test suite on CF"
    echo "  cron <subcommand>                      Manage automated database backup cron jobs"
    echo "  state <action> <type> [max_wait_mins]  Manage Drupal state (action: enable|disable, type: tome|sm|both)"
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
    echo "  local-manager.sh download AUTO-prod-14850 db . --unzip                 # Download and unzip"
    echo "  local-manager.sh download AUTO-prod-14850 db . --unzip=usagov.sql      # Download, unzip, and rename"
    echo "  local-manager.sh restore AUTO-prod-14850-2025-10-28                    # Restore all (interactive)"
    echo "  local-manager.sh restore AUTO-prod-14850-2025-10-28 --only=db          # Restore db only"
    echo "  local-manager.sh test                                               # Run test suite on CF"
    echo "  local-manager.sh cron status                                        # Show current cron jobs"
    echo "  local-manager.sh cron setup all                                     # Setup automated backups (all types)"
    echo "  local-manager.sh cron setup db                                      # Setup automated db backups only"
    echo "  local-manager.sh cron test                                          # Test cron backup command"
    echo "  local-manager.sh cron remove                                        # Remove all cron jobs"
    echo ""
    echo "Note: Requires Cloud Foundry CLI (cf) installed and logged in"
    echo "      Login with: bin/cloudgov/login --sso"
}

# Show command-specific help
show_command_help() {
    local command="$1"

    case "$command" in
        "list")
            echo "List Backups"
            echo ""
            echo "Usage: local-manager.sh list [types] [days]"
            echo ""
            echo "Description:"
            echo "  List available backups in Cloud Foundry by type and time range."
            echo ""
            echo "Arguments:"
            echo "  types  - Backup types: all, static, public, db, or comma-separated (default: all)"
            echo "  days   - Number of days to show (default: 30)"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh list"
            echo "  local-manager.sh list db 7"
            echo ""
            ;;
        "backup")
            echo "Create Backup"
            echo ""
            echo "Usage: local-manager.sh backup [types] [prefix] [suffix]"
            echo ""
            echo "Description:"
            echo "  Create new backups in Cloud Foundry of specified types."
            echo ""
            echo "Arguments:"
            echo "  types   - Backup types: all, static, public, db, or comma-separated (default: all)"
            echo "  prefix  - Backup prefix (default: AUTO)"
            echo "  suffix  - Optional backup suffix"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh backup"
            echo "  local-manager.sh backup db"
            echo "  local-manager.sh backup all USAGOV-123 post-deploy"
            echo ""
            ;;
        "clean")
            echo "Clean Old Backups"
            echo ""
            echo "Usage: local-manager.sh clean [types] [days|all|0] [-y]"
            echo ""
            echo "Description:"
            echo "  Remove backups based on retention policy."
            echo ""
            echo "Arguments:"
            echo "  types  - Backup types: all, static, public, db (default: all)"
            echo "  days   - Keep last N days (default: 30), or 'all'/'0' to delete everything"
            echo "  -y     - Non-interactive mode (no confirmation)"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh clean all 30"
            echo "  local-manager.sh clean db 7 -y"
            echo ""
            ;;
        "delete")
            echo "Delete Specific Backups"
            echo ""
            echo "Usage: local-manager.sh delete <tag> [tag2 tag3...] [types] [-y]"
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
            echo "  local-manager.sh delete AUTO-prod-14850-2025-10-28"
            echo "  local-manager.sh delete TAG1 TAG2 TAG3 all -y"
            echo ""
            ;;
        "restore")
            echo "Restore Backup"
            echo ""
            echo "Usage: local-manager.sh restore <tag> [--only=type,type]"
            echo ""
            echo "Description:"
            echo "  Restore backups from specified tag."
            echo ""
            echo "Arguments:"
            echo "  tag  - Backup tag to restore"
            echo ""
            echo "Options:"
            echo "  --only=type,type  - Restore only specific types"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh restore AUTO-prod-14850-2025-10-28"
            echo "  local-manager.sh restore AUTO-prod-14850 --only=db"
            echo ""
            ;;
        "info")
            echo "Show Backup Information"
            echo ""
            echo "Usage: local-manager.sh info [types] [tag]"
            echo ""
            echo "Description:"
            echo "  Show backup system information or details about specific backup."
            echo ""
            echo "Arguments:"
            echo "  types  - Show info for specific types: all, static, public, db"
            echo "  tag    - Show details for specific backup tag"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh info"
            echo "  local-manager.sh info db AUTO-prod-14850-2025-10-28"
            echo ""
            ;;
        "download")
            echo "Download Backup"
            echo ""
            echo "Usage: local-manager.sh download <tag> [type] [output-dir] [--unzip] [--unzip=name]"
            echo ""
            echo "Description:"
            echo "  Download backups from Cloud Foundry to local filesystem."
            echo ""
            echo "Arguments:"
            echo "  tag         - Backup tag to download"
            echo "  type        - Type to download: all, static, public, db (default: all)"
            echo "  output-dir  - Output directory (default: current directory)"
            echo ""
            echo "Options:"
            echo "  --unzip              - Automatically extract downloaded archives"
            echo "                         • .sql.gz files → .sql file"
            echo "                         • .tar.gz files → directory/"
            echo "  --unzip=name         - Extract and save as specified name"
            echo "                         • For .sql.gz: filename (e.g., usagov.sql)"
            echo "                         • For .tar.gz: directory name"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh download AUTO-prod-14850-2025-10-28"
            echo "  local-manager.sh download AUTO-prod-14850 db ./backups/"
            echo "  local-manager.sh download AUTO-prod-14850 db ./backups/ --unzip"
            echo "  local-manager.sh download AUTO-prod-14850 db . --unzip=usagov.sql"
            echo "  local-manager.sh download AUTO-prod-14850 static . --unzip=my-static-files"
            echo ""
            ;;
        "test")
            echo "Run Test Suite"
            echo ""
            echo "Usage: local-manager.sh test"
            echo ""
            echo "Description:"
            echo "  Run the backup system test suite on Cloud Foundry."
            echo ""
            ;;
        "cron")
            echo "Manage Cron Jobs"
            echo ""
            echo "Usage: local-manager.sh cron <subcommand> [types]"
            echo ""
            echo "Description:"
            echo "  Manage automated backup cron jobs."
            echo ""
            echo "Subcommands:"
            echo "  setup [types]  - Configure automated backups"
            echo "                   types: all, db, static, public, or comma-separated (default: all)"
            echo "  remove         - Remove all cron jobs"
            echo "  status         - Show current cron jobs (default)"
            echo "  test [types]   - Test the cron backup command with optional types override"
            echo ""
            echo "Examples:"
            echo "  local-manager.sh cron status"
            echo "  local-manager.sh cron setup all"
            echo "  local-manager.sh cron setup db"
            echo "  local-manager.sh cron setup static,db"
            echo "  local-manager.sh cron test all"
            echo ""
            ;;
        "state")
            echo "Manage Drupal State"
            echo ""
            echo "Usage: local-manager.sh state <action> <type> [max_wait_mins]"
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
            echo "  local-manager.sh state disable tome 30"
            echo "  local-manager.sh state enable tome"
            echo "  local-manager.sh state disable sm"
            echo "  local-manager.sh state enable both"
            echo ""
            ;;
        *)
            echo "No help available for command: $command"
            echo ""
            echo "Run 'local-manager.sh' for list of all commands"
            exit 1
            ;;
    esac
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
    local backup_tag=""
    local backup_type="all"
    local output_dir=$(pwd)
    local unzip_flag=false
    local unzip_filename=""

    # Parse all arguments, separating flags from positional args
    local positional_args=()
    while [ $# -gt 0 ]; do
        case "$1" in
            --unzip)
                unzip_flag=true
                shift
                ;;
            --unzip=*)
                unzip_flag=true
                unzip_filename="${1#*=}"
                shift
                ;;
            *)
                positional_args+=("$1")
                shift
                ;;
        esac
    done

    # Assign positional arguments
    backup_tag="${positional_args[0]}"
    [ ${#positional_args[@]} -gt 1 ] && backup_type="${positional_args[1]}"
    [ ${#positional_args[@]} -gt 2 ] && output_dir="${positional_args[2]}"

    if [ -z "$backup_tag" ]; then
        echo "❌ Error: backup tag required"
        echo "Usage: local-manager.sh download <backup-tag> [type] [output-dir]"
        return 1
    fi

    # Load common utilities for validation
    SCRIPT_DIR=$(dirname "$0")
    if [ -f "$SCRIPT_DIR/../common.sh" ]; then
        . "$SCRIPT_DIR/../common.sh"
    fi

    # Validate backup tag to prevent command injection
    if command -v validate_backup_tag >/dev/null 2>&1; then
        if ! validate_backup_tag "$backup_tag"; then
            return 1
        fi
    fi

    # Validate and normalize output path
    if command -v validate_output_path >/dev/null 2>&1; then
        local validated_path
        validated_path=$(validate_output_path "$output_dir")
        if [ $? -ne 0 ]; then
            echo "❌ Invalid output path"
            return 1
        fi
        output_dir="$validated_path"
    fi

    # Create output directory with restrictive permissions
    mkdir -p "$output_dir"
    chmod 700 "$output_dir"

    echo "📦 Downloading backup: $backup_tag"
    echo "📂 Output directory: $output_dir"
    echo "🎯 Type: $backup_type"
    echo ""

    # Track success/failure and downloaded files
    local failed=0
    local downloaded_files=""

    # Parse backup types (handle comma-separated list)
    local types_to_download="$backup_type"
    if [ "$backup_type" = "all" ]; then
        types_to_download="db,static,public"
    fi

    # Helper function to download a backup with progress tracking
    download_backup_with_progress() {
        local type="$1"
        local label="$2"
        local filename="$3"
        local extension="$4"

        echo "📥 Downloading $label backup..."
        local output_file="$output_dir/${backup_tag}-${filename}.${extension}"

        # Start download in background and show progress
        local cmd
        cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download %q %q - --stream' "$backup_tag" "$type")
        cf ssh cms -c "$cmd" > "$output_file" 2>/dev/null &
        local download_pid=$!

        # Show progress while downloading
        while kill -0 $download_pid 2>/dev/null; do
            if [ -f "$output_file" ]; then
                local current_size=$(du -h "$output_file" 2>/dev/null | awk '{print $1}')
                printf "\r   Downloading... %s" "$current_size"
            fi
            sleep 2
        done
        wait $download_pid
        local exit_code=$?
        printf "\r   Downloading... "

        if [ $exit_code -eq 0 ] && [ -s "$output_file" ]; then
            local size=$(du -h "$output_file" | awk '{print $1}')
            echo "✅ $label backup saved: ${backup_tag}-${filename}.${extension} ($size)"

            # Unzip if requested
            if [ "$unzip_flag" = true ]; then
                echo "📦 Extracting $label backup..."

                # Determine extraction method based on file type
                if [[ "$output_file" == *.tar.gz ]]; then
                    # Extract tar.gz to directory
                    local extract_dir
                    if [ -n "$unzip_filename" ]; then
                        extract_dir="$output_dir/$unzip_filename"
                    else
                        # Remove .tar.gz extension for default directory name
                        extract_dir="${output_file%.tar.gz}"
                    fi

                    # Check if target directory exists
                    if [ -d "$extract_dir" ]; then
                        echo "⚠️  Directory already exists: $(basename "$extract_dir")"
                        printf "Overwrite? (y/N): "
                        read -r response
                        if [ "$response" != "y" ] && [ "$response" != "Y" ]; then
                            echo "❌ Extraction cancelled"
                            rm -f "$output_file"
                            return 1
                        fi
                        rm -rf "$extract_dir"
                    fi

                    # Create directory and extract
                    mkdir -p "$extract_dir"
                    if tar -xzf "$output_file" -C "$extract_dir" 2>/dev/null; then
                        local extracted_size=$(du -sh "$extract_dir" | awk '{print $1}')
                        echo "✅ Extracted to: $(basename "$extract_dir")/ ($extracted_size)"
                        # Track the archive transformation
                        downloaded_files="${downloaded_files}$(basename "$output_file") -> $(basename "$extract_dir")/\n"
                        rm -f "$output_file"
                    else
                        echo "❌ Failed to extract $label backup"
                        rm -rf "$extract_dir"
                        return 1
                    fi

                else
                    # Gunzip .sql.gz or other .gz files
                    local unzipped_file
                    if [ -n "$unzip_filename" ]; then
                        unzipped_file="$output_dir/$unzip_filename"
                    else
                        # Remove .gz extension
                        unzipped_file="${output_file%.gz}"
                    fi

                    # Check if target file exists
                    if [ -f "$unzipped_file" ]; then
                        echo "⚠️  File already exists: $(basename "$unzipped_file")"
                        printf "Overwrite? (y/N): "
                        read -r response
                        if [ "$response" != "y" ] && [ "$response" != "Y" ]; then
                            echo "❌ Extraction cancelled"
                            rm -f "$output_file"
                            return 1
                        fi
                    fi

                    if gunzip -c "$output_file" > "$unzipped_file" 2>/dev/null; then
                        local unzipped_size=$(du -h "$unzipped_file" | awk '{print $1}')
                        echo "✅ Extracted to: $(basename "$unzipped_file") ($unzipped_size)"
                        # Track the file transformation
                        downloaded_files="${downloaded_files}$(basename "$output_file") -> $(basename "$unzipped_file")\n"
                        rm -f "$output_file"
                    else
                        echo "❌ Failed to extract $label backup"
                        return 1
                    fi
                fi
            else
                # Track the downloaded .gz file
                downloaded_files="${downloaded_files}$(basename "$output_file")\n"
            fi
        else
            echo "❌ $label backup failed or not found"
            rm -f "$output_file"
            return 1
        fi
        echo ""
        return 0
    }

    # Download each requested type
    IFS=',' read -ra TYPES <<< "$types_to_download"
    for type in "${TYPES[@]}"; do
        # Trim whitespace
        type=$(echo "$type" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

        case "$type" in
            db)
                download_backup_with_progress "db" "database" "database" "sql.gz" || failed=$((failed + 1))
                ;;
            static)
                download_backup_with_progress "static" "static" "static" "tar.gz" || failed=$((failed + 1))
                ;;
            public)
                download_backup_with_progress "public" "public" "public" "tar.gz" || failed=$((failed + 1))
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
        if [ -n "$downloaded_files" ]; then
            echo "Downloaded files:"
            printf "$downloaded_files"
        fi
        return 0
    else
        echo "⚠️  Download completed with errors ($failed failed)"
        echo ""
        if [ -n "$downloaded_files" ]; then
            echo "Downloaded files:"
            printf "$downloaded_files"
        fi
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

# Check if second arg is -h/--help for command-specific help
if [ "$2" = "-h" ] || [ "$2" = "--help" ]; then
    show_command_help "$COMMAND"
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
        # cron <subcommand> [types] - manage cron jobs on CF
        CRON_SUBCOMMAND=${2:-status}
        CRON_TYPES=${3:-}
        case $CRON_SUBCOMMAND in
            setup|remove|status|test)
                echo "⚙️  Managing cron jobs on Cloud Foundry..."
                echo ""
                # Use printf %q for safe shell escaping
                local cmd
                if [ -n "$CRON_TYPES" ]; then
                    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/setup-cron.sh %q %q' "$CRON_SUBCOMMAND" "$CRON_TYPES")
                else
                    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/setup-cron.sh %q' "$CRON_SUBCOMMAND")
                fi
                cf ssh cms -c "$cmd"
                ;;
            *)
                echo "❌ Unknown cron subcommand: $CRON_SUBCOMMAND"
                echo ""
                echo "Valid subcommands: setup, remove, status, test"
                exit 1
                ;;
        esac
        ;;
    "state")
        # state <action> <type> [max_wait_mins] - Manage Drupal state
        remote_command state "$2" "$3" "$4"
        ;;
    *)
        echo "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac
