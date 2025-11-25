#!/bin/sh

# Common utilities for backup system
# Shared functions to eliminate redundancy across backup scripts

# Colors for consistent output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Initialize backup system paths and configuration
# Sets: PROJECT_ROOT, BACKUP_DIR, CONFIG_FILE
# Loads: backup-system.conf
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

# Consistent status printing function
print_status() {
    local color=$1
    local message=$2
    printf "${color}${message}${NC}\n"
}

# Consistent log message function
log_message() {
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $1"
}

# Get container tag (consistent implementation)
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

# Check if file exists and is readable
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

# Check if command is available
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

# Calculate epoch timestamp for N days ago (works on Alpine BusyBox, GNU, and BSD date)
get_days_ago_epoch() {
    local days=$1
    local now_epoch=$(date -u '+%s')
    local seconds_per_day=86400
    local cutoff_epoch=$((now_epoch - (days * seconds_per_day)))
    echo "$cutoff_epoch"
}

# Setup S3 environment variables from VCAP_SERVICES
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