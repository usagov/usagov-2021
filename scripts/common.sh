#!/bin/sh

# ===================================================================
# COMMON UTILITIES FOR BACKUP/DEPLOYMENT SYSTEM
# ===================================================================
# Shared functions to eliminate redundancy across backup scripts
# Provides: initialization, logging, status printing, date handling, S3 setup
# ===================================================================

# Set restrictive permissions for all created files
umask 077

# ===================================================================
# COLOR DEFINITIONS
# ===================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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
            echo "❌ Fatal Error: Cannot find scripts/snapshot directory. Please run from project root or scripts/snapshot directory."
            echo "   Current directory: $(pwd)"
            exit 1
        fi
    fi

    # Load configuration
    CONFIG_FILE="$BACKUP_DIR/backup-system.conf"
    if [ -f "$CONFIG_FILE" ]; then
        . "$CONFIG_FILE"
    else
        echo "❌ Fatal Error: Configuration file not found: $CONFIG_FILE"
        exit 1
    fi

    # Set defaults for constants if not defined in config
    : "${RETENTION_MIN_HOURS:=48}"
    : "${RATE_LIMIT_SECONDS:=60}"
    : "${MAX_RETRY_ATTEMPTS:=100}"
    : "${FLOCK_TIMEOUT_SECONDS:=15}"
    : "${TAG_MAX_LENGTH:=200}"
    : "${MAX_WAIT_TOME_MINUTES:=30}"

    # Make constants readonly
    readonly RETENTION_MIN_HOURS
    readonly RATE_LIMIT_SECONDS
    readonly MAX_RETRY_ATTEMPTS
    readonly FLOCK_TIMEOUT_SECONDS
    readonly TAG_MAX_LENGTH
    readonly MAX_WAIT_TOME_MINUTES

    # Calculate derived constant
    readonly RETENTION_MIN_SECONDS=$((RETENTION_MIN_HOURS * 3600))

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

# Show loading message
show_loading() {
    local message="$1"
    printf "  %s...\n" "$message" >&2
}

# Log message with timestamp for audit trail
# Outputs: YYYY-MM-DD HH:MM:SS: <message>
# Args:
#   $1: message - Text to log
log_message() {
    local msg="$1"
    # Redact AWS access keys (AKIA followed by 16 alphanumeric)
    msg=$(echo "$msg" | sed 's/AKIA[A-Z0-9]\{16\}/AKIA***REDACTED***/g')
    # Redact AWS secret keys (base64-like strings 40+ chars)
    msg=$(echo "$msg" | sed 's/\([A-Za-z0-9+/]\{40,\}\)/***REDACTED***/g')
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $msg"
}

# Log structured audit message for security events
# Outputs in [tags@47450 ...] format for logshipper parsing
# Creates JSON fields in New Relic for easy querying/visualization
# Args:
#   $1: event_type - Type of event (backup_create, backup_restore, tome_disable, etc.)
#   $2: status - Event status (success, failure, started)
#   $3: message - Human-readable message
#   $4: additional_data - Optional additional key="value" pairs (space-separated)
audit_log() {
    local event_type="$1"
    local status="$2"
    local msg="$3"
    local additional="${4:-}"

    # Get current context
    local user=$(whoami 2>/dev/null || echo "unknown")
    local pid=$$
    local timestamp=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
    local space="${APP_SPACE:-unknown}"

    # Redact sensitive data from message
    msg=$(echo "$msg" | sed 's/AKIA[A-Z0-9]\{16\}/AKIA***REDACTED***/g')
    msg=$(echo "$msg" | sed 's/\([A-Za-z0-9+/]\{40,\}\)/***REDACTED***/g')

    # Build structured log in tags format for logshipper
    # Format: [tags@47450 key="value" key2="value2" ...]
    # Note: Space-separated, not comma-separated
    local structured="[tags@47450 "
    structured="${structured}event_type=\"${event_type}\" "
    structured="${structured}status=\"${status}\" "
    structured="${structured}message=\"${msg}\" "
    structured="${structured}user=\"${user}\" "
    structured="${structured}pid=\"${pid}\" "
    structured="${structured}timestamp=\"${timestamp}\" "
    structured="${structured}space=\"${space}\""

    # Add additional data if provided (should already be space-separated)
    if [ -n "$additional" ]; then
        structured="${structured} ${additional}"
    fi

    structured="${structured}]"

    # Output timestamp + structured data
    echo "$(date '+%Y-%m-%d %H:%M:%S'): $structured"
}

# ===================================================================
# CONFIRMATION PROMPTS
# ===================================================================

# Universal confirmation function for destructive operations
# Supports both yes/no and exact text matching with retry logic
# Args:
#   $1: prompt - The warning/question text to display
#   $2: mode - "yn" for yes/no (default), "exact" for exact text matching
#   $3: required_text - (optional) For "exact" mode, the text user must type
#   $4: max_attempts - (optional) Max attempts for exact mode (default: 3)
#   $5: skip_flag - (optional) If equals "--skip-confirmation", bypass prompt
#
# Returns:
#   0 - Confirmed (user proceeded)
#   1 - Cancelled (user declined or max attempts exceeded)
#
# Examples:
#   confirm_action "⚠️  This will deploy to production" || return 1
#   confirm_action "⚠️  This will deploy to production" "yn" "" "" "$skip_flag" || return 1
#   confirm_action "⚠️  PRODUCTION ROLLBACK" "exact" "CONFIRM PROD ROLLBACK" 3 || exit 1
#   confirm_action "⚠️  Type DELETE to confirm" "exact" "DELETE" || return 1
#
# NIST 800-53: AC-3 - Access Enforcement
# NIST 800-53: IA-2 - User Identification and Authentication
confirm_action() {
    local prompt="$1"
    local mode="${2:-yn}"
    local required_text="$3"
    local max_attempts="${4:-3}"
    local skip_flag="$5"

    # Check for --skip-confirmation flag
    if [ "$skip_flag" = "--skip-confirmation" ]; then
        print_status $YELLOW "⚠️  --skip-confirmation flag detected, bypassing confirmation"
        echo ""
        return 0
    fi

    # Display the prompt
    print_status $YELLOW "$prompt"
    echo ""

    # Handle based on mode
    case "$mode" in
        yn)
            # Simple yes/no confirmation
            read -p "Type 'yes' to proceed: " confirm
            if [ "$confirm" = "yes" ]; then
                echo ""
                return 0
            else
                handle_error "" "cancelled" "return"
            fi
            ;;

        exact)
            # Exact text matching with retry logic
            if [ -z "$required_text" ]; then
                handle_error "required_text not provided for exact mode" "validation" "return"
            fi

            local attempts=0
            local user_input=""

            while [ $attempts -lt $max_attempts ]; do
                attempts=$((attempts + 1))

                if [ $attempts -gt 1 ]; then
                    print_status $RED "❌ Incorrect. Attempt $attempts of $max_attempts"
                    echo ""
                fi

                read -p "Type '$required_text' to confirm: " user_input

                if [ "$user_input" = "$required_text" ]; then
                    echo ""
                    return 0
                fi
            done

            # Max attempts exceeded
            handle_error "Maximum attempts exceeded" "cancelled" "return"
            ;;

        *)
            handle_error "Invalid mode '$mode'. Use 'yn' or 'exact'" "validation" "return"
            ;;
    esac
}

# ===================================================================
# ERROR HANDLING
# ===================================================================
#
# Error Handling Conventions:
# --------------------------
# This system uses standardized error handling with distinct exit codes
# to enable better error diagnostics and automation.
#
# Exit Codes:
#   0 - Success (or user cancellation, not an error)
#   1 - Generic/fatal error
#   2 - Validation error (invalid input, missing parameters)
#   3 - System error (infrastructure/service failures)
#   4 - User error (permissions, authentication)
#
# Exit vs Return:
#   - Utility functions (common.sh): Use return to pass control back
#   - Validation functions: Use return with appropriate exit code
#   - Main command functions (deploy.sh): Use exit to terminate
#   - Library functions: Use return, let caller decide on exit
#
# Error Message Format:
#   - Errors:  print_status $RED "❌ Error: <message>"
#            OR handle_error "<message>" "validation|system|user" "return|exit"
#   - Warnings: print_status $YELLOW "⚠️ Warning: <message>"
#   - Tips:     print_status $YELLOW "💡 Tip: <message>"
#   - Success:  print_status $GREEN "✅ <message>"
#   - Info:     print_status $BLUE "🔍 <message>"
#
# Usage Examples:
#   # Validation error in utility function
#   validate_tag() {
#       [ -z "$1" ] && handle_error "Tag cannot be empty" "validation" "return"
#       return 0
#   }
#
#   # System error that should exit
#   deploy_app() {
#       cf push "$app" || handle_error "Failed to deploy $app" "system" "exit"
#   }
#
#   # Generic error with custom exit code
#   critical_check() {
#       some_command || handle_error "Critical failure" "fatal" "exit" 1
#   }
# ===================================================================

# Common error handler with consistent formatting and exit codes
# Provides standardized error messages and allows control over exit vs return behavior
#
# Args:
#   $1: error_message - The error message to display
#   $2: error_type - Type of error (optional, default: error)
#       - validation: Input validation errors (exit code 2)
#       - system: System/infrastructure errors (exit code 3)
#       - user: User-triggered errors (exit code 4)
#       - fatal: Critical errors that must exit (exit code 1)
#       - cancelled: User cancelled action (exit code 0, not an error)
#       - error: Generic error (exit code 1)
#   $3: exit_mode - Whether to exit or return (optional, default: return)
#       - exit: Terminate script with exit code
#       - return: Return exit code to caller
#   $4: exit_code - Override exit code (optional, uses type default if not specified)
#
# Returns: Never returns if exit_mode=exit, otherwise returns exit_code
#
# Examples:
#   handle_error "Missing required parameter" "validation" "return"
#   handle_error "Environment and ticket required" "validation" "exit"
#   handle_error "Failed to connect to S3" "system" "exit"
#   handle_error "Operation cancelled by user" "cancelled" "return"
#   handle_error "Could not determine digest" || return 1
#
# Exit Code Reference:
#   0 - Success or user cancellation
#   1 - Generic/fatal error
#   2 - Validation error
#   3 - System error
#   4 - User error
handle_error() {
    local message="$1"
    local error_type="${2:-error}"
    local exit_mode="${3:-return}"
    local exit_code="${4:-}"

    # Set exit code based on error type if not explicitly provided
    case "$error_type" in
        validation)
            print_status $RED "❌ Error: $message"
            exit_code="${exit_code:-2}"
            ;;
        system)
            print_status $RED "❌ System Error: $message"
            exit_code="${exit_code:-3}"
            ;;
        user)
            print_status $RED "❌ Error: $message"
            exit_code="${exit_code:-4}"
            ;;
        fatal)
            print_status $RED "❌ Fatal Error: $message"
            exit_code="${exit_code:-1}"
            ;;
        cancelled)
            print_status $YELLOW "⚠️  Action cancelled"
            exit_code="${exit_code:-0}"
            ;;
        *)
            print_status $RED "❌ Error: $message"
            exit_code="${exit_code:-1}"
            ;;
    esac

    if [ "$exit_mode" = "exit" ]; then
        exit $exit_code
    else
        return $exit_code
    fi
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
# NIST 800-53: AU-3 - Content of Audit Records
# NIST 800-53: AU-9 - Protection of Audit Information
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

    # Get git info from /etc/motd (inside container) or git commands (local)
    local git_commit="unknown"
    local git_branch="unknown"

    if [ -f "/etc/motd" ]; then
        # Extract from container's MOTD
        git_commit=$(grep "commit:" /etc/motd 2>/dev/null | awk '{print $NF}' | head -1)
        git_branch=$(grep "branch:" /etc/motd 2>/dev/null | awk '{print $NF}' | head -1)
    fi

    # Fall back to git commands if not found in MOTD
    if [ -z "$git_commit" ] || [ "$git_commit" = "unknown" ]; then
        git_commit=$(git rev-parse HEAD 2>/dev/null || echo "unknown")
    fi
    if [ -z "$git_branch" ] || [ "$git_branch" = "unknown" ]; then
        git_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
    fi

    # Get currently deployed containers from S3 digest file
    setup_s3_vars >/dev/null 2>&1

    # Try cron bucket first (where cron writes digests), fall back to CMS bucket
    local digests_json=""
    if [ -n "$VCAP_SERVICES" ]; then
        local cron_bucket=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.bucket' 2>/dev/null)
        if [ -n "$cron_bucket" ] && [ "$cron_bucket" != "null" ]; then
            # Get credentials for cron bucket
            local cron_access_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.access_key_id' 2>/dev/null)
            local cron_secret_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.secret_access_key' 2>/dev/null)
            local cron_region=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.region' 2>/dev/null)

            # Try to fetch from cron bucket
            export AWS_ACCESS_KEY_ID="$cron_access_key"
            export AWS_SECRET_ACCESS_KEY="$cron_secret_key"
            export AWS_DEFAULT_REGION="$cron_region"
            digests_json=$(aws s3 cp "s3://${cron_bucket}/deployment-metadata/.current_digests_${environment}.json" - 2>/dev/null)
            unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION
        fi
    fi

    # Fall back to CMS bucket if not found in cron bucket
    if [ -z "$digests_json" ]; then
        digests_json=$(aws s3 cp "s3://${BUCKET_NAME}/deployment-metadata/.current_digests_${environment}.json" - $S3_EXTRA_PARAMS 2>/dev/null)
    fi

    # Get build number from local container's MOTD (if we're inside a container)
    local local_build="unknown"
    local local_app_name=""

    if [ -f "/etc/motd" ]; then
        local_build=$(grep "containertag:" /etc/motd 2>/dev/null | awk '{print $NF}')
        [ -z "$local_build" ] || [ "$local_build" = "none" ] && local_build="unknown"

        # Determine which app we're running in
        if [ -n "$VCAP_APPLICATION" ]; then
            local_app_name=$(echo "$VCAP_APPLICATION" | jq -r .application_name 2>/dev/null)
        fi
    fi

    # Extract all containers from digest JSON
    # Format: {"timestamp": "...", "environment": "dev", "containers": {"app": "digest", ...}}
    local containers_list=""
    if [ -n "$digests_json" ]; then
        # Extract container names (all keys in the "containers" object)
        containers_list=$(echo "$digests_json" | sed -n 's/.*"containers":[[:space:]]*{\([^}]*\)}.*/\1/p' | sed 's/"//g' | sed 's/:[^,]*//g' | tr ',' '\n' | sed 's/^[[:space:]]*//' | sort)
    fi

    # If no containers found in S3, fall back to CF CLI for known apps
    if [ -z "$containers_list" ] && command -v cf >/dev/null 2>&1; then
        containers_list="cms
www
waf"
    fi

    # Get username (circleci or actual user)
    local created_by=$(whoami 2>/dev/null || echo "unknown")
    if [ -n "$CIRCLECI" ]; then
        created_by="circleci"
    fi

    # Determine primary build number (use cms if available, otherwise local)
    local cms_build="unknown"
    if [ "$local_app_name" = "cms" ]; then
        cms_build="$local_build"
    fi

    # Build JSON with dynamic container list
    echo "{"
    echo "  \"backup_tag\": \"$backup_tag\","
    echo "  \"timestamp\": \"$(date -u +"%Y-%m-%dT%H:%M:%SZ")\","
    echo "  \"environment\": \"$environment\","
    echo "  \"ticket\": \"$ticket\","
    echo "  \"backup_type\": \"$backup_type\","
    echo "  \"git_commit\": \"$git_commit\","
    echo "  \"git_branch\": \"$git_branch\","
    echo "  \"deployed_containers\": {"

    # Build containers object dynamically
    local first_container=true
    for container_name in $containers_list; do
        # Skip empty names
        [ -z "$container_name" ] && continue

        # Get digest for this container
        local container_digest=""
        if [ -n "$digests_json" ]; then
            container_digest=$(echo "$digests_json" | sed -n "s/.*\"$container_name\":[[:space:]]*\"\([^\"]*\)\".*/\1/p")
        fi

        # If still no digest, try CF CLI (only works outside container)
        if [ -z "$container_digest" ] && command -v cf >/dev/null 2>&1; then
            container_digest=$(get_app_digest "$container_name" 2>/dev/null || echo "")
        fi

        # Get build number for this container
        local container_build="unknown"
        if [ "$local_app_name" = "$container_name" ]; then
            # We're running in this container - use local build
            container_build="$local_build"
        elif [ "$cms_build" != "unknown" ]; then
            # Use cms build as fallback (likely same deployment)
            container_build="$cms_build"
        fi

        # Add comma before all entries except the first
        if [ "$first_container" = "false" ]; then
            echo ","
        fi
        first_container=false

        # Output container entry (no trailing comma on last line of this entry)
        echo -n "    \"$container_name\": {"
        echo -n "\"cci_build\": \"$container_build\", "
        echo -n "\"digest\": \"$container_digest\""
        echo -n "}"
    done

    echo ""
    echo "  },"
    echo "  \"created_by\": \"$created_by\""
    echo "}"
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
#   $1: backup_tag - Tag to fetch metadata for (optional - if empty, fetches latest)
# Returns: JSON string or empty if not found
fetch_deployment_metadata() {
    local backup_tag="$1"

    setup_s3_vars || return 1

    # If no tag provided, find the most recent metadata file
    if [ -z "$backup_tag" ]; then
        backup_tag=$(fetch_latest_backup_tag)
        if [ -z "$backup_tag" ]; then
            return 1
        fi
    fi

    local metadata_path="deployment-metadata/${backup_tag}.json"

    # Download from S3 to stdout
    aws s3 cp "s3://${BUCKET_NAME}/${metadata_path}" - $S3_EXTRA_PARAMS 2>/dev/null
}

# Fetch the tag of the most recent backup metadata file
# Returns: backup tag string or empty if not found
fetch_latest_backup_tag() {
    setup_s3_vars || return 1

    # List all metadata files, sort by reverse order (newest first), and extract tag from filename
    aws s3 ls "s3://${BUCKET_NAME}/deployment-metadata/" $S3_EXTRA_PARAMS 2>/dev/null | \
        grep '\.json$' | \
        grep -v '.current_digests' | \
        sort -r | \
        head -1 | \
        awk '{print $4}' | \
        sed 's/\.json$//'
}

# Extract container digests from deployment metadata JSON
# Args:
#   $1: metadata_json - JSON string from fetch_deployment_metadata
#   $2: apps - Comma-separated list of app names (optional - defaults to cms,www,waf)
# Returns: One digest per line for each app
extract_digests_from_metadata() {
    local metadata_json="$1"
    local apps="${2:-cms,www,waf}"

    if [ -z "$metadata_json" ]; then
        return 1
    fi

    # Parse comma-separated app list
    local IFS=','
    for app in $apps; do
        # Extract digest for this app from JSON using grep and sed pipeline
        # Look for the app section, then extract the digest value
        echo "$metadata_json" | grep -A 2 "\"$app\":" | grep '"digest"' | sed 's/.*"digest":[[:space:]]*"\([^"]*\)".*/\1/' | head -1
    done
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

# Show current container digests captured by cron
# Reads .current_digests_{env}.json from S3 and displays in formatted output
# This shows what digests would be captured if a backup were created right now
show_current_digests() {
    # Initialize backup system to get S3 access
    init_backup_system >/dev/null 2>&1 || true

    # Use cron bucket instead of CMS bucket for digest files
    # Check for cron-state-storage binding first, fall back to storage
    local bucket_name=""
    local access_key=""
    local secret_key=""
    local region=""

    if [ -n "$VCAP_SERVICES" ]; then
        # Try cron-state-storage first (where cron writes digests)
        bucket_name=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.bucket' 2>/dev/null)

        if [ -n "$bucket_name" ] && [ "$bucket_name" != "null" ]; then
            # Get credentials for cron bucket
            access_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.access_key_id' 2>/dev/null)
            secret_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.secret_access_key' 2>/dev/null)
            region=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "cron-state-storage") | .credentials.region' 2>/dev/null)
        else
            # Fall back to main storage bucket
            bucket_name=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.bucket' 2>/dev/null)
            access_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.access_key_id' 2>/dev/null)
            secret_key=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.secret_access_key' 2>/dev/null)
            region=$(echo "$VCAP_SERVICES" | jq -r '.["s3"][]? | select(.name == "storage") | .credentials.region' 2>/dev/null)
        fi
    fi

    if [ -z "$bucket_name" ] || [ "$bucket_name" = "null" ]; then
        handle_error "Could not determine S3 bucket for digest files" "system" "return"
    fi

    # Determine environment
    local env="${APP_SPACE:-}"
    if [ -z "$env" ] || [ "$env" = "local" ]; then
        if [ -n "$VCAP_APPLICATION" ]; then
            env=$(echo "$VCAP_APPLICATION" | jq -r '.space_name' 2>/dev/null)
        fi
    fi

    if [ -z "$env" ] || [ "$env" = "null" ]; then
        echo "⚠️  Warning: Could not determine environment, defaulting to 'dev'"
        env="dev"
    fi

    print_status $BLUE "📦 Current Container Digests (from cron capture)"
    echo ""
    echo "Environment: $env"
    echo "Source: deployment-metadata/.current_digests_${env}.json"
    echo "Bucket: $bucket_name"
    echo ""

    # Fetch the digest file from S3 with proper credentials
    local digest_file="s3://${bucket_name}/deployment-metadata/.current_digests_${env}.json"

    # Export AWS credentials for this operation
    export AWS_ACCESS_KEY_ID="$access_key"
    export AWS_SECRET_ACCESS_KEY="$secret_key"
    export AWS_DEFAULT_REGION="$region"

    local digest_json=$(aws s3 cp "$digest_file" - 2>/dev/null)

    # Clean up credentials
    unset AWS_ACCESS_KEY_ID
    unset AWS_SECRET_ACCESS_KEY
    unset AWS_DEFAULT_REGION

    if [ -z "$digest_json" ]; then
        print_status $YELLOW "⚠️  No digest file found at: $digest_file"
        echo ""
        echo "This file is created by the cron app every 5 minutes."
        echo "It may not exist if:"
        echo "  • Cron app is not running"
        echo "  • Cron app hasn't run the digest update script yet"
        echo "  • S3 permissions are not configured correctly"
        return 1
    fi

    # Parse and display the JSON
    local timestamp=$(echo "$digest_json" | jq -r '.timestamp' 2>/dev/null)
    local captured_env=$(echo "$digest_json" | jq -r '.environment' 2>/dev/null)

    if [ -n "$timestamp" ] && [ "$timestamp" != "null" ]; then
        echo "Last Updated: $timestamp"
    fi
    echo ""

    print_status $GREEN "Container Digests:"
    echo ""

    # Extract all container names and their digests
    # The JSON from cron has literal \n characters, so we need to interpret them
    printf '%b' "$digest_json" | jq -r '.containers | to_entries[] | "  \(.key): \(.value)"' 2>/dev/null

    echo ""
    print_status $BLUE "💡 This shows what would be captured in backup metadata"
    echo "   Cron updates this file every 5 minutes automatically"
    echo ""
}

# ===================================================================
# VALIDATION FUNCTIONS
# ===================================================================

# Validate backup tag and exit/return on failure
require_valid_tag() {
    local tag="$1"
    local should_exit="${2:-false}"

    if ! validate_backup_tag "$tag"; then
        if [ "$should_exit" = "true" ]; then
            exit 1
        else
            return 1
        fi
    fi
    return 0
}

# Validate backup tag format to prevent command injection
validate_backup_tag() {
    local tag="$1"

    if [ -z "$tag" ]; then
        handle_error "Backup tag cannot be empty" "validation" "return"
    fi

    # Only allow alphanumeric, hyphens, underscores, and dots
    # This prevents command injection via shell metacharacters
    if ! echo "$tag" | grep -qE '^[a-zA-Z0-9._-]+$'; then
        print_status $RED "❌ Error: Invalid backup tag format: $tag"
        print_status $YELLOW "   Tags may only contain: letters, numbers, dots, hyphens, underscores"
        return 2
    fi

    # Check reasonable length
    if [ ${#tag} -gt ${TAG_MAX_LENGTH:-200} ]; then
        handle_error "Backup tag too long (max ${TAG_MAX_LENGTH:-200} characters)" "validation" "return"
    fi

    return 0
}

# Extract build number from container digest
# Falls back to motd if digest doesn't contain build number
extract_build_from_digest() {
    local digest="$1"
    local build_num=""

    # Try to extract from digest first (format: usagov_cms:BUILD@sha256:...)
    if echo "$digest" | grep -qE 'usagov[_-](cms|2021):[0-9]+@'; then
        build_num=$(echo "$digest" | sed -E 's/.*usagov[_-](cms|2021):([0-9]+)@.*/\2/')
    fi

    # Fallback: Get from motd if available (Cloud Foundry only)
    if [ -z "$build_num" ] || [ "$build_num" = "unknown" ]; then
        if command -v cf >/dev/null 2>&1 && [ -n "$VCAP_APPLICATION" ]; then
            build_num=$(cat /etc/motd 2>/dev/null | grep 'containertag:' | sed 's/.*containertag:[[:space:]]*//' | tr -d '[:space:]')
        elif command -v cf >/dev/null 2>&1; then
            # If not on CF, try SSH to get it (slower but works)
            build_num=$(cf ssh cms -c "cat /etc/motd 2>/dev/null | grep 'containertag:' | sed 's/.*containertag:[[:space:]]*//'" 2>/dev/null | tr -d '[:space:]')
        fi
    fi

    # Return result or "unknown"
    if [ -n "$build_num" ] && [ "$build_num" != "unknown" ]; then
        echo "$build_num"
    else
        echo "unknown"
    fi
}

# Parse comma-separated backup types into normalized list
parse_backup_types() {
    local types_arg="$1"

    if [ -z "$types_arg" ] || [ "$types_arg" = "all" ]; then
        echo "static,public,db"
    else
        echo "$types_arg"
    fi
}

# Check if a specific backup type is in the list
has_backup_type() {
    local backup_types="$1"
    local check_type="$2"
    echo "$backup_types" | grep -q "$check_type"
}

# Get current Cloud Foundry environment
get_current_environment() {
    local env="${DEPLOY_ENV:-$(cf target 2>/dev/null | grep 'space:' | awk '{print $2}')}"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Could not determine environment"
        echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
        return 3
    fi
    echo "$env"
    return 0
}

# Get latest S3 backup tag for a given type
get_latest_s3_backup() {
    local backup_type="$1"
    local s3_path=""

    setup_s3_vars || return 1

    case "$backup_type" in
        static)
            s3_path="$AUTO_STATIC_BACKUP_PATH"
            aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -1 | awk '{print $2}' | tr -d '/'
            ;;
        public)
            s3_path="$AUTO_PUBLIC_BACKUP_PATH"
            aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -1 | awk '{print $2}' | tr -d '/'
            ;;
        db)
            s3_path="$AUTO_DB_BACKUP_PATH"
            aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS | grep '\.sql\.gz$' | sort -r | head -1 | awk '{print $4}' | xargs basename | sed 's/\.sql\.gz$//'
            ;;
        *)
            return 1
            ;;
    esac
}

# Validate output path to prevent path traversal attacks
validate_output_path() {
    local path="$1"

    if [ -z "$path" ]; then
        print_status $RED "❌ Output path cannot be empty"
        return 1
    fi

    # Reject paths with suspicious patterns
    if echo "$path" | grep -qE '(\.\./|^/etc/|^/var/|^/usr/|^/bin/|^/sbin/)'; then
        print_status $RED "❌ Invalid output path: $path"
        print_status $YELLOW "   Path traversal or system directory access not allowed"
        return 1
    fi

    # Convert to absolute path if relative
    if [ "${path:0:1}" != "/" ]; then
        path="$(pwd)/$path"
    fi

    # Ensure path is under /tmp or current working directory
    local allowed=false
    if echo "$path" | grep -q "^/tmp/"; then
        allowed=true
    elif echo "$path" | grep -q "^$(pwd)"; then
        allowed=true
    fi

    if [ "$allowed" = "false" ]; then
        handle_error "Output path must be under /tmp or current directory" "validation" "return"
    fi

    echo "$path"
    return 0
}

# Validate SQL dump content for dangerous patterns
# Args:
#   $1: sql_file - Path to SQL file
# Returns: 0 if safe, 1 if dangerous patterns found
validate_sql_content() {
    local sql_file="$1"

    if [ ! -f "$sql_file" ]; then
        handle_error "SQL file not found: $sql_file" "validation" "return"
    fi

    # Check for dangerous SQL patterns that could be used for exploitation
    # Note: This is a basic check - more sophisticated validation may be needed
    local dangerous_patterns="INTO OUTFILE|INTO DUMPFILE|LOAD_FILE|LOAD DATA|SYSTEM|EXEC |GRANT ALL|CREATE USER"

    if grep -qiE "($dangerous_patterns)" "$sql_file"; then
        print_status $RED "❌ Error: Dangerous SQL patterns detected in dump file"
        print_status $YELLOW "   File may contain: OUTFILE, LOAD_FILE, SYSTEM, GRANT, or CREATE USER statements"
        return 2
    fi

    return 0
}

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
        print_status $RED "❌ Error: Missing or unreadable $description: $file_path"
        return 2
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
        print_status $RED "❌ Error: Command not found: $cmd"
        return 2
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

get_next_backup_suffix() {
    local backup_type="$1"
    local base_tag="$2"

    setup_s3_vars || return 1

    local lockfile="/tmp/backup-suffix-${backup_type}.lock"
    local lockfd=200

    eval "exec $lockfd>$lockfile"
    if ! flock -x -w $FLOCK_TIMEOUT_SECONDS $lockfd 2>/dev/null; then
        print_status $YELLOW "⚠️  Could not acquire lock, proceeding without lock" >&2
    fi

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
            flock -u $lockfd 2>/dev/null
            eval "exec $lockfd>&-"
            echo "0"
            return 0
            ;;
    esac

    local max_attempts=$MAX_RETRY_ATTEMPTS
    local attempt=0
    local existing_numbers=""

    while [ $attempt -lt $max_attempts ]; do
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

        local result=$((max_num + 1))

        if [ $result -lt $MAX_RETRY_ATTEMPTS ]; then
            break
        fi

        attempt=$((attempt + 1))
        if [ $attempt -ge $max_attempts ]; then
            print_status $RED "❌ Error: Exceeded maximum suffix attempts ($max_attempts)" >&2
            print_status $YELLOW "   Current max suffix found: $max_num" >&2
            result=0
            break
        fi

        sleep 1
    done

    # Release lock
    flock -u $lockfd 2>/dev/null
    eval "exec $lockfd>&-"

    echo "$result"
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

    # If we're already inside a Tome process (set by tome-run.sh),
    # don't detect ourselves to avoid deadlock during automatic backups
    if [ -n "$INSIDE_TOME_PROCESS" ]; then
        return 1  # Not running (from perspective of caller)
    fi

    local ps_aux=$(ps aux)
    local running_count=$(echo "$ps_aux" | grep "$script_name" | grep -v grep | wc -l)

    if [ "$running_count" -gt "0" ]; then
        return 0  # Running
    else
        return 1  # Not running
    fi
}

# Prepare Drupal state for backup/restore operations
# Manages Tome and/or maintenance mode based on state type
# Args:
#   $1: state_type - "tome", "maintenance", or "both" (default: "both")
#   $2: max_wait_minutes (optional, default: 25) - Maximum time to wait for tome to stop
# Returns: 0 on success, 1 on failure or timeout
prepare_drupal_state() {
    local state_type="${1:-both}"
    local max_wait_minutes=${2:-25}

    # Validate state_type
    if [ "$state_type" != "tome" ] && [ "$state_type" != "maintenance" ] && [ "$state_type" != "both" ]; then
        print_status $RED "Error: Invalid state_type. Must be 'tome', 'maintenance', or 'both'"
        return 1
    fi

    # Validate max_wait_minutes
    if ! echo "$max_wait_minutes" | grep -qE '^[0-9]+$' || [ "$max_wait_minutes" -gt 30 ]; then
        print_status $RED "Error: max_wait_minutes must be an integer less than or equal to 30"
        return 1
    fi

    case "$state_type" in
        "tome")
            # Wait for tome to stop and disable it
            local start_seconds=$(date +'%s')
            local sleep_time=15

            print_status $YELLOW "⏳ Waiting for Tome to stop (max: ${max_wait_minutes} minutes)..."

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

            print_status $YELLOW "🔒 Disabling Tome..."
            audit_log "tome_disable" "started" "Tome disable requested" "max_wait_minutes=\"${max_wait_minutes}\""
            if ! drush sset usagov.tome_run_disabled 1 2>/dev/null; then
                print_status $RED "❌ Failed to disable Tome"
                audit_log "tome_disable" "failure" "Failed to disable Tome"
                return 1
            fi

            local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
            print_status $GREEN "✅ Tome disabled: $tome_disabled"
            audit_log "tome_disable" "success" "Tome disabled successfully" "tome_disabled_state=\"${tome_disabled}\""
            ;;

        "maintenance")
            # Enable maintenance mode only
            print_status $YELLOW "🚧 Enabling maintenance mode..."
            audit_log "maintenance_mode_enable" "started" "Enabling maintenance mode"
            if drush sset system.maintenance_mode 1 2>/dev/null && drush cr 2>/dev/null; then
                local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
                print_status $GREEN "✅ Maintenance mode enabled: $maint_mode"
                audit_log "maintenance_mode_enable" "success" "Maintenance mode enabled" "maint_mode_state=\"${maint_mode}\""
            else
                print_status $RED "❌ Failed to enable maintenance mode"
                audit_log "maintenance_mode_enable" "failure" "Failed to enable maintenance mode"
                return 1
            fi
            ;;

        "both")
            # Wait for tome to stop, disable it, then enable maintenance mode
            local start_seconds=$(date +'%s')
            local sleep_time=15

            print_status $YELLOW "⏳ Waiting for Tome to stop (max: ${max_wait_minutes} minutes)..."

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

            print_status $YELLOW "🔒 Disabling Tome..."
            audit_log "tome_disable" "started" "Tome disable requested" "max_wait_minutes=\"${max_wait_minutes}\""
            if ! drush sset usagov.tome_run_disabled 1 2>/dev/null; then
                print_status $RED "❌ Failed to disable Tome"
                audit_log "tome_disable" "failure" "Failed to disable Tome"
                return 1
            fi

            local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
            print_status $GREEN "✅ Tome disabled: $tome_disabled"
            audit_log "tome_disable" "success" "Tome disabled successfully" "tome_disabled_state=\"${tome_disabled}\""

            print_status $YELLOW "🚧 Enabling maintenance mode..."
            audit_log "maintenance_mode_enable" "started" "Enabling maintenance mode"
            if drush sset system.maintenance_mode 1 2>/dev/null && drush cr 2>/dev/null; then
                local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
                print_status $GREEN "✅ Maintenance mode enabled: $maint_mode"
                audit_log "maintenance_mode_enable" "success" "Maintenance mode enabled" "maint_mode_state=\"${maint_mode}\""
            else
                print_status $RED "❌ Failed to enable maintenance mode"
                audit_log "maintenance_mode_enable" "failure" "Failed to enable maintenance mode, rolling back Tome disable"
                # Try to re-enable tome before returning
                drush sdel usagov.tome_run_disabled 2>/dev/null
                return 1
            fi
            ;;
    esac

    return 0
}

# Restore Drupal state to normal operation after backup/restore
# Manages Tome and/or maintenance mode based on state type
# Args:
#   $1: state_type - "tome", "maintenance", or "both" (default: "both")
# Returns: 0 on success, 1 on failure
restore_drupal_state() {
    local state_type="${1:-both}"

    # Validate state_type
    if [ "$state_type" != "tome" ] && [ "$state_type" != "maintenance" ] && [ "$state_type" != "both" ]; then
        print_status $RED "Error: Invalid state_type. Must be 'tome', 'maintenance', or 'both'"
        return 1
    fi

    case "$state_type" in
        "tome")
            # Re-enable Tome only
            print_status $YELLOW "🔓 Re-enabling Tome..."
            audit_log "tome_enable" "started" "Re-enabling Tome"
            if ! drush sdel usagov.tome_run_disabled 2>/dev/null; then
                print_status $RED "❌ Failed to re-enable Tome"
                audit_log "tome_enable" "failure" "Failed to re-enable Tome"
                return 1
            fi

            local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
            print_status $GREEN "✅ Tome re-enabled (disabled flag: ${tome_disabled:-none})"
            audit_log "tome_enable" "success" "Tome re-enabled" "tome_disabled_state=\"${tome_disabled:-none}\""
            ;;

        "maintenance")
            # Disable maintenance mode only
            print_status $YELLOW "🚧 Disabling maintenance mode..."
            audit_log "maintenance_mode_disable" "started" "Disabling maintenance mode"
            if drush sset system.maintenance_mode 0 2>/dev/null && drush cr 2>/dev/null; then
                local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
                print_status $GREEN "✅ Maintenance mode disabled: $maint_mode"
                audit_log "maintenance_mode_disable" "success" "Maintenance mode disabled" "maint_mode_state=\"${maint_mode}\""
            else
                print_status $RED "❌ Failed to disable maintenance mode"
                audit_log "maintenance_mode_disable" "failure" "Failed to disable maintenance mode"
                return 1
            fi
            ;;

        "both")
            # Disable maintenance mode and re-enable Tome
            print_status $YELLOW "🚧 Disabling maintenance mode..."
            audit_log "maintenance_mode_disable" "started" "Restoring Drupal state"
            if drush sset system.maintenance_mode 0 2>/dev/null && drush cr 2>/dev/null; then
                local maint_mode=$(drush sget system.maintenance_mode 2>/dev/null)
                print_status $GREEN "✅ Maintenance mode disabled: $maint_mode"
                audit_log "maintenance_mode_disable" "success" "Maintenance mode disabled" "maint_mode_state=\"${maint_mode}\""
            else
                print_status $RED "❌ Failed to disable maintenance mode"
                audit_log "maintenance_mode_disable" "failure" "Failed to disable maintenance mode"
            fi

            print_status $YELLOW "🔓 Re-enabling Tome..."
            audit_log "tome_enable" "started" "Re-enabling Tome"
            if ! drush sdel usagov.tome_run_disabled 2>/dev/null; then
                print_status $RED "❌ Failed to re-enable Tome"
                audit_log "tome_enable" "failure" "Failed to re-enable Tome"
                return 1
            fi

            local tome_disabled=$(drush sget usagov.tome_run_disabled 2>/dev/null)
            print_status $GREEN "✅ Tome re-enabled (disabled flag: ${tome_disabled:-none})"
            audit_log "tome_enable" "success" "Tome re-enabled" "tome_disabled_state=\"${tome_disabled:-none}\""
            ;;
    esac

    return 0
}

# Find corresponding backup of specific type for smart restore
# Args: $1=static backup tag, $2=backup type (public|db)
# Returns: matching backup name or empty on error
find_corresponding_backup() {
    local static_backup_tag="$1"
    local backup_type="$2"

    local exact_match_path=""
    local search_path=""
    local file_suffix=""

    case "$backup_type" in
        public)
            exact_match_path="$AUTO_PUBLIC_BACKUP_PATH/$static_backup_tag/"
            search_path="$AUTO_PUBLIC_BACKUP_PATH/"
            ;;
        db)
            file_suffix=".sql.gz"
            exact_match_path="$AUTO_DB_BACKUP_PATH/${static_backup_tag}${file_suffix}"
            search_path="$AUTO_DB_BACKUP_PATH/"
            ;;
        *)
            return 1
            ;;
    esac

    # Check for exact match first
    if aws s3 ls "s3://$BUCKET_NAME/$exact_match_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        if [ "$backup_type" = "public" ]; then
            echo "$static_backup_tag"
        else
            echo "${static_backup_tag}${file_suffix}"
        fi
        return 0
    fi

    # No exact match - find most recent backup at or before static backup time
    local static_date=$(extract_date_from_backup_name "$static_backup_tag")
    [ -z "$static_date" ] && return 1

    local static_epoch=$(date -u -d "$static_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$static_date" '+%s' 2>/dev/null)
    [ -z "$static_epoch" ] && return 1

    local temp_list="/tmp/${backup_type}_backup_search_$$"
    if [ "$backup_type" = "public" ]; then
        aws s3 ls "s3://$BUCKET_NAME/$search_path" $S3_EXTRA_PARAMS | grep "PRE " > "$temp_list" 2>/dev/null
    else
        aws s3 ls "s3://$BUCKET_NAME/$search_path" --recursive $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | awk '{print $4}' | xargs -I {} basename {} > "$temp_list" 2>/dev/null
    fi

    local best_backup=""
    local best_epoch=0

    while read -r line; do
        [ -z "$line" ] && continue

        local backup_name
        if [ "$backup_type" = "public" ]; then
            backup_name=$(echo "$line" | awk '{print $2}' | tr -d '/')
        else
            backup_name="$line"
        fi

        local backup_date=$(extract_date_from_backup_name "$backup_name")
        [ -z "$backup_date" ] && continue

        local backup_epoch=$(date -u -d "$backup_date" '+%s' 2>/dev/null || date -u -j -f '%Y-%m-%d' "$backup_date" '+%s' 2>/dev/null)

        if [ -n "$backup_epoch" ] && [ "$backup_epoch" -le "$static_epoch" ] && [ "$backup_epoch" -gt "$best_epoch" ]; then
            best_backup="$backup_name"
            best_epoch="$backup_epoch"
        fi
    done < "$temp_list"

    rm -f "$temp_list" 2>/dev/null

    if [ -n "$best_backup" ]; then
        echo "$best_backup"
        return 0
    fi
    return 1
}

# Unified state management command
# Manages Drupal state (Tome and/or maintenance mode)
# Args:
#   $1: action - "enable" or "disable"
#   $2: state_type - "tome", "sm" (site maintenance), or "both" (default: "both")
#   $3: max_wait_minutes - For disable actions only (default: 25)
# Returns: 0 on success, 1 on failure
state_command() {
    local action="$1"
    local state_type="${2:-both}"
    local max_wait="${3:-25}"

    # Validate action
    if [ "$action" != "enable" ] && [ "$action" != "disable" ]; then
        print_status $RED "❌ Invalid action: $action"
        print_status $YELLOW "   Must be 'enable' or 'disable'"
        return 1
    fi

    # Map 'sm' to 'maintenance'
    if [ "$state_type" = "sm" ]; then
        state_type="maintenance"
    fi

    # Validate state_type
    if [ "$state_type" != "tome" ] && [ "$state_type" != "maintenance" ] && [ "$state_type" != "both" ]; then
        print_status $RED "❌ Invalid state type: $state_type"
        print_status $YELLOW "   Must be 'tome', 'sm', or 'both'"
        return 1
    fi

    if [ "$action" = "disable" ]; then
        print_status $BLUE "🔧 Disabling Drupal state ($state_type)..."
        if prepare_drupal_state "$state_type" "$max_wait"; then
            print_status $GREEN "✅ Drupal state disabled ($state_type)"
            return 0
        else
            print_status $RED "❌ Failed to disable Drupal state ($state_type)"
            return 1
        fi
    else
        print_status $BLUE "🔧 Enabling Drupal state ($state_type)..."
        if restore_drupal_state "$state_type"; then
            print_status $GREEN "✅ Drupal state enabled ($state_type)"
            return 0
        else
            print_status $RED "❌ Failed to enable Drupal state ($state_type)"
            return 1
        fi
    fi
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
