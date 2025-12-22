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
# DEPLOYMENT METADATA FUNCTIONS
# ===================================================================

# Parse backup tag to extract ticket and backup type
# Args:
#   $1: tag - Backup tag to parse
# Returns: ticket=VALUE and backup_type=VALUE on separate lines
parse_backup_tag_metadata() {
    local tag="$1"
    local ticket="none"
    local backup_type="manual"
    local prefix=""

    # Extract prefix (first segment before first hyphen)
    prefix=$(echo "$tag" | cut -d'-' -f1)

    # Determine backup type and ticket from prefix and suffix patterns
    case "$prefix" in
        AUTO)
            backup_type="auto"
            ticket="none"
            ;;
        HOTFIX)
            backup_type="hotfix"
            # Extract USAGOV ticket if present anywhere in tag
            ticket=$(echo "$tag" | grep -oE 'USAGOV-[0-9]+' | head -1)
            [ -z "$ticket" ] && ticket="none"
            ;;
        USAGOV*)
            # Prefix starts with USAGOV (e.g., USAGOV-1234-prod-...)
            ticket=$(echo "$prefix" | grep -oE 'USAGOV-[0-9]+')
            # Check suffix for deployment type
            if echo "$tag" | grep -q -- '-pre-deploy-'; then
                backup_type="pre-deploy"
            elif echo "$tag" | grep -q -- '-post-deploy-'; then
                backup_type="post-deploy"
            else
                backup_type="manual"
            fi
            ;;
        *)
            # Unknown prefix - try to extract ticket
            ticket=$(echo "$tag" | grep -oE 'USAGOV-[0-9]+' | head -1)
            [ -z "$ticket" ] && ticket="none"

            # Check for deployment suffixes
            if echo "$tag" | grep -q -- '-pre-deploy'; then
                backup_type="pre-deploy"
            elif echo "$tag" | grep -q -- '-post-deploy'; then
                backup_type="post-deploy"
            else
                backup_type="manual"
            fi
            ;;
    esac

    echo "ticket=$ticket"
    echo "backup_type=$backup_type"
}

# Capture current deployment state and create metadata JSON
# Args:
#   $1: backup_tag - Tag for this backup
#   $2: environment - Environment name (dev, stage, prod)
# Returns: JSON string with deployment metadata
capture_deployment_metadata() {
    local backup_tag="$1"
    local environment="$2"

    # Parse backup tag to get ticket and type
    local tag_info=$(parse_backup_tag_metadata "$backup_tag")
    local ticket=$(echo "$tag_info" | grep '^ticket=' | cut -d= -f2)
    local backup_type=$(echo "$tag_info" | grep '^backup_type=' | cut -d= -f2)

    # Get git info
    local git_commit=$(git rev-parse HEAD 2>/dev/null || echo "unknown")
    local git_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")

    # Get currently deployed containers from CF
    local cms_digest=$(get_app_digest "cms" || echo "unknown")
    local www_digest=$(get_app_digest "www" || echo "unknown")
    local waf_digest=$(get_app_digest "waf" || echo "unknown")

    # Try to extract CCI build number from digest
    # Format: registry/org/usagov_cms:BUILD@sha256:...
    local cci_build="unknown"
    if echo "$cms_digest" | grep -qE 'usagov_cms:[0-9]+@'; then
        cci_build=$(echo "$cms_digest" | sed 's/.*usagov_cms:\([0-9]*\)@.*/\1/')
    fi

    # Get username (circleci or actual user)
    local created_by=$(whoami 2>/dev/null || echo "unknown")
    if [ -n "$CIRCLECI" ]; then
        created_by="circleci"
    fi

    # Build JSON (simple approach without jq dependency)
    cat <<EOF
{
  "backup_tag": "$backup_tag",
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "environment": "$environment",
  "ticket": "$ticket",
  "backup_type": "$backup_type",
  "git_commit": "$git_commit",
  "git_branch": "$git_branch",
  "deployed_containers": {
    "cms": {
      "cci_build": "$cci_build",
      "digest": "$cms_digest"
    },
    "www": {
      "cci_build": "$cci_build",
      "digest": "$www_digest"
    },
    "waf": {
      "cci_build": "$cci_build",
      "digest": "$waf_digest"
    }
  },
  "created_by": "$created_by"
}
EOF
}

# Upload deployment metadata to S3
# Args:
#   $1: backup_tag - Tag for this backup
#   $2: metadata_json - JSON string to upload
# Returns: 0 on success, 1 on failure
upload_deployment_metadata() {
    local backup_tag="$1"
    local metadata_json="$2"

    setup_s3_vars || return 1

    # Store metadata in deployment-metadata path
    local metadata_path="deployment-metadata/${backup_tag}.json"
    local temp_file="/tmp/${backup_tag}-metadata.json"

    # Write JSON to temp file
    echo "$metadata_json" > "$temp_file"

    # Upload to S3
    if aws s3 cp "$temp_file" "s3://${BUCKET_NAME}/${metadata_path}" $S3_EXTRA_PARAMS 2>/dev/null; then
        rm -f "$temp_file"
        return 0
    else
        rm -f "$temp_file"
        return 1
    fi
}

# Fetch deployment metadata from S3
# Args:
#   $1: backup_tag - Tag to fetch metadata for
# Returns: JSON string or empty if not found
fetch_deployment_metadata() {
    local backup_tag="$1"

    setup_s3_vars || return 1

    local metadata_path="deployment-metadata/${backup_tag}.json"

    # Download from S3 to stdout
    aws s3 cp "s3://${BUCKET_NAME}/${metadata_path}" - $S3_EXTRA_PARAMS 2>/dev/null
}

# Get container digest for a specific app from Cloud Foundry
# Args:
#   $1: app_name - Name of app (cms, waf, www)
# Returns: Full docker image digest or empty string on error
get_app_digest() {
    local app_name="$1"
    cf app "$app_name" 2>/dev/null | grep 'docker image' | awk '{print $NF}'
}

# Get all app digests (cms, waf, www) from Cloud Foundry
# Returns: Three lines: cms_digest, waf_digest, www_digest
get_all_app_digests() {
    local cms_digest=$(get_app_digest "cms")
    local waf_digest=$(get_app_digest "waf")
    local www_digest=$(get_app_digest "www")
    echo "$cms_digest"
    echo "$waf_digest"
    echo "$www_digest"
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

# Get the next available numeric suffix for a backup tag on the same day
# Checks existing backups and returns the next number (0, 1, 2, etc.)
# Args:
#   $1: backup_type - Type of backup (static, public, db)
#   $2: base_tag - Base tag without suffix (e.g., AUTO-dev-14845-2025-12-01)
# Returns: Next available number as string
get_next_backup_suffix() {
    local backup_type="$1"
    local base_tag="$2"

    setup_s3_vars || return 1

    local s3_path=""
    local search_pattern=""

    case "$backup_type" in
        "static")
            s3_path="$AUTO_STATIC_BACKUP_PATH"
            search_pattern="${base_tag}-"
            ;;
        "public")
            s3_path="$AUTO_PUBLIC_BACKUP_PATH"
            search_pattern="${base_tag}-"
            ;;
        "db")
            s3_path="$AUTO_DB_BACKUP_PATH"
            search_pattern="${base_tag}-"
            ;;
        *)
            echo "0"
            return 0
            ;;
    esac

    # List existing backups matching the pattern
    local existing_numbers=""
    if [ "$backup_type" = "db" ]; then
        # For database, search for .sql.gz files
        existing_numbers=$(aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS 2>/dev/null | \
            grep "${search_pattern}" | \
            grep ".sql.gz" | \
            awk '{print $4}' | \
            sed "s/^${base_tag}-//" | \
            sed 's/.sql.gz$//' | \
            grep '^[0-9]\+$')
    else
        # For static/public, search for directories
        existing_numbers=$(aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS 2>/dev/null | \
            grep "PRE ${search_pattern}" | \
            awk '{print $2}' | \
            tr -d '/' | \
            sed "s/^${base_tag}-//" | \
            grep '^[0-9]\+$')
    fi

    # Find the highest number and add 1
    local max_num=-1
    if [ -n "$existing_numbers" ]; then
        for num in $existing_numbers; do
            if [ "$num" -gt "$max_num" ]; then
                max_num=$num
            fi
        done
    fi

    echo $((max_num + 1))
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

# Extract date from backup name using standardized pattern
# Args:
#   $1: backup_name - Backup tag or filename
# Returns: Date in YYYY-MM-DD format or empty string if not found
extract_date_from_backup_name() {
    local backup_name="$1"
    echo "$backup_name" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}' | head -1
}

# Display filter message based on filter type and value
# Consolidates duplicate message logic used in cleanup functions
# Args:
#   $1: filter_type - Type of filter (days, in-range, except-range, older-date, newer-date, all)
#   $2: filter_value - Filter value (days number, date range, or date)
#   $3: backup_types - Types of backups being cleaned (for display)
show_filter_message() {
    local filter_type="$1"
    local filter_value="$2"
    local backup_types="${3:-backups}"

    case "$filter_type" in
        "days")
            local cutoff_epoch=$(get_days_ago_epoch "$filter_value")
            local cutoff_display=$(date -u -d "@$cutoff_epoch" '+%Y-%m-%d' 2>/dev/null || date -u -r "$cutoff_epoch" '+%Y-%m-%d' 2>/dev/null)
            echo "🧹 Cleaning up $backup_types older than $filter_value days (before $cutoff_display)..."
            ;;
        "in-range")
            local start_date=$(echo "$filter_value" | cut -d: -f1)
            local end_date=$(echo "$filter_value" | cut -d: -f2)
            if [ -n "$start_date" ] && [ -n "$end_date" ]; then
                echo "🧹 Cleaning up $backup_types from ${start_date} to ${end_date}..."
            elif [ -n "$start_date" ]; then
                echo "🧹 Cleaning up $backup_types from ${start_date} onward..."
            elif [ -n "$end_date" ]; then
                echo "🧹 Cleaning up $backup_types up to ${end_date}..."
            fi
            ;;
        "except-range")
            local start_date=$(echo "$filter_value" | cut -d: -f1)
            local end_date=$(echo "$filter_value" | cut -d: -f2)
            if [ -n "$start_date" ] && [ -n "$end_date" ]; then
                echo "🧹 Cleaning up $backup_types EXCEPT those from ${start_date} to ${end_date}..."
            elif [ -n "$start_date" ]; then
                echo "🧹 Cleaning up $backup_types before ${start_date}..."
            elif [ -n "$end_date" ]; then
                echo "🧹 Cleaning up $backup_types after ${end_date}..."
            fi
            ;;
        "older-date")
            echo "🧹 Cleaning up $backup_types older than ${filter_value}..."
            ;;
        "newer-date")
            echo "🧹 Cleaning up $backup_types newer than ${filter_value}..."
            ;;
        "all")
            echo "🧹 Removing ALL $backup_types..."
            ;;
    esac
}

# Check if a backup date matches the specified filter criteria
# Supports: in-range, except-range, older-date, newer-date
# Args:
#   $1: backup_date - Date to check (YYYY-MM-DD)
#   $2: filter_type - Type of filter (in-range, except-range, older-date, newer-date, days)
#   $3: filter_value - Filter value (date range or date)
# Returns: 0 if matches filter, 1 if doesn't match
matches_clean_filter() {
    local backup_date="$1"
    local filter_type="$2"
    local filter_value="$3"

    [ -z "$backup_date" ] && return 1

    local backup_epoch=$(date_to_epoch "$backup_date")
    [ -z "$backup_epoch" ] && return 1

    case "$filter_type" in
        "in-range")
            # Delete if within range
            local start_date=$(echo "$filter_value" | cut -d: -f1)
            local end_date=$(echo "$filter_value" | cut -d: -f2)
            is_date_in_range "$backup_date" "$start_date" "$end_date"
            return $?
            ;;
        "except-range")
            # Delete if OUTSIDE range (inverse of in-range)
            local start_date=$(echo "$filter_value" | cut -d: -f1)
            local end_date=$(echo "$filter_value" | cut -d: -f2)
            if is_date_in_range "$backup_date" "$start_date" "$end_date"; then
                return 1  # Inside range, don't delete
            else
                return 0  # Outside range, delete
            fi
            ;;
        "older-date")
            # Delete if before this date
            local cutoff_epoch=$(date_to_epoch "$filter_value")
            [ -z "$cutoff_epoch" ] && return 1
            [ "$backup_epoch" -lt "$cutoff_epoch" ] && return 0 || return 1
            ;;
        "newer-date")
            # Delete if after this date
            local cutoff_epoch=$(date_to_epoch "$filter_value")
            [ -z "$cutoff_epoch" ] && return 1
            [ "$backup_epoch" -gt "$cutoff_epoch" ] && return 0 || return 1
            ;;
        "days")
            # Delete if older than N days (retention)
            local cutoff_epoch=$(get_days_ago_epoch "$filter_value")
            [ "$backup_epoch" -lt "$cutoff_epoch" ] && return 0 || return 1
            ;;
        "all")
            # Delete everything
            return 0
            ;;
        *)
            return 1
            ;;
    esac
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
    if ! echo "$first_lines" | grep -q "MySQL dump" && \
       ! echo "$first_lines" | grep -q "MariaDB dump"; then
        echo "❌ SQL dump missing MySQL/MariaDB header"
        return 1
    fi

    # Check for common SQL dump patterns in the beginning
    # Use multiple simple greps BusyBox compatibility
    if ! echo "$first_lines" | grep -q -e "-- Host" && \
       ! echo "$first_lines" | grep -q -e "-- Server" && \
       ! echo "$first_lines" | grep -q -e "-- Database" && \
       ! echo "$first_lines" | grep -q -e "-- Dump completed on" && \
       ! echo "$first_lines" | grep -q -e "-- Table structure"; then
        echo "⚠️  SQL dump header format unusual (missing standard comments)"
    fi

    # Read last few lines to check for proper completion
    local last_lines=$(tail -n 10 "$sql_file")

    # Check that dump was completed
    if ! echo "$last_lines" | grep -q "Dump completed on" && \
       ! echo "$last_lines" | grep -q -e "-- Dump completed"; then
        echo "⚠️  SQL dump may be incomplete (missing completion marker)"
    fi

    local has_create=false
    local has_insert=false

    # Check first 100 lines for CREATE statements
    if head -n 100 "$sql_file" | grep -q "CREATE TABLE\|CREATE DATABASE"; then
        has_create=true
    fi

    # Check for INSERT statements
    if grep -q "INSERT INTO" "$sql_file"; then
        has_insert=true
    fi

    if [ "$has_create" = "false" ] && [ "$has_insert" = "false" ]; then
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
    fi

    # Always set APP_SPACE if not already set (even if BUCKET_NAME was already exported)
    if [ -z "$APP_SPACE" ] && [ -n "$VCAP_APPLICATION" ]; then
        APP_SPACE=$(echo "$VCAP_APPLICATION" | jq -r '.space_name' 2>/dev/null)
        APP_SPACE=${APP_SPACE:-local}
        export APP_SPACE
    fi

    # endpoint and ssl specifications only necessary on local for minio support
    if [ -z "$S3_EXTRA_PARAMS" ]; then
        S3_EXTRA_PARAMS=""
        if [ "${APP_SPACE}" = "local" ]; then
            S3_EXTRA_PARAMS="--endpoint-url https://$AWS_ENDPOINT --no-verify-ssl"
        fi
        export S3_EXTRA_PARAMS
    fi

    # Validate that we have what we need
    if [ -z "$BUCKET_NAME" ]; then
        print_status $RED "❌ Error: Could not determine S3 bucket name. Make sure VCAP_SERVICES is set."
        return 1
    fi
}