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
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
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
    elif [ -d "snapshot" ] && [ -f "common.sh" ]; then
        # Running from scripts directory
        PROJECT_ROOT="$(cd .. && pwd)"
        BACKUP_DIR="$PROJECT_ROOT/scripts/snapshot"
    elif [ "$(basename "$(pwd)")" = "snapshot" ] && [ -f "../common.sh" ]; then
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
    # The message is data, not a format string: passing it as one made any '%'
    # in a message corrupt the output (e.g. "50% floor" printed as "50 0.000000loor").
    # %b expands the escape sequences in the colour codes; %s prints the message
    # literally.
    printf '%b%s%b\n' "$color" "$message" "$NC"
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
# FORMATTING SERVICE
# ===================================================================

# Check for --json flag in arguments
# Args: $@ - All arguments to check
# Returns: 0 if --json flag found, 1 otherwise
has_json_flag() {
    for arg in "$@"; do
        if [ "$arg" = "--json" ]; then
            return 0
        fi
    done
    return 1
}

# Format data as JSON with pretty printing
# Args:
#   $1: json_data - JSON string to format
# Output: Pretty-printed JSON via jq
format_json() {
    local json_data="$1"
    echo "$json_data" | jq .
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
            printf "Type 'yes' to proceed: "
            read -r confirm
            if [ "$confirm" = "yes" ]; then
                echo ""
                return 0
            else
                handle_error "" "cancelled" "return"
                return 1
            fi
            ;;

        exact)
            # Exact text matching with retry logic
            if [ -z "$required_text" ]; then
                handle_error "required_text not provided for exact mode" "validation" "return"
                return 2
            fi

            local attempts=0
            local user_input=""

            while [ $attempts -lt $max_attempts ]; do
                attempts=$((attempts + 1))

                if [ $attempts -gt 1 ]; then
                    print_status $RED "❌ Incorrect. Attempt $attempts of $max_attempts"
                    echo ""
                fi

                printf "Type '%s' to confirm: " "$required_text"
                read -r user_input

                if [ "$user_input" = "$required_text" ]; then
                    echo ""
                    return 0
                fi
            done

            # Max attempts exceeded
            handle_error "Maximum attempts exceeded" "cancelled" "return"
            return 1
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
    local extracted_ticket=""

    # Extract prefix (first segment before first hyphen)
    prefix=$(echo "$tag" | cut -d'-' -f1)
    extracted_ticket=$(echo "$tag" | grep -oE 'USAGOV-[0-9]+' | head -1)

    # Determine backup type and ticket from prefix and suffix patterns
    case "$prefix" in
        AUTO)
            backup_type="auto"
            ticket="none"
            ;;
        HOTFIX)
            backup_type="hotfix"
            # Extract USAGOV ticket if present anywhere in tag
            ticket="$extracted_ticket"
            [ -z "$ticket" ] && ticket="none"
            ;;
        USAGOV*)
            # Prefix starts with USAGOV (e.g., USAGOV-1234-prod-...)
            ticket="$extracted_ticket"
            [ -z "$ticket" ] && ticket="none"
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
            ticket="$extracted_ticket"
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

# ===================================================================
# DEPLOYMENT METADATA CONTRACT
# ===================================================================
#
# One schema, one producer, one reader, one validator. Before this, the
# producer wrote `deployed_containers` while the validator required
# `containers`, the cron capture was assembled by string concatenation with
# `\n` escapes (valid JSON only when the running shell's `echo` expands them),
# and every reader scraped that with its own `sed` expression. Those readers
# only worked on the single-line malformed form: given properly formatted
# JSON the container list came back empty, and an empty list is recorded as a
# backup with no digests at all.
#
# Everything below goes through jq, so a document either parses or is refused.
#
# NIST 800-53: AU-3 (content of audit records), CM-3 (configuration change
# control), SI-7 (software and information integrity)

# Schema version stamped into every metadata document this code writes.
# Plain assignment, not readonly: the test suite re-sources this file.
DEPLOYMENT_METADATA_VERSION=1

# A digest is either a bare manifest digest or an image reference pinned to one.
# Anything else is not something we can redeploy from.
DEPLOYMENT_DIGEST_PATTERN='^([A-Za-z0-9][A-Za-z0-9._/:-]*@)?sha256:[0-9a-f]{64}$'

# Require jq before building or reading a metadata document
# Returns: 0 if jq is present, 1 otherwise (message on stderr)
require_jq() {
    if command -v jq >/dev/null 2>&1; then
        return 0
    fi
    print_status $RED "❌ jq is required to read or write deployment metadata" >&2
    return 1
}

# Convert an ISO-8601 UTC timestamp ("2026-08-19T12:00:00Z") to epoch seconds.
# Done arithmetically rather than with `date -d`, whose accepted formats differ
# between GNU date, BSD date and BusyBox — this runs in all three.
# Args:
#   $1: timestamp
# Returns: 0 and echoes epoch seconds, or 1 if the input is not that shape
iso8601_to_epoch() {
    local ts="$1"
    local y m d hh mm ss era yoe doy doe days

    case "$ts" in
        [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]T[0-9][0-9]:[0-9][0-9]:[0-9][0-9]Z) ;;
        *) return 1 ;;
    esac

    # Strip leading zeros so arithmetic does not read them as octal
    y=$(printf '%s' "$ts" | cut -c1-4)
    m=$(printf '%s' "$ts" | cut -c6-7 | sed 's/^0//')
    d=$(printf '%s' "$ts" | cut -c9-10 | sed 's/^0//')
    hh=$(printf '%s' "$ts" | cut -c12-13 | sed 's/^0//')
    mm=$(printf '%s' "$ts" | cut -c15-16 | sed 's/^0//')
    ss=$(printf '%s' "$ts" | cut -c18-19 | sed 's/^0//')
    : "${m:=0}" "${d:=0}" "${hh:=0}" "${mm:=0}" "${ss:=0}"

    # days_from_civil, per Howard Hinnant's algorithm
    [ "$m" -le 2 ] && y=$((y - 1))
    era=$((y / 400))
    yoe=$((y - era * 400))
    if [ "$m" -gt 2 ]; then
        doy=$(((153 * (m - 3) + 2) / 5 + d - 1))
    else
        doy=$(((153 * (m + 9) + 2) / 5 + d - 1))
    fi
    doe=$((yoe * 365 + yoe / 4 - yoe / 100 + doy))
    days=$((era * 146097 + doe - 719468))

    echo $((days * 86400 + hh * 3600 + mm * 60 + ss))
}

# Parse a JSON document, tolerating the legacy escaped form.
# The cron capture was written as one string containing literal "\n" sequences,
# so whether the object on S3 is valid JSON depends on which shell's `echo`
# wrote it. Objects already in the buckets are in that form, so expand and
# retry rather than declaring years of captures unreadable.
# Args:
#   $1: raw document text
# Returns: 0 and echoes compact canonical JSON, 1 if it cannot be parsed
normalize_json_document() {
    local raw="$1"
    local expanded

    [ -n "$raw" ] || return 1
    require_jq || return 1

    if printf '%s' "$raw" | jq -e . >/dev/null 2>&1; then
        printf '%s' "$raw" | jq -c .
        return 0
    fi

    expanded=$(printf '%b' "$raw")
    if printf '%s' "$expanded" | jq -e . >/dev/null 2>&1; then
        printf '%s' "$expanded" | jq -c .
        return 0
    fi

    return 1
}

# Report how a document parsed: json, legacy-escaped, or unparsable
# Args:
#   $1: raw document text
deployment_metadata_parse_mode() {
    local raw="$1"

    [ -n "$raw" ] || { echo "absent"; return 0; }
    if printf '%s' "$raw" | jq -e . >/dev/null 2>&1; then
        echo "json"
    elif printf '%b' "$raw" | jq -e . >/dev/null 2>&1; then
        echo "legacy-escaped"
    else
        echo "unparsable"
    fi
}

# Read the container map out of any metadata or capture document, normalizing
# the three shapes in circulation: `containers` (current), `deployed_containers`
# (backup metadata written before this change), and a plain
# "app": "digest" string map (the cron capture).
# Args:
#   $1: canonical JSON document
# Returns: 0 and echoes {"app": {"digest": ..., ...}, ...}
metadata_containers_json() {
    printf '%s' "$1" | jq -c '
        (.containers // .deployed_containers // {})
        | with_entries(.value |= (if type == "string" then {digest: .} else . end))
    ' 2>/dev/null
}

# Extract the release tag from an image reference pinned to a digest.
# "…/usagov_cms:16302@sha256:<64 hex>" -> "16302". Empty for a bare digest.
# Args:
#   $1: digest or image reference
digest_release_tag() {
    printf '%s' "$1" | sed -n 's/^.*:\([^:@/]*\)@sha256:[0-9a-f]\{64\}$/\1/p'
}

# Check a digest against the grammar
# Args:
#   $1: digest
# Returns: 0 if it is a usable digest, 1 otherwise
is_valid_deployment_digest() {
    printf '%s' "$1" | grep -qE "$DEPLOYMENT_DIGEST_PATTERN"
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

    require_jq || return 1

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

    # Try cron bucket first (where cron writes digests), fall back to CMS bucket.
    # Record which object answered: the digests are only as trustworthy as their
    # provenance, and a rollback needs to be able to see that provenance.
    local capture_raw=""
    local capture_source="none"
    local capture_object="deployment-metadata/.current_digests_${environment}.json"

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
            capture_raw=$(aws s3 cp "s3://${cron_bucket}/${capture_object}" - 2>/dev/null)
            unset AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION
            [ -n "$capture_raw" ] && capture_source="cron-bucket"
        fi
    fi

    # Fall back to CMS bucket if not found in cron bucket
    if [ -z "$capture_raw" ]; then
        capture_raw=$(aws s3 cp "s3://${BUCKET_NAME}/${capture_object}" - $S3_EXTRA_PARAMS 2>/dev/null)
        [ -n "$capture_raw" ] && capture_source="cms-bucket"
    fi

    # Parse the capture once, here, instead of scraping it at each use site
    local capture_parse=$(deployment_metadata_parse_mode "$capture_raw")
    local capture_json=""
    if [ "$capture_parse" = "json" ] || [ "$capture_parse" = "legacy-escaped" ]; then
        capture_json=$(normalize_json_document "$capture_raw")
    fi

    local capture_containers="{}"
    local captured_at=""
    local capture_env=""
    if [ -n "$capture_json" ]; then
        capture_containers=$(metadata_containers_json "$capture_json")
        captured_at=$(printf '%s' "$capture_json" | jq -r '.captured_at // .timestamp // empty' 2>/dev/null)
        capture_env=$(printf '%s' "$capture_json" | jq -r '.environment // empty' 2>/dev/null)
    fi

    # How old is the state we are about to record as this backup's release?
    local now_epoch=$(date -u +%s)
    local capture_epoch=""
    local capture_age="null"
    local capture_stale=false
    if [ -n "$captured_at" ]; then
        capture_epoch=$(iso8601_to_epoch "$captured_at" 2>/dev/null)
        if [ -n "$capture_epoch" ]; then
            capture_age=$((now_epoch - capture_epoch))
            if [ "$capture_age" -gt "${METADATA_CAPTURE_MAX_AGE_SECONDS:-1800}" ] || [ "$capture_age" -lt 0 ]; then
                capture_stale=true
            fi
        fi
    fi

    # A capture from another space is not this space's release
    local capture_env_match=true
    if [ -n "$capture_env" ] && [ -n "$environment" ] && [ "$capture_env" != "$environment" ]; then
        capture_env_match=false
    fi

    # Get build number from local container's MOTD (if we're inside a container)
    local local_build=""
    local local_app_name=""
    if [ -f "/etc/motd" ]; then
        local_build=$(grep "containertag:" /etc/motd 2>/dev/null | awk '{print $NF}')
        [ "$local_build" = "none" ] && local_build=""
        if [ -n "$VCAP_APPLICATION" ]; then
            local_app_name=$(echo "$VCAP_APPLICATION" | jq -r .application_name 2>/dev/null)
        fi
    fi

    # Get username (circleci or actual user)
    local created_by=$(whoami 2>/dev/null || echo "unknown")
    if [ -n "$CIRCLECI" ]; then
        created_by="circleci"
    fi

    # Every release component is required. Anything else the capture happened to
    # hold is carried through marked as not required, so no information is lost,
    # but it never counts towards release identity.
    local components="${RELEASE_COMPONENTS:-cms www waf}"
    local all_apps="$components"
    local app=""
    for app in $(printf '%s' "$capture_containers" | jq -r 'keys[]' 2>/dev/null); do
        case " $all_apps " in
            *" $app "*) ;;
            *) all_apps="$all_apps $app" ;;
        esac
    done

    local containers_json="{}"
    local missing=""
    local release_tags=""
    for app in $all_apps; do
        [ -n "$app" ] || continue

        local required=false
        case " $components " in
            *" $app "*) required=true ;;
        esac

        local digest=$(printf '%s' "$capture_containers" | jq -r --arg a "$app" '.[$a].digest // empty' 2>/dev/null)

        # Outside a container the live target is authoritative and reachable
        if [ -z "$digest" ] && command -v cf >/dev/null 2>&1; then
            digest=$(get_app_digest "$app" 2>/dev/null || echo "")
        fi

        local valid=false
        if is_valid_deployment_digest "$digest"; then
            valid=true
        fi

        local build=$(digest_release_tag "$digest")
        if [ -z "$build" ] && [ "$local_app_name" = "$app" ]; then
            # We are inside this container, so its own tag is first-hand
            build="$local_build"
        fi

        if [ "$required" = true ]; then
            if [ "$valid" = true ]; then
                [ -n "$build" ] && release_tags="$release_tags $build"
            else
                missing="$missing $app"
            fi
        fi

        containers_json=$(printf '%s' "$containers_json" | jq -c \
            --arg a "$app" \
            --arg d "$digest" \
            --arg b "$build" \
            --argjson v "$valid" \
            --argjson r "$required" \
            '.[$a] = {
                digest: (if $d == "" then null else $d end),
                cci_build: (if $b == "" then null else $b end),
                valid: $v,
                required: $r
            }')
    done

    # One release ID for the whole set: every component must agree on it
    local unique_tags=$(printf '%s' "$release_tags" | tr ' ' '\n' | grep -v '^$' | sort -u)
    local tag_count=$(printf '%s\n' "$unique_tags" | grep -c '[^[:space:]]')
    local release_id=""
    local release_mixed=false
    if [ "$tag_count" -eq 1 ]; then
        release_id="$unique_tags"
    elif [ "$tag_count" -gt 1 ]; then
        release_mixed=true
    fi

    local complete=true
    [ -n "$missing" ] && complete=false

    local missing_json=$(printf '%s' "$missing" | tr ' ' '\n' | grep -v '^$' | jq -R . | jq -sc .)
    local components_json=$(printf '%s' "$components" | tr ' ' '\n' | grep -v '^$' | jq -R . | jq -sc .)

    jq -n \
        --argjson metadata_version "$DEPLOYMENT_METADATA_VERSION" \
        --arg backup_tag "$backup_tag" \
        --arg timestamp "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" \
        --arg environment "$environment" \
        --arg ticket "$ticket" \
        --arg backup_type "$backup_type" \
        --arg git_commit "$git_commit" \
        --arg git_branch "$git_branch" \
        --arg created_by "$created_by" \
        --arg release_id "$release_id" \
        --argjson release_mixed "$release_mixed" \
        --argjson components "$components_json" \
        --argjson containers "$containers_json" \
        --argjson complete "$complete" \
        --argjson missing "$missing_json" \
        --arg capture_source "$capture_source" \
        --arg capture_object "$capture_object" \
        --arg capture_parse "$capture_parse" \
        --arg captured_at "$captured_at" \
        --argjson capture_age "$capture_age" \
        --argjson capture_stale "$capture_stale" \
        --arg capture_env "$capture_env" \
        --argjson capture_env_match "$capture_env_match" \
        '{
            metadata_version: $metadata_version,
            backup_tag: $backup_tag,
            timestamp: $timestamp,
            environment: $environment,
            ticket: $ticket,
            backup_type: $backup_type,
            git_commit: $git_commit,
            git_branch: $git_branch,
            release: {
                id: (if $release_id == "" then null else $release_id end),
                mixed: $release_mixed,
                components: $components
            },
            containers: $containers,
            complete: $complete,
            missing: $missing,
            capture: {
                source: $capture_source,
                object: $capture_object,
                parse: $capture_parse,
                captured_at: (if $captured_at == "" then null else $captured_at end),
                age_seconds: $capture_age,
                stale: $capture_stale,
                environment: (if $capture_env == "" then null else $capture_env end),
                environment_match: $capture_env_match
            },
            created_by: $created_by
        }'
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

# Capture the current deployment state and store it against a backup tag.
# The single entry point for backups: it refuses to upload a document that is
# empty or that does not parse, because a metadata object that cannot be read is
# worse than none at all — rollback trusts whatever it finds under the tag.
# Args:
#   $1: backup_tag
#   $2: environment
# Returns: 0 when a valid document was uploaded, 1 otherwise
commit_deployment_metadata() {
    local backup_tag="$1"
    local environment="$2"
    local metadata=""

    if ! metadata=$(capture_deployment_metadata "$backup_tag" "$environment"); then
        return 1
    fi
    if [ -z "$metadata" ] || ! printf '%s' "$metadata" | jq -e . >/dev/null 2>&1; then
        return 1
    fi

    upload_deployment_metadata "$backup_tag" "$metadata"
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
    require_jq || return 1

    local canonical
    canonical=$(normalize_json_document "$metadata_json") || return 1

    local containers
    containers=$(metadata_containers_json "$canonical")

    # One line per requested app, in the order requested, even when a digest is
    # missing: callers address the output positionally with `sed -n '2p'`, so
    # skipping a line would silently hand them another app's digest.
    local app=""
    local incomplete=false
    for app in $(printf '%s' "$apps" | tr ',' ' '); do
        [ -n "$app" ] || continue

        local digest
        digest=$(printf '%s' "$containers" | jq -r --arg a "$app" '.[$a].digest // empty' 2>/dev/null)

        if is_valid_deployment_digest "$digest"; then
            echo "$digest"
        else
            # Refuse to pass on something that cannot be deployed
            echo ""
            incomplete=true
        fi
    done

    [ "$incomplete" = false ]
}

# Validate a deployment metadata document against the schema contract.
# The single validator: `digests validate` and `rollback` both call this, so
# what the audit command approves is exactly what rollback will accept.
# Args:
#   $1: metadata document (raw text, escaped legacy form tolerated)
#   $2: expected backup tag (optional)
#   $3: expected environment (optional)
# Output: one finding per line, "error <message>" or "warn <message>"
# Returns: 0 when there are no errors, 1 when there are
validate_deployment_metadata() {
    local raw="$1"
    local expect_tag="$2"
    local expect_env="$3"
    local errors=0

    if [ -z "$raw" ]; then
        echo "error metadata document is empty"
        return 1
    fi
    if ! require_jq; then
        echo "error jq is required to validate metadata"
        return 1
    fi

    local canonical
    if ! canonical=$(normalize_json_document "$raw"); then
        echo "error metadata is not valid JSON"
        return 1
    fi

    # A document written by string concatenation only parses because its escapes
    # happened to be expanded; say so, since the next reader may not expand them.
    local parse_mode=$(deployment_metadata_parse_mode "$raw")
    if [ "$parse_mode" = "legacy-escaped" ]; then
        echo "warn metadata is stored in the legacy escaped form, not valid JSON as written"
    fi

    local version=$(printf '%s' "$canonical" | jq -r '.metadata_version // empty')
    local legacy=false
    if [ -z "$version" ]; then
        legacy=true
        echo "warn metadata predates the schema (no metadata_version): release provenance cannot be checked"
    elif [ "$version" -gt "$DEPLOYMENT_METADATA_VERSION" ] 2>/dev/null; then
        echo "warn metadata schema version $version is newer than this tooling understands ($DEPLOYMENT_METADATA_VERSION)"
    fi

    # --- Identity ---------------------------------------------------------
    local doc_tag=$(printf '%s' "$canonical" | jq -r '.backup_tag // empty')
    if [ -z "$doc_tag" ]; then
        echo "error backup_tag is missing"
        errors=$((errors + 1))
    elif [ -n "$expect_tag" ] && [ "$doc_tag" != "$expect_tag" ]; then
        echo "error backup_tag mismatch: metadata says '$doc_tag', expected '$expect_tag'"
        errors=$((errors + 1))
    fi

    local doc_timestamp=$(printf '%s' "$canonical" | jq -r '.timestamp // empty')
    if [ -z "$doc_timestamp" ]; then
        echo "error timestamp is missing"
        errors=$((errors + 1))
    elif ! iso8601_to_epoch "$doc_timestamp" >/dev/null 2>&1; then
        echo "error timestamp is not an ISO-8601 UTC instant: '$doc_timestamp'"
        errors=$((errors + 1))
    fi

    local doc_env=$(printf '%s' "$canonical" | jq -r '.environment // empty')
    if [ -z "$doc_env" ]; then
        echo "error environment is missing"
        errors=$((errors + 1))
    elif [ -n "$expect_env" ] && [ "$doc_env" != "$expect_env" ]; then
        echo "error environment mismatch: metadata is from '$doc_env', target is '$expect_env'"
        errors=$((errors + 1))
    fi

    if [ -z "$(printf '%s' "$canonical" | jq -r '.ticket // empty')" ]; then
        echo "warn ticket is missing (non-critical)"
    fi

    # --- Release components -----------------------------------------------
    local containers=$(metadata_containers_json "$canonical")
    if [ "$(printf '%s' "$containers" | jq -r 'length')" = "0" ]; then
        echo "error containers object is missing or empty"
        errors=$((errors + 1))
    fi

    local components=$(printf '%s' "$canonical" | jq -r '(.release.components // [])[]' 2>/dev/null)
    [ -n "$components" ] || components=$(printf '%s' "${RELEASE_COMPONENTS:-cms www waf}" | tr ' ' '\n')

    local app=""
    for app in $components; do
        [ -n "$app" ] || continue
        local digest=$(printf '%s' "$containers" | jq -r --arg a "$app" '.[$a].digest // empty')
        if [ -z "$digest" ]; then
            echo "error $app digest missing"
            errors=$((errors + 1))
        elif ! is_valid_deployment_digest "$digest"; then
            echo "error $app digest is not a pinned sha256 digest: '$digest'"
            errors=$((errors + 1))
        fi
    done

    # --- Provenance, only recorded from schema v1 onwards -------------------
    if [ "$legacy" = false ]; then
        if [ "$(printf '%s' "$canonical" | jq -r '.complete')" = "false" ]; then
            local missing=$(printf '%s' "$canonical" | jq -r '(.missing // []) | join(", ")')
            echo "error metadata was recorded incomplete, missing: ${missing:-unknown}"
            errors=$((errors + 1))
        fi

        if [ "$(printf '%s' "$canonical" | jq -r '.release.mixed')" = "true" ]; then
            echo "error components come from more than one release, so there is no single release to roll back to"
            errors=$((errors + 1))
        elif [ "$(printf '%s' "$canonical" | jq -r '.release.id // empty')" = "" ]; then
            echo "warn release id could not be established from the digests"
        fi

        local capture_source=$(printf '%s' "$canonical" | jq -r '.capture.source // empty')
        case "$capture_source" in
            none|"")
                echo "error no container digest capture was available when this backup was taken"
                errors=$((errors + 1))
                ;;
        esac

        if [ "$(printf '%s' "$canonical" | jq -r '.capture.parse // empty')" = "unparsable" ]; then
            echo "error the digest capture could not be parsed when this backup was taken"
            errors=$((errors + 1))
        fi

        if [ "$(printf '%s' "$canonical" | jq -r '.capture.environment_match')" = "false" ]; then
            echo "error the digest capture came from environment '$(printf '%s' "$canonical" | jq -r '.capture.environment')', not '$doc_env'"
            errors=$((errors + 1))
        fi

        if [ "$(printf '%s' "$canonical" | jq -r '.capture.stale')" = "true" ]; then
            local age=$(printf '%s' "$canonical" | jq -r '.capture.age_seconds // "unknown"')
            echo "error the digest capture was already ${age}s old at backup time (limit ${METADATA_CAPTURE_MAX_AGE_SECONDS:-1800}s), so these digests may not be what was running"
            errors=$((errors + 1))
        elif [ "$(printf '%s' "$canonical" | jq -r '.capture.age_seconds')" = "null" ]; then
            echo "warn digest capture age is unknown"
        fi
    fi

    [ "$errors" -eq 0 ]
}

# Get container digest for a specific app from Cloud Foundry
# Args:
#   $1: app_name - Name of app (cms, waf, www)
# Returns: Full docker image digest or empty string on error
get_app_digest() {
    local app_name="$1"
    cf app "$app_name" 2>/dev/null | grep 'docker image' | awk '{print $NF}'
}

# Get all app digests (cms, www, waf) from Cloud Foundry in a single batched call
# Returns: Three lines — line 1 = cms, line 2 = www, line 3 = waf
# Callers should parse with: sed -n '1p' (cms), sed -n '2p' (www), sed -n '3p' (waf)
get_all_app_digests() {
    local cms_digest=$(get_app_digest "cms")
    local www_digest=$(get_app_digest "www")
    local waf_digest=$(get_app_digest "waf")
    echo "$cms_digest"
    echo "$www_digest"
    echo "$waf_digest"
}

# Show current container digests captured by cron
# Reads .current_digests_{env}.json from S3 and displays in formatted output
# This shows what digests would be captured if a backup were created right now
#
# NOTE: This function intentionally reads the S3 cron file rather than querying CF directly.
# It executes inside a CMS container (via cf ssh) where CF authentication is not available.
# To query live CF digests from outside a container, use get_app_digest() or get_all_app_digests().
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

    # Parse once through the shared normalizer. The previous version read
    # .timestamp straight out of the raw text with jq while separately expanding
    # escapes for the container list — so for a legacy capture, the one shape
    # this display exists to handle, the timestamp silently came back empty.
    local canonical
    if ! canonical=$(normalize_json_document "$digest_json"); then
        print_status $RED "❌ The capture at $digest_file is not valid JSON and cannot be read"
        return 1
    fi

    local timestamp=$(printf '%s' "$canonical" | jq -r '.captured_at // .timestamp // empty')
    local captured_env=$(printf '%s' "$canonical" | jq -r '.environment // empty')

    if [ -n "$timestamp" ]; then
        echo "Last Updated: $timestamp"
        local capture_epoch=$(iso8601_to_epoch "$timestamp" 2>/dev/null)
        if [ -n "$capture_epoch" ]; then
            local age=$(( $(date -u +%s) - capture_epoch ))
            if [ "$age" -gt "${METADATA_CAPTURE_MAX_AGE_SECONDS:-1800}" ]; then
                print_status $YELLOW "⚠️  Capture is ${age}s old (limit ${METADATA_CAPTURE_MAX_AGE_SECONDS:-1800}s) — a backup taken now would record stale digests"
            else
                echo "Age:          ${age}s"
            fi
        fi
    fi
    if [ -n "$captured_env" ] && [ "$captured_env" != "$env" ]; then
        print_status $YELLOW "⚠️  Capture records environment '$captured_env', not '$env'"
    fi
    if [ "$(printf '%s' "$canonical" | jq -r '.complete // empty')" = "false" ]; then
        print_status $YELLOW "⚠️  Capture is incomplete, missing: $(printf '%s' "$canonical" | jq -r '(.missing // []) | join(", ")')"
    fi
    echo ""

    print_status $GREEN "Container Digests:"
    echo ""

    metadata_containers_json "$canonical" | jq -r 'to_entries[] | "  \(.key): \(.value.digest)"' 2>/dev/null

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

# Normalize a requested backup-type argument into an exact type set
# Accepts comma- and space-separated tokens, deduplicates them, and emits them
# in canonical order. Unknown tokens are rejected so a destructive command can
# never act on a type the operator did not name.
# Args:
#   $1: types_arg - "all", or any combination of static, public, db
# Outputs: normalized comma-separated set (e.g. "static,public,db")
# Returns: 0 on success, 1 if a token is unknown or nothing was requested
normalize_backup_types() {
    local types_arg="${1:-all}"
    local token=""
    local want_static=""
    local want_public=""
    local want_db=""
    local normalized=""

    for token in $(echo "$types_arg" | tr ',' ' '); do
        case "$token" in
            all)
                want_static="yes"
                want_public="yes"
                want_db="yes"
                ;;
            static)
                want_static="yes"
                ;;
            public)
                want_public="yes"
                ;;
            db|database)
                # "database" is accepted as an alias so documented restore
                # options such as --only=database keep working.
                want_db="yes"
                ;;
            *)
                print_status $RED "❌ Error: Unknown backup type: $token" >&2
                echo "   Valid types: static, public, db, all (comma-separated)" >&2
                return 1
                ;;
        esac
    done

    if [ -n "$want_static" ]; then
        normalized="static"
    fi
    if [ -n "$want_public" ]; then
        normalized="${normalized:+$normalized,}public"
    fi
    if [ -n "$want_db" ]; then
        normalized="${normalized:+$normalized,}db"
    fi

    if [ -z "$normalized" ]; then
        print_status $RED "❌ Error: No backup types requested" >&2
        echo "   Valid types: static, public, db, all (comma-separated)" >&2
        return 1
    fi

    echo "$normalized"
    return 0
}

# Exact membership test against a normalized backup-type set
# Unlike has_backup_type(), this never matches a substring, so values such as
# "notstatic" cannot select the static type.
# Args:
#   $1: backup_types - normalized set from normalize_backup_types()
#   $2: check_type   - exact type to look for
# Returns: 0 if present, 1 otherwise
has_exact_backup_type() {
    local backup_types="$1"
    local check_type="$2"
    local token=""

    for token in $(echo "$backup_types" | tr ',' ' '); do
        if [ "$token" = "$check_type" ]; then
            return 0
        fi
    done

    return 1
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
# NIST 800-53: SI-10 - Information Input Validation
# Args:
#   $1: sql_file - Path to SQL file
# Returns: 0 if safe, 1 if dangerous patterns found
validate_sql_content() {
    local sql_file="$1"

    if [ ! -f "$sql_file" ]; then
        handle_error "SQL file not found: $sql_file" "validation" "return"
    fi

    # Check for dangerous SQL statements that could be used for exploitation.
    # Anchor to a statement boundary so words such as "system" in serialized
    # Drupal data within INSERT values do not reject an otherwise valid dump.
    local dangerous_patterns='^[[:space:]]*(SELECT[[:space:]].*INTO[[:space:]]+(OUTFILE|DUMPFILE)|LOAD[[:space:]]+(DATA|FILE)|SYSTEM[[:space:]]|EXEC[[:space:]]|GRANT[[:space:]]+ALL|CREATE[[:space:]]+USER)'

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

# Find the next unused sequence number for a backup stem.
#
# Args:
#   $1: backup_type - static, public, or db
#   $2: stem - the full name the number is appended to, suffix included
#             (e.g. "AUTO-dr-16277-2026-08-05-post-deploy"), NOT just the base
#   $3: legacy_stem - optional pre-normalization stem carrying an extra delimiter,
#             counted so numbering does not restart across the change
# Sets: NEXT_BACKUP_SUFFIX
# Returns: 0 on success, 1 if no number is available
get_next_backup_suffix() {
    local backup_type="$1"
    local stem="$2"
    local legacy_stem="${3:-}"

    setup_s3_vars || return 1

    local lockfile="/tmp/backup-suffix-${backup_type}.lock"
    local lockfd=200
    local lock_waited=0

    eval "exec $lockfd>$lockfile"
    if ! command -v flock >/dev/null 2>&1; then
        print_status $YELLOW "⚠️  flock is unavailable, proceeding without backup suffix lock" >&2
        eval "exec $lockfd>&-"
    else
        # BusyBox flock does not support util-linux's -w timeout option.
        while ! flock -x -n $lockfd 2>/dev/null; do
            if [ $lock_waited -ge $FLOCK_TIMEOUT_SECONDS ]; then
                print_status $YELLOW "⚠️  Could not acquire backup suffix lock within ${FLOCK_TIMEOUT_SECONDS} seconds, proceeding without lock" >&2
                eval "exec $lockfd>&-"
                break
            fi
            sleep 1
            lock_waited=$((lock_waited + 1))
        done
    fi

    local s3_path=""

    case "$backup_type" in
        "static") s3_path="$AUTO_STATIC_BACKUP_PATH" ;;
        "public") s3_path="$AUTO_PUBLIC_BACKUP_PATH" ;;
        "db")     s3_path="$AUTO_DB_BACKUP_PATH" ;;
        *)
            eval "exec $lockfd>&-"
            return 1
            ;;
    esac

    # Names are compared against the full stem, suffix included. The previous
    # version searched for "<base>-<number>" and required the remainder to be
    # purely numeric, so an existing "<base>--post-deploy-0" never counted: every
    # retry of a named backup recomputed 0 and overwrote the earlier one. Confirmed
    # live — three downsyncs into dr on 2026-08-05 each produced
    # "DOWNSYNC-dr-16277-2026-08-05--pre-downsync-0" and only the last survived.
    local names=""
    if [ "$backup_type" = "db" ]; then
        names=$(aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS 2>/dev/null \
            | awk '{print $4}' | sed 's/\.sql\.gz$//')
    else
        names=$(aws s3 ls "s3://$BUCKET_NAME/$s3_path/" $S3_EXTRA_PARAMS 2>/dev/null \
            | grep 'PRE ' | awk '{print $2}' | tr -d '/')
    fi

    # Dots would otherwise act as wildcards and over-match neighbouring stems.
    local stem_re=$(printf '%s' "$stem" | sed 's/\./\\./g')
    local existing_numbers=""
    existing_numbers=$(printf '%s\n' "$names" | sed -n "s|^${stem_re}-\([0-9][0-9]*\)$|\1|p")

    # Objects written before suffixes were normalized carry an extra delimiter
    # ("<base>--post-deploy-0"). They are counted so numbering stays monotonic
    # across the change rather than restarting and sitting beside the old names.
    if [ -n "$legacy_stem" ]; then
        local legacy_re=$(printf '%s' "$legacy_stem" | sed 's/\./\\./g')
        local legacy_numbers=""
        legacy_numbers=$(printf '%s\n' "$names" | sed -n "s|^${legacy_re}-\([0-9][0-9]*\)$|\1|p")
        existing_numbers=$(printf '%s\n%s\n' "$existing_numbers" "$legacy_numbers")
    fi

    local max_num=-1
    local num=""
    for num in $existing_numbers; do
        if [ "$num" -gt "$max_num" ]; then
            max_num=$num
        fi
    done

    local result=$((max_num + 1))

    # Previously this reset to 0 at the cap, which overwrote the very first backup
    # of the day. Refusing is the only safe answer: the caller must not be handed a
    # number that already has objects under it.
    if [ "$result" -ge "$MAX_RETRY_ATTEMPTS" ]; then
        print_status $RED "❌ Error: backup sequence for $stem reached the limit ($MAX_RETRY_ATTEMPTS)" >&2
        print_status $YELLOW "   Highest existing suffix: $max_num. Remove old backups or raise MAX_RETRY_ATTEMPTS." >&2
        eval "exec $lockfd>&-"
        return 1
    fi

    # The descriptor remains locked until this process exits or allocates another type.
    # That keeps the selected suffix reserved while its backup uploads to S3.
    NEXT_BACKUP_SUFFIX="$result"
}

# Choose one sequence number for every component of a backup set.
#
# Each component used to allocate its own number, so a set could come out as
# static-3, public-1, db-1 and no longer be addressable by a single tag — leaving
# the restore's same-day pairing to guess which pieces belong together. Taking the
# highest free number across the requested types keeps one tag for the set.
#
# Cross-instance atomicity comes from the backup lock (H-05), which is held for the
# whole backup command: no second run can allocate between this call and the last
# component upload.
# Args:
#   $1: stem - normalized stem, suffix included
#   $2: legacy_stem - pre-normalization stem, or empty
#   $3: types - requested backup types
# Sets: NEXT_BACKUP_SUFFIX
# Returns: 0 on success, 1 if any type could not be allocated
allocate_backup_set_suffix() {
    local stem="$1"
    local legacy_stem="$2"
    local types="$3"

    local best=0
    local type=""
    for type in static public db; do
        has_backup_type "$types" "$type" || continue
        if ! get_next_backup_suffix "$type" "$stem" "$legacy_stem"; then
            return 1
        fi
        if [ "$NEXT_BACKUP_SUFFIX" -gt "$best" ]; then
            best="$NEXT_BACKUP_SUFFIX"
        fi
    done

    NEXT_BACKUP_SUFFIX="$best"
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

# State captured before a backup or restore changes Drupal. These values are
# process-local because the manager prepares and restores state in one run.
# NIST 800-53: CP-10 - Information System Recovery and Reconstitution
SAVED_MAINTENANCE_MODE=""
SAVED_TOME_DISABLED=""
DRUPAL_STATE_CAPTURED=false

# Which halves of the state are currently held away from their captured values.
# A trap handler needs to know what is still outstanding, and callers that
# prepare one half must not have the other restored out from under them.
DRUPAL_STATE_ACTIVE_MAINT=false
DRUPAL_STATE_ACTIVE_TOME=false

# Sticky for the life of the process: set when any restoration fails or cannot be
# verified. Twenty call sites in manager.sh invoke restore_drupal_state as
# `[ "$prepared" = "true" ] && restore_drupal_state ...` and discard the status,
# so the return value alone cannot surface a failure. run_backup_command checks
# this flag before reporting success, which makes the failure impossible to lose
# without editing every call site.
# NIST 800-53: CP-10, AU-3
DRUPAL_STATE_RESTORE_FAILED=false

# ===================================================================
# CROSS-INSTANCE BACKUP LOCK
# ===================================================================
#
# Production deploys two CMS instances and each one installs the same daily cron
# entry, so both can dump the database, change Drupal state, and write the same S3
# keys at the same time — overwriting objects, checksums and metadata. The existing
# flock and rate-limit files live in each container's /tmp and cannot see across
# instances.
#
# The lock is an S3 object created with `--if-none-match '*'`, which fails with
# PreconditionFailed if the key already exists. That check happens at the bucket,
# so it is atomic between instances. Verified against the `dr` bucket in GovCloud:
# a second writer is rejected and the first writer's content is left intact.
#
# NIST 800-53: CP-9, SC-5
BACKUP_LOCK_OWNED=false
BACKUP_LOCK_TOKEN=""

# A value unique to this process, used to prove ownership before releasing. CF
# supplies a per-instance GUID; the PID and a random suffix separate concurrent
# runs inside one container.
_backup_lock_token() {
    local rand=""
    rand=$(head -c 6 /dev/urandom 2>/dev/null | od -An -tx1 2>/dev/null | tr -d ' \n')
    [ -n "$rand" ] || rand="$$"
    printf '%s' "${CF_INSTANCE_GUID:-nocf}-${CF_INSTANCE_INDEX:-x}-$$-$rand"
}

# Read a field out of a lock object's body.
# Args: $1 - body text, $2 - field name
_backup_lock_field() {
    printf '%s\n' "$1" | grep "^$2=" | head -1 | sed "s/^$2=//"
}

# Acquire the backup lock.
#
# Returns 0 when the lock is held by this process, 1 when another live holder has
# it, and 2 on an error that leaves ownership unknown. A caller that gets 1 should
# skip its run: on a multi-instance app that is the expected outcome for every
# instance but one, not a failure.
backup_lock_acquire() {
    local lock_path="${BACKUP_LOCK_PATH:-backup-locks/backup.lock}"
    local ttl="${BACKUP_LOCK_TTL_SECONDS:-7200}"
    local now_epoch=$(date -u '+%s')

    if [ -z "$BUCKET_NAME" ]; then
        print_status $RED "❌ Cannot acquire the backup lock: bucket is not configured"
        return 2
    fi

    BACKUP_LOCK_TOKEN=$(_backup_lock_token)

    local body="/tmp/backup-lock-$$.txt"
    {
        echo "token=$BACKUP_LOCK_TOKEN"
        echo "acquired_epoch=$now_epoch"
        echo "expires_epoch=$((now_epoch + ttl))"
        echo "instance_index=${CF_INSTANCE_INDEX:-unknown}"
        echo "space=${APP_SPACE:-unknown}"
        echo "pid=$$"
    } > "$body"

    local attempt=0
    while [ "$attempt" -lt 2 ]; do
        attempt=$((attempt + 1))

        local put_error="/tmp/backup-lock-err-$$.txt"
        if aws s3api put-object --bucket "$BUCKET_NAME" --key "$lock_path" \
                --if-none-match '*' --body "$body" $S3_EXTRA_PARAMS >/dev/null 2>"$put_error"; then
            rm -f "$body" "$put_error"
            BACKUP_LOCK_OWNED=true
            audit_log "backup_lock_acquired" "info" "Backup lock acquired" \
                "lock_path=\"$lock_path\" instance_index=\"${CF_INSTANCE_INDEX:-unknown}\""
            return 0
        fi

        # An unrecognized option means this CLI cannot express the precondition at
        # all, so no backup would ever run. That is a very different problem from
        # losing a race, and it is worth naming rather than leaving the operator to
        # infer it from a generic message.
        if grep -qiE 'unknown options|invalid choice|unrecognized arguments|argument --if-none-match' "$put_error" 2>/dev/null; then
            rm -f "$body"
            print_status $RED "❌ This aws CLI does not support --if-none-match, so the backup lock cannot be taken"
            print_status $YELLOW "   Refusing to continue: without it, concurrent backups cannot be excluded."
            print_status $YELLOW "   CLI reported: $(tr -d '\n' < "$put_error" | cut -c1-160)"
            audit_log "backup_lock_error" "error" "aws CLI lacks conditional write support" \
                "lock_path=\"$lock_path\""
            rm -f "$put_error"
            return 2
        fi
        rm -f "$put_error"

        # Someone holds it, or the conditional write is unsupported. Distinguish the
        # two by reading the object: a missing object after a failed create means the
        # precondition was not the reason, and proceeding would defeat the lock.
        local existing=""
        existing=$(aws s3 cp "s3://$BUCKET_NAME/$lock_path" - $S3_EXTRA_PARAMS 2>/dev/null)
        if [ -z "$existing" ]; then
            rm -f "$body"
            print_status $RED "❌ Could not acquire the backup lock and no lock object is present"
            print_status $YELLOW "   Refusing to continue: concurrent backups cannot be ruled out."
            audit_log "backup_lock_error" "error" "Lock acquisition failed with no lock present" \
                "lock_path=\"$lock_path\""
            return 2
        fi

        local expires=$(_backup_lock_field "$existing" expires_epoch)
        local holder=$(_backup_lock_field "$existing" instance_index)
        local held_since=$(_backup_lock_field "$existing" acquired_epoch)

        # An unparseable expiry is treated as live, never as stale: stealing on a
        # bad read is what would allow two concurrent backups.
        if ! echo "$expires" | grep -qE '^[0-9]+$'; then
            rm -f "$body"
            print_status $YELLOW "⏭️  Backup skipped: lock held by instance ${holder:-unknown} (expiry unreadable)"
            return 1
        fi

        if [ "$now_epoch" -lt "$expires" ]; then
            rm -f "$body"
            print_status $YELLOW "⏭️  Backup skipped: another backup is running (instance ${holder:-unknown}, since epoch ${held_since:-unknown})"
            audit_log "backup_lock_contended" "info" "Backup skipped, lock held elsewhere" \
                "lock_path=\"$lock_path\" holder_instance=\"${holder:-unknown}\""
            return 1
        fi

        # Expired: the holder died without releasing. Remove it and retry once.
        print_status $YELLOW "⚠️  Removing an expired backup lock (instance ${holder:-unknown}, expired at epoch $expires)"
        audit_log "backup_lock_expired" "warning" "Removed an expired backup lock" \
            "lock_path=\"$lock_path\" holder_instance=\"${holder:-unknown}\" expires_epoch=\"$expires\""
        aws s3 rm "s3://$BUCKET_NAME/$lock_path" $S3_EXTRA_PARAMS >/dev/null 2>&1
    done

    rm -f "$body"
    print_status $YELLOW "⏭️  Backup skipped: could not take the lock after removing an expired one"
    return 1
}

# Release the backup lock, but only if this process still owns it.
#
# Ownership is re-checked against the object because an expired lock may have been
# taken over by another run; deleting it then would release someone else's lock.
backup_lock_release() {
    [ "$BACKUP_LOCK_OWNED" = "true" ] || return 0

    local lock_path="${BACKUP_LOCK_PATH:-backup-locks/backup.lock}"
    BACKUP_LOCK_OWNED=false

    local existing=""
    existing=$(aws s3 cp "s3://$BUCKET_NAME/$lock_path" - $S3_EXTRA_PARAMS 2>/dev/null)
    local token=$(_backup_lock_field "$existing" token)

    if [ -n "$token" ] && [ "$token" != "$BACKUP_LOCK_TOKEN" ]; then
        print_status $YELLOW "⚠️  Not releasing the backup lock: it is now held by another run"
        audit_log "backup_lock_release_skipped" "warning" "Lock taken over by another run" \
            "lock_path=\"$lock_path\""
        return 0
    fi

    if aws s3 rm "s3://$BUCKET_NAME/$lock_path" $S3_EXTRA_PARAMS >/dev/null 2>&1; then
        audit_log "backup_lock_released" "info" "Backup lock released" "lock_path=\"$lock_path\""
        return 0
    fi

    print_status $YELLOW "⚠️  Could not remove the backup lock at $lock_path"
    print_status $YELLOW "   Later backups will take it over after ${BACKUP_LOCK_TTL_SECONDS:-7200}s."
    audit_log "backup_lock_release_failed" "error" "Could not remove the backup lock" \
        "lock_path=\"$lock_path\""
    return 1
}

# Install a cleanup handler for normal exit and for signals.
#
# A POSIX signal trap returns control to the point where the signal arrived, so a
# handler that only cleans up lets the operation carry on afterwards — with its
# state already restored. Confirmed live in `dr`: sending TERM to a running backup
# cleared maintenance mode and the backup then continued, which is the opposite of
# what maintenance mode is for. Signal handlers therefore terminate with the
# conventional 128+signal status; the EXIT handler only cleans up.
#
# Handlers must be idempotent, because the explicit exit below re-triggers EXIT.
# Args:
#   $1: handler - name of the cleanup function
arm_cleanup_traps() {
    local handler="$1"

    trap "$handler" EXIT
    trap "$handler; exit 130" INT
    trap "$handler; exit 143" TERM
    trap "$handler; exit 129" HUP
}

# Set maintenance mode and confirm the value persisted.
#
# A Drupal state write can report success without taking effect. The previous
# code read the value back only to print it, never comparing it to the target, so
# a silent no-op was indistinguishable from a successful change.
# Args:
#   $1: target - 0 or 1
# Returns: 0 if the value is confirmed set, 1 otherwise
_apply_maintenance_mode() {
    local target="$1"

    if ! drush sset system.maintenance_mode "$target" 2>/dev/null; then
        return 1
    fi
    # Cached pages keep serving until the rebuild lands, so a failed rebuild
    # means the mode is not actually in force.
    if ! drush cr 2>/dev/null; then
        return 1
    fi

    local actual=$(drush sget system.maintenance_mode 2>/dev/null)
    [ "$actual" = "1" ] || actual="0"
    [ "$actual" = "$target" ]
}

# Set the Tome disable flag and confirm the value persisted.
#
# The enabled case deletes the key rather than writing 0, so an absent value and
# a literal 0 both mean "enabled". The comparison normalizes the same way
# capture_drupal_state does; without that, restoring a captured "0" would read
# back as empty and look like a failure.
# Args:
#   $1: target - 0 or 1
# Returns: 0 if the value is confirmed set, 1 otherwise
_apply_tome_disabled() {
    local target="$1"

    if [ "$target" = "1" ]; then
        drush sset usagov.tome_run_disabled 1 2>/dev/null || return 1
    else
        drush sdel usagov.tome_run_disabled 2>/dev/null || return 1
    fi

    local actual=$(drush sget usagov.tome_run_disabled 2>/dev/null)
    case "$actual" in
        ""|0|null|NULL) actual="0" ;;
        *) actual="1" ;;
    esac
    [ "$actual" = "$target" ]
}

capture_drupal_state() {
    if [ "$DRUPAL_STATE_CAPTURED" = "true" ]; then
        return 0
    fi

    SAVED_MAINTENANCE_MODE=$(drush sget system.maintenance_mode 2>/dev/null || true)
    [ "$SAVED_MAINTENANCE_MODE" = "1" ] || SAVED_MAINTENANCE_MODE="0"

    SAVED_TOME_DISABLED=$(drush sget usagov.tome_run_disabled 2>/dev/null || true)
    case "$SAVED_TOME_DISABLED" in
        ""|0|null|NULL) SAVED_TOME_DISABLED="0" ;;
        *) SAVED_TOME_DISABLED="1" ;;
    esac

    DRUPAL_STATE_CAPTURED=true
    audit_log "drupal_state_captured" "info" "Captured Drupal state" "maintenance_mode=\"$SAVED_MAINTENANCE_MODE\" tome_disabled=\"$SAVED_TOME_DISABLED\""
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

    capture_drupal_state

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
            if ! _apply_tome_disabled 1; then
                print_status $RED "❌ Failed to disable Tome"
                audit_log "tome_disable" "failure" "Failed to disable Tome"
                return 1
            fi
            DRUPAL_STATE_ACTIVE_TOME=true

            print_status $GREEN "✅ Tome disabled: 1"
            audit_log "tome_disable" "success" "Tome disabled successfully" "tome_disabled_state=\"1\""
            ;;

        "maintenance")
            # Enable maintenance mode only
            print_status $YELLOW "🚧 Enabling maintenance mode..."
            audit_log "maintenance_mode_enable" "started" "Enabling maintenance mode"
            if _apply_maintenance_mode 1; then
                DRUPAL_STATE_ACTIVE_MAINT=true
                print_status $GREEN "✅ Maintenance mode enabled: 1"
                audit_log "maintenance_mode_enable" "success" "Maintenance mode enabled" "maint_mode_state=\"1\""
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
            if ! _apply_tome_disabled 1; then
                print_status $RED "❌ Failed to disable Tome"
                audit_log "tome_disable" "failure" "Failed to disable Tome"
                return 1
            fi
            DRUPAL_STATE_ACTIVE_TOME=true

            print_status $GREEN "✅ Tome disabled: 1"
            audit_log "tome_disable" "success" "Tome disabled successfully" "tome_disabled_state=\"1\""

            print_status $YELLOW "🚧 Enabling maintenance mode..."
            audit_log "maintenance_mode_enable" "started" "Enabling maintenance mode"
            if _apply_maintenance_mode 1; then
                DRUPAL_STATE_ACTIVE_MAINT=true
                print_status $GREEN "✅ Maintenance mode enabled: 1"
                audit_log "maintenance_mode_enable" "success" "Maintenance mode enabled" "maint_mode_state=\"1\""
            else
                print_status $RED "❌ Failed to enable maintenance mode"
                audit_log "maintenance_mode_enable" "failure" "Failed to enable maintenance mode, rolling back Tome disable"
                restore_drupal_state "tome"
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
    local target_maintenance_mode="0"
    local target_tome_disabled="0"

    # Validate state_type
    if [ "$state_type" != "tome" ] && [ "$state_type" != "maintenance" ] && [ "$state_type" != "both" ]; then
        print_status $RED "Error: Invalid state_type. Must be 'tome', 'maintenance', or 'both'"
        return 1
    fi

    if [ "$DRUPAL_STATE_CAPTURED" = "true" ]; then
        target_maintenance_mode="$SAVED_MAINTENANCE_MODE"
        target_tome_disabled="$SAVED_TOME_DISABLED"
    fi

    # Both halves are attempted and their results aggregated. The previous "both"
    # branch logged a maintenance failure and carried on, then returned the Tome
    # result — so a site left in maintenance mode reported success. Conversely a
    # Tome failure returned immediately, skipping nothing but also never recording
    # that maintenance had been dealt with.
    local failures=0

    case "$state_type" in
        "tome"|"both")
            if [ "$state_type" = "both" ]; then
                if _restore_maintenance_half "$target_maintenance_mode"; then
                    DRUPAL_STATE_ACTIVE_MAINT=false
                else
                    failures=$((failures + 1))
                fi
            fi

            if _restore_tome_half "$target_tome_disabled"; then
                DRUPAL_STATE_ACTIVE_TOME=false
            else
                failures=$((failures + 1))
            fi
            ;;

        "maintenance")
            if _restore_maintenance_half "$target_maintenance_mode"; then
                DRUPAL_STATE_ACTIVE_MAINT=false
            else
                failures=$((failures + 1))
            fi
            ;;
    esac

    if [ "$failures" -gt 0 ]; then
        # Sticky, because most callers discard this return value.
        DRUPAL_STATE_RESTORE_FAILED=true
        print_status $RED "❌ Drupal state was not fully restored ($failures of the requested changes failed)"
        print_status $YELLOW "   The site may still be in maintenance mode or have Tome disabled."
        print_status $YELLOW "   Check with: drush sget system.maintenance_mode; drush sget usagov.tome_run_disabled"
        audit_log "drupal_state_restore" "failure" "Drupal state not fully restored" \
            "state_type=\"$state_type\" failures=\"$failures\" target_maintenance_mode=\"$target_maintenance_mode\" target_tome_disabled=\"$target_tome_disabled\""
        return 1
    fi

    return 0
}

# Restore one half of the state, verifying the value landed.
# Args:
#   $1: target - 0 or 1
# Returns: 0 if confirmed restored, 1 otherwise
_restore_maintenance_half() {
    local target="$1"

    print_status $YELLOW "🚧 Restoring maintenance mode to: $target..."
    if _apply_maintenance_mode "$target"; then
        print_status $GREEN "✅ Maintenance mode restored: $target"
        audit_log "maintenance_mode_restore" "success" "Maintenance mode restored" "maint_mode_state=\"$target\""
        return 0
    fi

    print_status $RED "❌ Failed to restore maintenance mode"
    audit_log "maintenance_mode_restore" "failure" "Failed to restore maintenance mode" "maint_mode_target=\"$target\""
    return 1
}

# Restore the Tome flag, verifying the value landed. Success was previously logged
# unconditionally, without reading the key back at all.
# Args:
#   $1: target - 0 or 1
# Returns: 0 if confirmed restored, 1 otherwise
_restore_tome_half() {
    local target="$1"

    if [ "$target" = "1" ]; then
        print_status $YELLOW "🔒 Restoring Tome to disabled..."
    else
        print_status $YELLOW "🔓 Re-enabling Tome..."
    fi

    if _apply_tome_disabled "$target"; then
        print_status $GREEN "✅ Tome state restored: tome_run_disabled=$target"
        audit_log "tome_state_restore" "success" "Tome state restored" "tome_disabled_state=\"$target\""
        return 0
    fi

    print_status $RED "❌ Failed to restore Tome state"
    audit_log "tome_state_restore" "failure" "Failed to restore Tome state" "tome_disabled_target=\"$target\""
    return 1
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

# List a backup namespace through the structured S3 API
# `aws s3 ls` exits non-zero both when a prefix holds no objects and when the
# request fails, so a caller cannot tell an empty inventory from an unreadable
# one. list-objects-v2 succeeds with no results for an empty prefix and fails
# only on a real error, so destructive callers can fail closed on a bad listing
# instead of treating it as "nothing to do".
# Args:
#   $1: mode - "prefixes" for immediate child names, "keys" for every object key
#   $2: base_path - S3 key prefix without bucket or trailing slash
# Outputs: one backup name (prefixes) or object key (keys) per line
# Returns: 0 if the namespace was read successfully, non-zero if the request failed
s3_list_backup_namespace() {
    local mode="$1"
    local base_path="$2"
    local output=""
    local status=0
    local item=""

    case "$mode" in
        prefixes)
            output=$(aws s3api list-objects-v2 --bucket "$BUCKET_NAME" --prefix "$base_path/" \
                --delimiter / --query 'CommonPrefixes[].Prefix' --output text $S3_EXTRA_PARAMS)
            status=$?
            ;;
        keys)
            output=$(aws s3api list-objects-v2 --bucket "$BUCKET_NAME" --prefix "$base_path/" \
                --query 'Contents[].Key' --output text $S3_EXTRA_PARAMS)
            status=$?
            ;;
        *)
            print_status $RED "❌ Internal error: unknown S3 list mode: $mode" >&2
            return 2
            ;;
    esac

    if [ "$status" -ne 0 ]; then
        return "$status"
    fi

    # `--output text` returns tab-separated values, and the literal None when the
    # query matched nothing. The trailing newline matters: `read` discards an
    # unterminated final line, which would silently drop the last backup.
    printf '%s\n' "$output" | tr '\t' '\n' | while IFS= read -r item; do
        [ -n "$item" ] || continue
        [ "$item" = "None" ] && continue

        if [ "$mode" = "prefixes" ]; then
            item=${item#"$base_path/"}
            item=${item%/}
            [ -n "$item" ] || continue
        fi

        printf '%s\n' "$item"
    done

    return 0
}

# Count the objects under an S3 prefix, failing instead of reporting zero
# A `--query 'length(Contents)'` count cannot be used here: the AWS CLI applies
# --query once per page, so a prefix holding more than 1000 objects reports 1000
# (verified: a 5091-object prefix reported "1000"). Any guard built on that value
# passes regardless of reality, so count the keys the listing actually returned.
# Args:
#   $1: base_path - S3 key prefix without bucket or trailing slash
# Outputs: object count on stdout (only when the listing succeeded)
# Returns: 0 if counted, non-zero if the listing failed
s3_count_objects() {
    local base_path="$1"
    local listing=""
    local status=0

    listing=$(s3_list_backup_namespace keys "$base_path")
    status=$?
    if [ "$status" -ne 0 ]; then
        return "$status"
    fi

    printf '%s\n' "$listing" | grep -c '.'
    return 0
}
