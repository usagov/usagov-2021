#!/bin/sh

# ===================================================================
# COMMON UTILITIES FOR BACKUP SYSTEM
# ===================================================================
# Shared functions to eliminate redundancy across backup scripts
# Provides: initialization, logging, status printing, date handling, S3 setup
# ===================================================================

# ===================================================================
# COLOR DEFINITIONS
# ===================================================================
# ANSI color codes for consistent terminal output across all scripts
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'  # No Color (reset)

# ===================================================================
# SYSTEM INITIALIZATION
# ===================================================================

# Initialize backup system paths and configuration
# Automatically detects project root from various starting directories
# Sets: PROJECT_ROOT, BACKUP_DIR, CONFIG_FILE
# Loads: backup-system.conf
# Exit on error: Yes (if project root cannot be found)
init_backup_system() {
    # When sourced, BASH_SOURCE points to this file, $0 points to the calling script
    # We need to find scripts/snapshot directory from current working directory

    # First, try to find from current directory
    if [ -d "scripts/snapshot" ]; then
        # Running from project root
        PROJECT_ROOT="$(pwd)"
        BACKUP_DIR="$PROJECT_ROOT/scripts/snapshot"
    elif [ -d "snapshot" ] && [ -f "snapshot/common.sh" ]; then
        # Running from scripts directory
        PROJECT_ROOT="$(cd .. && pwd)"
        BACKUP_DIR="$PROJECT_ROOT/scripts/snapshot"
    elif [ "$(basename "$(pwd)")" = "snapshot" ] && [ -f "common.sh" ]; then
        # Running from scripts/snapshot directory
        PROJECT_ROOT="$(cd ../.. && pwd)"
        BACKUP_DIR="$(pwd)"
    else
        # Try to find the project root by walking up the directory tree
        current_dir="$(pwd)"
        while [ "$current_dir" != "/" ]; do
            if [ -d "$current_dir/scripts/snapshot" ]; then
                PROJECT_ROOT="$current_dir"
                BACKUP_DIR="$current_dir/scripts/snapshot"
                break
            fi
            current_dir=$(dirname "$current_dir")
        done

        if [ -z "$PROJECT_ROOT" ]; then
            echo "❌ ERROR: Cannot find scripts/snapshot directory. Please run from project root or scripts/snapshot directory."
            echo "   Current directory: $(pwd)"
            echo "   Available directories: $(ls -la)"
            exit 1
        fi
    fi

    # Load configuration
    CONFIG_FILE="$BACKUP_DIR/backup-system.conf"
    if [ -f "$CONFIG_FILE" ]; then
        . "$CONFIG_FILE"
    else
        echo "❌ ERROR: Configuration file not found: $CONFIG_FILE"
        exit 1
    fi

    # Add vendor/bin to PATH for drush and other tools
    if [ -d "$PROJECT_ROOT/vendor/bin" ]; then
        export PATH="$PROJECT_ROOT/vendor/bin:$PATH"
    fi

    # Export for use by other scripts
    export PROJECT_ROOT BACKUP_DIR CONFIG_FILE
}

# ===================================================================
# OUTPUT AND LOGGING FUNCTIONS
# ===================================================================

# Print colored status messages to terminal
# Args:
#   $1: color - Color code (e.g., $GREEN, $RED, $YELLOW, $BLUE)
#   $2: message - Text to display
print_status() {
    local color=$1
    local message=$2
    printf "${color}${message}${NC}\n"
}

# Log message with timestamp for audit trail
# Outputs: YYYY-MM-DD HH:MM:SS: <message>
# Args:
#   $1: message - Text to log
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $1"
}

# ===================================================================
# CONTAINER AND VERSION IDENTIFICATION
# ===================================================================

# Get container tag for backup identification and traceability
# Tries multiple sources in order of preference:
#   1. Cloud Foundry /etc/motd containertag field
#   2. Cloud Foundry /etc/motd cf-XXXXXX pattern
#   3. Git repository short hash
#   4. Fallback to "unknown"
# Returns: container tag string (e.g., "14850", "cf-a1b2c3", "git-d4e5f6")
get_container_tag() {
    # Try to get container tag from Cloud Foundry environment
    if [ -f /etc/motd ]; then
        # First try the containertag: format used in Cloud Foundry
        container_tag=$(grep -i 'containertag' /etc/motd 2>/dev/null | sed 's/containertag\:[[:space:]]*//' | sed 's/^[[:space:]]*//' | head -1)
        if [ -n "$container_tag" ]; then
            echo "$container_tag"
            return 0
        fi

        # Fallback to cf-XXXXXX pattern
        container_tag=$(grep -oE 'cf-[a-f0-9]{6}' /etc/motd | head -1 2>/dev/null)
        if [ -n "$container_tag" ]; then
            echo "$container_tag"
            return 0
        fi
    fi

    # Fallback to git hash if in a git repository
    if command -v git >/dev/null 2>&1 && git rev-parse --git-dir >/dev/null 2>&1; then
        git_hash=$(git rev-parse --short HEAD 2>/dev/null)
        if [ -n "$git_hash" ]; then
            echo "git-$git_hash"
            return 0
        fi
    fi

    # Final fallback
    echo "unknown"
}

# ===================================================================
# VALIDATION FUNCTIONS
# ===================================================================

# Check if file exists and is readable
# Args:
#   $1: file_path - Absolute or relative path to file
#   $2: description - Human-readable description (default: "file")
# Returns: 0 if file is accessible, 1 otherwise
check_file() {
    local file_path="$1"
    local description="${2:-file}"

    if [ -f "$file_path" ] && [ -r "$file_path" ]; then
        echo "✅ Found $description: $file_path"
        return 0
    else
        echo "❌ Missing or unreadable $description: $file_path"
        return 1
    fi
}

# Check if command is available in PATH
# Args:
#   $1: cmd - Command name to check
# Returns: 0 if command is found, 1 otherwise
check_command() {
    local cmd="$1"

    if command -v "$cmd" >/dev/null 2>&1; then
        echo "✅ Command available: $cmd"
        return 0
    else
        echo "❌ Command not found: $cmd"
        return 1
    fi
}

# ===================================================================
# DATE AND TIME UTILITIES
# ===================================================================

# Calculate epoch timestamp for N days ago
# Portable implementation that works on Alpine BusyBox, GNU, and BSD date
# Uses simple arithmetic instead of date command's relative date parsing
# Args:
#   $1: days - Number of days in the past
# Returns: Epoch timestamp (seconds since 1970-01-01 00:00:00 UTC)
get_days_ago_epoch() {
    local days=$1
    local now_epoch=$(date -u '+%s')
    local seconds_per_day=86400
    local cutoff_epoch=$((now_epoch - (days * seconds_per_day)))
    echo "$cutoff_epoch"
}

# Convert a YYYY-MM-DD date string to epoch timestamp
# Portable implementation that works on GNU and BSD date
# Args:
#   $1: date - Date string in YYYY-MM-DD format
# Returns: Epoch timestamp or empty string if invalid
date_to_epoch() {
    local date_str="$1"

    # Try GNU date format first
    local epoch=$(date -u -d "$date_str" '+%s' 2>/dev/null)

    # If that fails, try BSD date format
    if [ -z "$epoch" ]; then
        epoch=$(date -u -j -f '%Y-%m-%d' "$date_str" '+%s' 2>/dev/null)
    fi

    echo "$epoch"
}

# Check if a backup date falls within a date range
# Args:
#   $1: backup_date - Date from backup name (YYYY-MM-DD)
#   $2: start_date - Start of range (YYYY-MM-DD) or empty for no start limit
#   $3: end_date - End of range (YYYY-MM-DD) or empty for no end limit
# Returns: 0 if in range, 1 if not in range
is_date_in_range() {
    local backup_date="$1"
    local start_date="$2"
    local end_date="$3"

    # Convert backup date to epoch
    local backup_epoch=$(date_to_epoch "$backup_date")
    if [ -z "$backup_epoch" ]; then
        return 1  # Invalid date
    fi

    # Check start date constraint
    if [ -n "$start_date" ]; then
        local start_epoch=$(date_to_epoch "$start_date")
        if [ -n "$start_epoch" ] && [ "$backup_epoch" -lt "$start_epoch" ]; then
            return 1  # Before start date
        fi
    fi

    # Check end date constraint
    if [ -n "$end_date" ]; then
        local end_epoch=$(date_to_epoch "$end_date")
        if [ -n "$end_epoch" ] && [ "$backup_epoch" -gt "$end_epoch" ]; then
            return 1  # After end date
        fi
    fi

    return 0  # In range
}

# Format file size to human-readable format with appropriate units
# Automatically selects best unit (KB, MB, or GB) based on size
# Args:
#   $1: bytes - File size in bytes
# Returns: Formatted string like "12345678 bytes (11.77 MB)"
format_file_size() {
    local bytes="$1"

    if [ -z "$bytes" ] || [ "$bytes" = "0" ]; then
        echo "0 bytes"
        return
    fi

    # Use bc for floating point math if available, otherwise use awk
    if command -v bc >/dev/null 2>&1; then
        local kb=$(echo "scale=2; $bytes / 1024" | bc)
        local mb=$(echo "scale=2; $bytes / 1048576" | bc)
        local gb=$(echo "scale=2; $bytes / 1073741824" | bc)

        if [ $(echo "$gb >= 1" | bc) -eq 1 ]; then
            echo "$bytes bytes (${gb} GB)"
        elif [ $(echo "$mb >= 1" | bc) -eq 1 ]; then
            echo "$bytes bytes (${mb} MB)"
        elif [ $(echo "$kb >= 1" | bc) -eq 1 ]; then
            echo "$bytes bytes (${kb} KB)"
        else
            echo "$bytes bytes"
        fi
    else
        # Fallback to awk if bc is not available
        echo "$bytes" | awk '{
            if ($1 >= 1073741824) {
                printf "%d bytes (%.2f GB)", $1, $1/1073741824
            } else if ($1 >= 1048576) {
                printf "%d bytes (%.2f MB)", $1, $1/1048576
            } else if ($1 >= 1024) {
                printf "%d bytes (%.2f KB)", $1, $1/1024
            } else {
                printf "%d bytes", $1
            }
        }'
    fi
}

# Format AWS S3 summary output with human-readable sizes
# Extracts and formats Total Objects and Total Size from aws s3 ls --summarize output
# Args:
#   $1: output - Raw output from aws s3 ls --summarize command
# Returns: Formatted summary with human-readable file sizes
format_s3_summary() {
    local output="$1"

    # Extract Total Objects line
    local objects_line=$(echo "$output" | grep "Total Objects:")
    echo "$objects_line"

    # Extract and format Total Size line
    local size_line=$(echo "$output" | grep "Total Size:")
    if [ -n "$size_line" ]; then
        local bytes=$(echo "$size_line" | awk '{print $3}')
        local formatted=$(format_file_size "$bytes")
        echo "Total Size: $formatted"
    fi
}

# ===================================================================
# DRUPAL STATE MANAGEMENT
# ===================================================================

# Validate SQL dump file structure
# Checks that the file looks like a valid MySQL/MariaDB dump
# Args:
#   $1: path to SQL file
# Returns: 0 if valid, 1 if invalid
validate_sql_dump() {
    local sql_file="$1"

    if [ ! -f "$sql_file" ] || [ ! -s "$sql_file" ]; then
        echo "❌ SQL file does not exist or is empty"
        return 1
    fi

    # Read first few lines to check for SQL dump header
    local first_lines=$(head -n 20 "$sql_file")

    # Check for MySQL/MariaDB dump markers
    if ! echo "$first_lines" | grep -q "MySQL dump\|MariaDB dump"; then
        echo "❌ SQL dump missing MySQL/MariaDB header"
        return 1
    fi

    # Check for common SQL dump patterns in the beginning
    if ! echo "$first_lines" | grep -qE "-- (Host|Server|Database|Dump completed on|Table structure)"; then
        echo "⚠️  SQL dump header format unusual (missing standard comments)"
    fi

    # Read last few lines to check for proper completion
    local last_lines=$(tail -n 10 "$sql_file")

    # Check that dump was completed (should have "Dump completed on" or similar)
    if ! echo "$last_lines" | grep -qE "Dump completed on|-- Dump completed"; then
        echo "⚠️  SQL dump may be incomplete (missing completion marker)"
    fi

    # Check for common end-of-dump patterns
    if ! echo "$first_lines" | grep -qE "CREATE TABLE|INSERT INTO|CREATE DATABASE" && \
       ! echo "$last_lines" | grep -qE "CREATE TABLE|INSERT INTO"; then
        echo "❌ SQL dump contains no CREATE or INSERT statements"
        return 1
    fi

    echo "✅ SQL dump structure validated"
    return 0
}

# Check if tome-run.sh is currently running
# Returns: 0 if running, 1 if not running
is_tome_running() {
    local script_name="tome-run.sh"
    local ps_aux=$(ps aux)
    local running_count=$(echo "$ps_aux" | grep "$script_name" | grep -v grep | wc -l)

    if [ "$running_count" -gt "0" ]; then
        return 0  # Running
    else
        return 1  # Not running
    fi
}

# Wait for tome to stop, disable it, then enable maintenance mode
# This prepares Drupal for backup/restore operations
# Args:
#   $1: max_wait_minutes (optional, default: 25) - Maximum time to wait for tome to stop
# Returns: 0 on success, 1 on failure or timeout
prepare_drupal_for_backup() {
    local max_wait_minutes=${1:-30}

    # Validate max_wait_minutes
    if ! echo "$max_wait_minutes" | grep -qE '^[0-9]+$' || [ "$max_wait_minutes" -gt 30 ]; then
        print_status $RED "Error: max_wait_minutes must be an integer less than or equal to 30"
        return 1
    fi

    local start_seconds=$(date +'%s')
    local sleep_time=15

    print_status $YELLOW "⏳ Waiting for Tome to stop (max: ${max_wait_minutes} minutes)..."

    # Wait for tome to stop
    while true; do
        local current_seconds=$(date +'%s')
        local diff_seconds=$((current_seconds - start_seconds))
        local diff_minutes=$((diff_seconds / 60))

        if [ $diff_minutes -gt $max_wait_minutes ]; then
            print_status $RED "❌ Timeout: Tome still running after ${max_wait_minutes} minutes"
            return 1
        fi

        if ! is_tome_running; then
            print_status $GREEN "✅ Tome has stopped"
            break
        fi

        echo "   Tome is still running... waiting ${sleep_time}s (elapsed: ${diff_minutes}m / ${max_wait_minutes}m)"
        sleep $sleep_time
    done

    # Disable tome
    print_status $YELLOW "🔒 Disabling Tome..."
    if ! drush sset usagov.tome_run_disabled 1 2>/dev/null; then
        print_status $RED "❌ Failed to disable Tome"
        return 1
    fi

    local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
    print_status $GREEN "✅ Tome disabled: $tome_disabled"

    # Enable maintenance mode
    print_status $YELLOW "🚧 Enabling maintenance mode..."
    if drush sset system.maintenance_mode 1 2>/dev/null && drush cr 2>/dev/null; then
        local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
        print_status $GREEN "✅ Maintenance mode enabled: $maint_mode"
    else
        print_status $RED "❌ Failed to enable maintenance mode"
        # Try to re-enable tome before returning
        drush sdel usagov.tome_run_disabled 2>/dev/null
        return 1
    fi

    return 0
}

# Restore Drupal to normal operation after backup/restore
# Disables maintenance mode first, then re-enables tome
# Returns: 0 on success, 1 on failure
restore_drupal_state() {
    # Disable maintenance mode first
    print_status $YELLOW "🚧 Disabling maintenance mode..."
    if drush sset system.maintenance_mode 0 2>/dev/null && drush cr 2>/dev/null; then
        local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
        print_status $GREEN "✅ Maintenance mode disabled: $maint_mode"
    else
        print_status $RED "❌ Failed to disable maintenance mode"
        # Continue anyway
    fi

    # Re-enable tome
    print_status $YELLOW "🔓 Re-enabling Tome..."
    if ! drush sdel usagov.tome_run_disabled 2>/dev/null; then
        print_status $RED "❌ Failed to re-enable Tome"
        return 1
    fi

    local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
    print_status $GREEN "✅ Tome re-enabled (disabled flag: ${tome_disabled:-none})"

    return 0
}

# ===================================================================
# AWS S3 CONFIGURATION
# ===================================================================

# Setup S3 environment variables from VCAP_SERVICES (Cloud Foundry)
# Extracts S3 credentials and configuration from Cloud Foundry environment
# Sets: BUCKET_NAME, AWS_DEFAULT_REGION, AWS_ACCESS_KEY_ID,
#       AWS_SECRET_ACCESS_KEY, AWS_ENDPOINT, APP_SPACE, S3_EXTRA_PARAMS
# Returns: 0 on success, 1 if bucket name cannot be determined
setup_s3_vars() {
    if [ -z "$BUCKET_NAME" ] && [ -n "$VCAP_SERVICES" ]; then
        export BUCKET_NAME=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket' 2>/dev/null)
        export AWS_DEFAULT_REGION=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region' 2>/dev/null)
        export AWS_ACCESS_KEY_ID=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id' 2>/dev/null)
        export AWS_SECRET_ACCESS_KEY=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key' 2>/dev/null)
        export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.hostname' 2>/dev/null)

        # Fallback to endpoint if hostname is null
        if [ -z "$AWS_ENDPOINT" ] || [ "$AWS_ENDPOINT" = "null" ]; then
            export AWS_ENDPOINT=$(echo "${VCAP_SERVICES}" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.endpoint' 2>/dev/null)
        fi

        # Get the Cloud.gov space we are hosted in
        APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name' 2>/dev/null)
        APP_SPACE=${APP_SPACE:-local}

        # endpoint and ssl specifications only necessary on local for minio support
        S3_EXTRA_PARAMS=""
        if [ "${APP_SPACE}" = "local" ]; then
            S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
        fi

        # Export for use by other functions
        export APP_SPACE S3_EXTRA_PARAMS
    fi

    # Validate that we have what we need
    if [ -z "$BUCKET_NAME" ]; then
        print_status $RED "❌ Error: Could not determine S3 bucket name. Make sure VCAP_SERVICES is set."
        return 1
    fi
}