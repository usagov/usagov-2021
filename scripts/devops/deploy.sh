#!/bin/sh

# Deployment Helper Script
# Simplified commands for deployment workflows integrated with backup system
# Usage: deploy.sh <command> [options]

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/../common.sh"

# Initialize backup system
init_backup_system

show_usage() {
    echo "Deployment Helper - Simplified deployment workflows"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Setup Commands:"
    echo "  set-context <env> <ticket> [pre] [post]  Set deployment context (creates env vars)"
    echo "                                        Example: deploy.sh set-context prod USAGOV-1234"
    echo "                                        Optional: deploy.sh set-context prod USAGOV-1234 pre-deploy post-deploy"
    echo "                                        Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX"
    echo ""
    echo "  show-context                          Show current deployment context"
    echo ""
    echo "Status Commands:"
    echo "  last-backup                           Show when last backup of each type was taken"
    echo "  status                                Show CF target and recent activity"
    echo "  motd                                  Show message of the day from CMS container"
    echo "  ccb [from] [to]                       Show tickets between two commits/branches"
    echo "                                        Default: from=prod, to=stage"
    echo "                                        Uses DEPLOY_ENV if set (e.g., stage vs prod)"
    echo ""
    echo "Container/Build Information:"
    echo "  show-build-info <env>                 Show latest build info from annotated git tags"
    echo "                                        Displays CCI build number and container digests"
    echo "                                        for CMS, WAF, and WWW"
    echo ""
    echo "Deployment Commands (DESTRUCTIVE):"
    echo "  deploy app <name> <build> <digest> [--force]"
    echo "                                        🔥 DESTRUCTIVE: Deploy specific app with container digest"
    echo "                                        Example: deploy app cms 5936 @sha256:abc..."
    echo "                                        Use --force to skip space validation"
    echo ""
    echo "Deployment Backup Commands:"
    echo "  pre-deploy [--force]                  Create pre-deployment backup using DEPLOY_PRE_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --force to skip)"
    echo "  post-deploy [--force]                 Create post-deployment backup using DEPLOY_POST_SUFFIX"
    echo "                                        Automatically creates annotated git tag for deployment tracking"
    echo "                                        Requires: DEPLOY_TICKET, DEPLOY_ENV"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --force to skip)"
    echo ""
    echo "Rollback Commands (DESTRUCTIVE):"
    echo "  list-backups [days]                   List recent backups for rollback (default: 7 days)"
    echo "  rollback [types] <tag> [--force]      🔥 DESTRUCTIVE: Rollback code (always) + optional data types"
    echo "                                        Types: db, static, public, full, or comma-separated"
    echo "                                        Default: code only (no data restore)"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --force to skip)"
    echo "                                        Example: rollback AUTO-prod-cf-123-2025-12-22-0"
    echo "                                        Example: rollback db AUTO-prod-cf-123-2025-12-22-0"
    echo "                                        Example: rollback full AUTO-prod-cf-123-2025-12-22-0"
    echo "  rollback-static [tag] [--force]       🔥 DESTRUCTIVE: Restore static site only (with confirmation)"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_STATIC_TAG is set"
    echo "  rollback-db [tag] [--force]           🔥 DESTRUCTIVE: Restore database only (with confirmation)"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_DB_TAG is set"
    echo ""
    echo "Quick Backup Commands:"
    echo "  snapshot [suffix]                     Quick backup with auto-generated tag"
    echo "  snapshot-db [suffix]                  Quick database-only backup"
    echo ""
    echo "Validation Commands:"
    echo "  validate [--only=app1,app2] [--commit=sha] [--skip-http]"
    echo "                                        Validate deployment was successful"
    echo "                                        Default: validates all apps (cms, www)"
    echo "                                        Options:"
    echo "                                          --only=cms,www    Validate specific apps only"
    echo "                                          --commit=<sha>    Expected commit (default: HEAD)"
    echo "                                          --skip-http       Skip HTTP endpoint checks"
    echo ""
    echo "Environment Management:"
    echo "  switch <env>                          Switch cf target to environment"
    echo ""
    echo "Example Workflow:"
    echo "  # Set up deployment context"
    echo "  \$0 set-context prod USAGOV-1234"
    echo "  "
    echo "  # Before deployment"
    echo "  \$0 pre-deploy"
    echo "  "
    echo "  # Check when last backups were taken"
    echo "  \$0 last-backup"
    echo "  "
    echo "  # After deployment, create backup"
    echo "  \$0 post-deploy"
    echo "  "
    echo "  # If needed, rollback"
    echo "  $0 list-backups 1"
    echo "  $0 rollback AUTO-prod-14855-2025-12-08-0"
    echo ""
}

# Set deployment context (stores in shell variables for session)
set_context() {
    local env="$1"
    local ticket="$2"
    local pre_suffix="${3:-pre-deploy}"
    local post_suffix="${4:-post-deploy}"

    if [ -z "$env" ] || [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Environment and ticket required"
        echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix]"
        exit 1
    fi

    print_status $BLUE "🔍 Capturing most recent backup tags for rollback..."

    # Query S3 to get the most recent valid backup tag for each type
    local backup_tags=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars && \
        echo 'STATIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_STATIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'PUBLIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_PUBLIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'DB:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep '\.sql\.gz$' | sort -r | head -1 | awk '{print \$4}' | sed 's/\.sql\.gz$//'")

    local static_tag=$(echo "$backup_tags" | grep -A1 "^STATIC:" | tail -1)
    local public_tag=$(echo "$backup_tags" | grep -A1 "^PUBLIC:" | tail -1)
    local db_tag=$(echo "$backup_tags" | grep -A1 "^DB:" | tail -1)

    # Export variables for this session
    export DEPLOY_ENV="$env"
    export DEPLOY_TICKET="$ticket"
    export DEPLOY_PRE_SUFFIX="$pre_suffix"
    export DEPLOY_POST_SUFFIX="$post_suffix"
    export DEPLOY_ROLLBACK_STATIC_TAG="$static_tag"
    export DEPLOY_ROLLBACK_PUBLIC_TAG="$public_tag"
    export DEPLOY_ROLLBACK_DB_TAG="$db_tag"

    print_status $GREEN "✅ Deployment context set"
    echo ""
    echo "Environment variables:"
    echo "  DEPLOY_ENV=$DEPLOY_ENV"
    echo "  DEPLOY_TICKET=$DEPLOY_TICKET"
    echo "  DEPLOY_PRE_SUFFIX=$DEPLOY_PRE_SUFFIX"
    echo "  DEPLOY_POST_SUFFIX=$DEPLOY_POST_SUFFIX"
    echo ""
    echo "Captured backup tags for rollback:"
    echo "  DEPLOY_ROLLBACK_STATIC_TAG=$DEPLOY_ROLLBACK_STATIC_TAG"
    echo "  DEPLOY_ROLLBACK_PUBLIC_TAG=$DEPLOY_ROLLBACK_PUBLIC_TAG"
    echo "  DEPLOY_ROLLBACK_DB_TAG=$DEPLOY_ROLLBACK_DB_TAG"
    echo ""
    print_status $YELLOW "💡 To use these in your current shell, run:"
    echo ""
    echo "export DEPLOY_ENV='$env'"
    echo "export DEPLOY_TICKET='$ticket'"
    echo "export DEPLOY_POST_SUFFIX='$post_suffix'"
    echo "export DEPLOY_PRE_SUFFIX='$pre_suffix'"
    echo "export DEPLOY_ROLLBACK_STATIC_TAG='$static_tag'"
    echo "export DEPLOY_ROLLBACK_PUBLIC_TAG='$public_tag'"
    echo "export DEPLOY_ROLLBACK_DB_TAG='$db_tag'"
}

# Show current deployment context
show_context() {
    print_status $BLUE "📋 Current Deployment Context"
    echo ""
    if [ -n "$DEPLOY_ENV" ] || [ -n "$DEPLOY_TICKET" ] || [ -n "$DEPLOY_PRE_SUFFIX" ] || [ -n "$DEPLOY_POST_SUFFIX" ]; then
        echo "  DEPLOY_ENV=${DEPLOY_ENV:-(not set)}"
        echo "  DEPLOY_TICKET=${DEPLOY_TICKET:-(not set)}"
        echo "  DEPLOY_PRE_SUFFIX=${DEPLOY_PRE_SUFFIX:-(not set)}"
        echo "  DEPLOY_POST_SUFFIX=${DEPLOY_POST_SUFFIX:-(not set)}"
        echo ""
        echo "Rollback tags:"
        echo "  DEPLOY_ROLLBACK_STATIC_TAG=${DEPLOY_ROLLBACK_STATIC_TAG:-(not set)}"
        echo "  DEPLOY_ROLLBACK_PUBLIC_TAG=${DEPLOY_ROLLBACK_PUBLIC_TAG:-(not set)}"
        echo "  DEPLOY_ROLLBACK_DB_TAG=${DEPLOY_ROLLBACK_DB_TAG:-(not set)}"
    else
        print_status $YELLOW "⚠️  No deployment context set"
        echo ""
        echo "Run: deploy.sh set-context <env> <ticket>"
    fi
    echo ""
}

# Show when last backup of each type was taken
last_backup() {
    print_status $BLUE "🕒 Last Backup Times"
    echo ""

    cf ssh cms -c 'cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars &&
    echo "Static Site Backups:"
    latest_static=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -n1)
    if [ -n "$latest_static" ]; then
        tag=$(echo "$latest_static" | awk "{print \$2}" | tr -d "/")
        echo "  Latest: $tag"
        date_part=$(echo "$tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
        if [ -n "$date_part" ]; then
            echo "  Date: $date_part"
        fi
    else
        echo "  No backups found"
    fi
    echo ""

    echo "Public Files Backups:"
    latest_public=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -n1)
    if [ -n "$latest_public" ]; then
        tag=$(echo "$latest_public" | awk "{print \$2}" | tr -d "/")
        echo "  Latest: $tag"
        date_part=$(echo "$tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
        if [ -n "$date_part" ]; then
            echo "  Date: $date_part"
        fi
    else
        echo "  No backups found"
    fi
    echo ""

    echo "Database Backups:"
    latest_db=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | sort -r | head -n1)
    if [ -n "$latest_db" ]; then
        tag=$(echo "$latest_db" | awk "{print \$4}" | sed "s/\.sql\.gz$//")
        echo "  Latest: $tag"
        date_part=$(echo "$tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
        if [ -n "$date_part" ]; then
            echo "  Date: $date_part"
        fi
    else
        echo "  No backups found"
    fi
    '
}

# Show current status
show_status() {
    print_status $BLUE "📊 Current Status"
    echo ""
    echo "CF Target:"
    cf target
    echo ""
    echo "Recent Activity (last 10 events):"
    cf events cms | head -15
}

# Show message of the day from CMS container
show_motd() {
    cf ssh cms -c "cat /etc/motd"
}

# ===================================================================
# HELPER FUNCTIONS
# ===================================================================

# Validate CF target matches DEPLOY_ENV (safety check for destructive operations)
# Args:
#   $1: force - If "force" or "--force", skip validation
# Returns: 0 if valid, exits if mismatch
validate_target_space() {
    local force="$1"

    # Skip validation if force flag provided
    if [ "$force" = "force" ] || [ "$force" = "--force" ]; then
        print_status $YELLOW "⚠️  --force flag used, skipping space validation"
        return 0
    fi

# Get current CF space
    local current_space=$(cf target | grep "space:" | awk '{print $2}')

    if [ -z "$current_space" ]; then
        print_status $RED "❌ Could not determine current CF space"
        exit 1
    fi

    # Require DEPLOY_ENV to be set for safety
    if [ -z "$DEPLOY_ENV" ]; then
        print_status $RED "❌ DEPLOY_ENV NOT SET"
        echo ""
        echo "  Current CF space: $current_space"
        echo "  DEPLOY_ENV:       (not set)"
        echo ""
        print_status $YELLOW "This is a safety check to ensure you have deployment context set."
        echo ""
        echo "To proceed, either:"
        echo "  1. Set context: deploy.sh set-context $current_space <ticket>"
        echo "  2. Use --force flag to skip validation (NOT RECOMMENDED)"
        echo ""
        exit 1
    fi

    # Compare with DEPLOY_ENV
    if [ "$current_space" != "$DEPLOY_ENV" ]; then
        print_status $RED "❌ SPACE MISMATCH DETECTED"
        echo ""
        echo "  Current CF space: $current_space"
        echo "  DEPLOY_ENV:       $DEPLOY_ENV"
        echo ""
        print_status $YELLOW "This is a safety check to prevent deploying/rolling back to the wrong environment."
        echo ""
        echo "To proceed anyway, either:"
        echo "  1. Switch spaces: deploy.sh switch $DEPLOY_ENV"
        echo "  2. Update context: deploy.sh set-context $current_space <ticket>"
        echo "  3. Use --force flag (NOT RECOMMENDED)"
        echo ""
        exit 1
    fi

    print_status $GREEN "✅ Space validation passed: $current_space"
    return 0
}

# Execute a backup command via cf ssh to cms container
# Args:
#   $1: ticket - Ticket number
#   $2: suffix - Backup suffix
#   $3: types - Backup types (all, db, etc.) - default: all
exec_backup_command() {
    local ticket="$1"
    local suffix="$2"
    local types="${3:-all}"
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup $types $ticket $suffix"
}

# Execute a restore command via cf ssh to cms container
# Args:
#   $1: tag - Backup tag
#   $2: only_flag - Optional --only=type flag
exec_restore_command() {
    local tag="$1"
    local only_flag="${2:-}"
    if [ -n "$only_flag" ]; then
        cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag $only_flag"
    else
        cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag"
    fi
}

# Prompt for rollback confirmation
# Args:
#   $1: rollback_type - Description of what's being rolled back
#   $2: tag - Backup tag
# Returns: 0 if confirmed, exits if cancelled
confirm_rollback() {
    local rollback_type="$1"
    local tag="$2"

    print_status $YELLOW "⚠️  ROLLBACK: This will restore $rollback_type"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""
    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        exit 0
    fi
    echo ""
}

# Create a deployment backup (pre or post)
# Args:
#   $1: backup_type - "pre" or "post" deployment
#   $2: default_suffix - Default suffix if not set in context
create_deployment_backup() {
    local backup_type="$1"
    local default_suffix="$2"
    local ticket="${DEPLOY_TICKET:-}"
    local suffix_var="DEPLOY_${backup_type}_SUFFIX"
    local suffix="${!suffix_var:-$default_suffix}"

    if [ -z "$ticket" ]; then
        print_status $RED "❌ Error: DEPLOY_TICKET not set"
        echo "Run: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $BLUE "📦 Creating ${backup_type}-deployment backup"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    exec_backup_command "$ticket" "$suffix" "all"
}

# Pre-deployment backup using context variables
pre_deploy() {
    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$1"

    create_deployment_backup "PRE" "pre-deploy"
}

# Post-deployment backup using context variables
# Now automatically creates annotated git tags for deployment tracking
post_deploy() {
    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$1"

    create_deployment_backup "POST" "post-deploy"

    # Automatically create git tag for this deployment
    print_status $BLUE "🏷️ Capturing deployment metadata for git tag..."

    # Get current environment
    local env="${DEPLOY_ENV:-}"
    if [ -z "$env" ]; then
        log_message "⚠️ DEPLOY_ENV not set, skipping git tag creation"
        return 0
    fi

    # Query CF for current container digests using helper function
    local digests=$(get_all_app_digests)
    local cms_digest=$(echo "$digests" | sed -n '1p')
    local waf_digest=$(echo "$digests" | sed -n '2p')
    local www_digest=$(echo "$digests" | sed -n '3p')

    # Extract CCI build number from digest (format: {registry}/{org}/usagov_{app}:{cci_build}@sha256:...)
    local cci_build=""
    if [ -n "$cms_digest" ]; then
        cci_build=$(echo "$cms_digest" | sed 's/.*usagov_cms:\([0-9]*\)@.*/\1/')
    fi

    if [ -z "$cci_build" ] || [ -z "$cms_digest" ] || [ -z "$waf_digest" ] || [ -z "$www_digest" ]; then
        log_message "⚠️ Could not extract deployment info, skipping git tag creation"
        return 0
    fi

    # Create and push git tag
    create_deployment_tag "$env" "$cci_build" "$cms_digest" "$waf_digest" "$www_digest"
}

# Quick snapshot with auto-generated suffix
snapshot() {
    local suffix="${1:-$(date +%H%M)}"

    print_status $BLUE "📸 Creating snapshot"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    exec_backup_command "SNAPSHOT" "$suffix" "all"
}

# Quick database snapshot
snapshot_db() {
    local suffix="${1:-$(date +%H%M)}"

    print_status $BLUE "💾 Creating database snapshot"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    exec_backup_command "SNAPSHOT" "$suffix" "db"
}

# List backups for rollback
list_backups() {
    local days="${1:-7}"

    print_status $BLUE "📋 Available backups (last $days days)"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh list all $days"
}

# Rollback single data type only (with confirmation)
# Args:
#   $1: type - Type to restore (static, db, public)
#   $2: tag - Backup tag (optional if DEPLOY_ROLLBACK_{TYPE}_TAG is set)
#   $3: force - Optional "force" flag to skip validation
rollback_single_type() {
    local type="$1"
    local tag="${2:-}"
    local force="$3"

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$force"

    # Map type to context variable if tag not provided
    if [ -z "$tag" ]; then
        case "$type" in
            static) tag="$DEPLOY_ROLLBACK_STATIC_TAG" ;;
            db) tag="$DEPLOY_ROLLBACK_DB_TAG" ;;
            public) tag="$DEPLOY_ROLLBACK_PUBLIC_TAG" ;;
        esac
    fi

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-$type <tag>"
        echo "Or set deployment context first: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    # Use confirmation helper
    confirm_rollback "$type only" "$tag"

    exec_restore_command "$tag" "--only=$type"
}

# Rollback static site only (with confirmation)
rollback_static() {
    rollback_single_type "static" "$1" "$2"
}

# Rollback database only (with confirmation)
rollback_db() {
    rollback_single_type "db" "$1" "$2"
}

# Switch CF target to specified environment
switch_env() {
    local env="$1"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Environment required"
        echo "Usage: deploy.sh switch <env>"
        exit 1
    fi

    print_status $BLUE "🔄 Switching to $env environment..."
    cf target -s "$env"
}

# Show what tickets/commits are between two refs (branches, commits, tags)
show_changes() {
    local from="${1:-prod}"
    local to="${2:-stage}"

    # Fetch latest from remote to ensure we have current refs
    print_status $BLUE "🔄 Fetching latest changes..."
    git fetch --all

    # Validate that both refs exist
    if ! git cat-file -t "$from" > /dev/null 2>&1; then
        print_status $RED "❌ Error: '$from' not found in this repo"
        exit 1
    fi
    if ! git cat-file -t "$to" > /dev/null 2>&1; then
        print_status $RED "❌ Error: '$to' not found in this repo"
        exit 1
    fi

    # Find the common ancestor (merge base) to handle non-linear history
    local merge_base
    merge_base=$(git merge-base "$from" "$to" 2>/dev/null)

    if [ -z "$merge_base" ]; then
        print_status $RED "❌ Error: No common ancestor found between $from and $to"
        exit 1
    fi

    # Check if refs are the same
    local from_sha
    local to_sha
    from_sha=$(git rev-parse "$from")
    to_sha=$(git rev-parse "$to")

    if [ "$from_sha" = "$to_sha" ]; then
        print_status $YELLOW "ℹ️  $from and $to point to the same commit"
        return 0
    fi

    print_status $BLUE "📋 Changes from $from to $to"
    echo ""

    # Show commits in 'to' that are not in 'from'
    # Using --first-parent to follow main branch history and avoid seeing every merged commit
    local commits_ahead
    commits_ahead=$(git log --first-parent --oneline "$from..$to" 2>/dev/null)

    if [ -z "$commits_ahead" ]; then
        print_status $YELLOW "ℹ️  No new commits in $to (may be behind $from)"

        # Check if 'from' is ahead instead
        local commits_behind
        commits_behind=$(git log --first-parent --oneline "$to..$from" 2>/dev/null)
        if [ -n "$commits_behind" ]; then
            print_status $YELLOW "⚠️  Warning: $to is behind $from by $(echo "$commits_behind" | wc -l | tr -d ' ') commits"
        fi
        return 0
    fi

    # Extract tickets from commit messages
    # Accept: "Usa 123", "usa_123", "USAGOV-123", etc.
    # Pattern matches: hyphen, underscore, space, or tab
    local tickets
    tickets=$(git log --first-parent "$from..$to" | \
        grep -Eio 'usa(gov)?[-_[:space:]]([0-9]+)' | \
        sed -E 's/usa(gov)?[-_[:space:]]([0-9]+)/USAGOV-\2/ig' | \
        grep -iv usagov-2021 | \
        sort -u)

    if [ -n "$tickets" ]; then
        echo "Tickets:"
        echo "$tickets" | while read -r ticket; do
            echo "  • $ticket"
        done
        echo ""
    fi

    # Show commit count
    local commit_count
    commit_count=$(echo "$commits_ahead" | wc -l | tr -d ' ')
    echo "Total commits: $commit_count"
    echo ""

    # Show recent commits (last 10)
    echo "Recent commits:"
    echo "$commits_ahead" | head -10 | while read -r line; do
        echo "  $line"
    done

    if [ "$commit_count" -gt 10 ]; then
        echo "  ... and $((commit_count - 10)) more"
    fi
}

# Show latest build information from git annotated tags
show_build_info() {
    local env="${1:-prod}"
    env=$(echo "$env" | tr '[:upper:]' '[:lower:]')

    print_status $BLUE "🔍 Searching for latest build information for: $env"
    echo ""

    # Find the most recent annotated tag for this environment
    local annotated_tag
    annotated_tag=$(git for-each-ref refs/tags/usagov-cci-build-*-${env} --sort='-*authordate' \
        --format '%(objecttype) %(refname:short)' | \
        while read ty name; do [ "$ty" = "tag" ] && echo "$name" && break; done)

    if [ -z "$annotated_tag" ]; then
        print_status $RED "❌ No git tag found matching pattern: usagov-cci-build-*-${env}"
        echo ""
        echo "Available tags:"
        git tag -l "usagov-cci-build-*" | tail -10
        return 1
    fi

    print_status $GREEN "✅ Found tag: $annotated_tag"
    echo ""

    # Parse the tag annotation
    local tag_content
    tag_content=$(git for-each-ref refs/tags/$annotated_tag --format "%(contents)" | sed "s/'//g")

    if [ -z "$tag_content" ]; then
        print_status $RED "❌ Tag annotation is empty"
        return 1
    fi

    # Parse the fields
    local cci_build=""
    local cms_digest=""
    local waf_digest=""
    local www_digest=""

    IFS='|' read -ra fields <<< "$tag_content"
    for field in "${fields[@]}"; do
        case "$field" in
            CCI_BUILD=*)
                cci_build="${field#CCI_BUILD=}"
                ;;
            CMS_DIGEST=*)
                cms_digest="${field#CMS_DIGEST=}"
                ;;
            WAF_DIGEST=*)
                waf_digest="${field#WAF_DIGEST=}"
                ;;
            WWW_DIGEST=*)
                www_digest="${field#WWW_DIGEST=}"
                ;;
        esac
    done

    # Display the information
    print_status $BLUE "📦 Build Information"
    echo "----------------------------------------"
    echo "Environment:    $env"
    echo "Tag:            $annotated_tag"
    echo "CCI Build:      $cci_build"
    echo ""
    echo "Container Digests:"
    echo "  CMS:          $cms_digest"
    echo "  WAF:          $waf_digest"
    echo "  WWW:          $www_digest"
    echo ""

    # Generate deployment commands
    print_status $BLUE "🚀 Deployment Commands"
    echo "----------------------------------------"
    echo ""
    echo "To deploy CMS:"
    echo "  deploy.sh deploy app cms $cci_build $cms_digest"
    echo ""
    echo "To deploy WAF:"
    echo "  deploy.sh deploy app waf $cci_build $waf_digest"
    echo ""
    echo "To deploy WWW:"
    echo "  deploy.sh deploy app www $cci_build $www_digest"
    echo ""

    # Optionally set these as environment variables if DEPLOY_ENV matches
    if [ -n "$DEPLOY_ENV" ] && [ "$DEPLOY_ENV" = "$env" ]; then
        export DEPLOY_CCI_BUILD="$cci_build"
        export DEPLOY_CMS_DIGEST="$cms_digest"
        export DEPLOY_WAF_DIGEST="$waf_digest"
        export DEPLOY_WWW_DIGEST="$www_digest"

        print_status $GREEN "✅ Build info exported to environment variables"
        echo "  DEPLOY_CCI_BUILD=$DEPLOY_CCI_BUILD"
        echo "  DEPLOY_CMS_DIGEST=$DEPLOY_CMS_DIGEST"
        echo "  DEPLOY_WAF_DIGEST=$DEPLOY_WAF_DIGEST"
        echo "  DEPLOY_WWW_DIGEST=$DEPLOY_WWW_DIGEST"
        echo ""
    fi
}

# Create and push annotated git tag for deployment tracking
# Args:
#   $1: env - Environment name
#   $2: cci_build - CircleCI build number
#   $3: cms_digest - CMS container digest
#   $4: waf_digest - WAF container digest
#   $5: www_digest - WWW container digest
create_deployment_tag() {
    local env="$1"
    local cci_build="$2"
    local cms_digest="$3"
    local waf_digest="$4"
    local www_digest="$5"

    if [ -z "$env" ] || [ -z "$cci_build" ] || [ -z "$cms_digest" ] || [ -z "$waf_digest" ] || [ -z "$www_digest" ]; then
        log_message "⚠️ Missing parameters for git tag creation, skipping"
        return 0
    fi

    # Check if we're in a git repository
    if ! git rev-parse --git-dir >/dev/null 2>&1; then
        log_message "⚠️ Not in a git repository, skipping tag creation"
        return 0
    fi

    local tag_name="usagov-cci-build-${cci_build}-${env}"
    local tag_msg="CCI_BUILD=${cci_build}|CMS_DIGEST=${cms_digest}|WAF_DIGEST=${waf_digest}|WWW_DIGEST=${www_digest}"

    print_status $BLUE "📌 Creating git tag: $tag_name"

    # Delete local tag if exists (force recreate)
    git tag -d "$tag_name" 2>/dev/null

    # Create annotated tag
    if ! git tag -a -m "$tag_msg" "$tag_name"; then
        log_message "⚠️ Failed to create git tag (non-critical)"
        return 0
    fi

    # Push to remote (OVERWRITES if exists)
    if git push origin "$tag_name" --force 2>&1; then
        print_status $GREEN "✅ Git tag pushed to origin: $tag_name"
    else
        log_message "⚠️ Failed to push git tag (non-critical)"
    fi

    return 0
}

# ===================================================================
# DEPLOYMENT HELPER FUNCTIONS
# ===================================================================

# Deploy a single app with container digest
# Args:
#   $1: app_name - App to deploy (cms, www, waf)
#   $2: env - Environment (dev, stage, prod)
#   $3: cci_build - CircleCI build number
#   $4: digest - Container digest
_deploy_app() {
    local app_name="$1"
    local env="$2"
    local cci_build="$3"
    local digest="$4"

    print_status $BLUE "🚀 Deploying $app_name to $env"
    echo "  Build: $cci_build"
    echo "  Digest: $digest"

    # Determine instance count based on environment
    local instances=1
    case "$env" in
        prod) instances=2 ;;
        stage) instances=1 ;;
        dev) instances=1 ;;
    esac

    # Check if app already exists
    local app_exists=false
    if cf app "$app_name" >/dev/null 2>&1; then
        app_exists=true
    fi

    # Prepare push command
    local push_opts="-i $instances --strategy rolling"
    if [ "$app_exists" = "false" ]; then
        push_opts="$push_opts --no-route"
        print_status $YELLOW "⚠️ App doesn't exist yet, will push with --no-route"
    fi

    # Push the container
    # Digest is expected to be full image path: registry/org/image:tag@sha256:...
    print_status $BLUE "Pushing container..."
    if [ -n "$DOCKERHUB_ACCESS_TOKEN" ]; then
        export CF_DOCKER_PASSWORD="$DOCKERHUB_ACCESS_TOKEN"
        cf push "$app_name" $push_opts --docker-image "$digest" --docker-username "${DOCKERHUB_USERNAME:-gsatts}"
        local exit_code=$?
        unset CF_DOCKER_PASSWORD
    else
        cf push "$app_name" $push_opts --docker-image "$digest"
        local exit_code=$?
    fi

    if [ $exit_code -ne 0 ]; then
        print_status $RED "❌ Failed to deploy $app_name"
        return 1
    fi

    print_status $GREEN "✅ $app_name deployed successfully"
    return 0
}

# Deploy command - deploy app with explicit parameters
deploy_app() {
    local app_name="$1"
    local cci_build="$2"
    local digest="$3"
    local force="$4"

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$force"

    if [ -z "$app_name" ] || [ -z "$cci_build" ] || [ -z "$digest" ]; then
        print_status $RED "❌ Error: Missing required parameters"
        echo "Usage: deploy.sh deploy app <app-name> <cci-build> <digest>"
        echo "Example: deploy.sh deploy app cms 5936 @sha256:abc123..."
        return 1
    fi

    # Use DEPLOY_ENV or current CF target
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Could not determine environment"
        echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
        return 1
    fi

    _deploy_app "$app_name" "$env" "$cci_build" "$digest"
}

# Rollback command - redeploy containers from backup metadata
rollback() {
    local data_types=""
    local backup_tag=""
    local force=""

    # Parse: [types] [tag] [--force]
    # Check for --force flag in any position
    for arg in "$@"; do
        if [ "$arg" = "--force" ]; then
            force="force"
        fi
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$force"

    # Parse: [types] [tag]
    # If first arg looks like a data type, it's types; otherwise it's the tag
    case "$1" in
        db|static|public|full|*,*)
            data_types="$1"
            backup_tag="$2"
            ;;
        *)
            # No type = code only (default)
            backup_tag="$1"
            ;;
    esac

    if [ -z "$backup_tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback [db|static|public|full] <backup-tag>"
        echo "       deploy.sh rollback <backup-tag>  (defaults to code only)"
        echo ""
        echo "Examples:"
        echo "  deploy.sh rollback AUTO-prod-cf-abc123-2025-12-22-0          # Code only"
        echo "  deploy.sh rollback db AUTO-prod-cf-abc123-2025-12-22-0       # Code + DB"
        echo "  deploy.sh rollback full AUTO-prod-cf-abc123-2025-12-22-0     # Code + All data"
        return 1
    fi

    print_status $BLUE "🔍 Loading deployment metadata for: $backup_tag"

    # Download and parse metadata JSON from S3
    local metadata=$(fetch_deployment_metadata "$backup_tag")
    if [ -z "$metadata" ]; then
        print_status $RED "❌ No metadata found for backup: $backup_tag"
        echo "This backup may have been created before metadata capture was implemented."
        echo "Or the backup tag may not exist."
        return 1
    fi

    # Parse container digests from metadata (using grep/sed since jq may not be available)
    local cms_digest=$(echo "$metadata" | grep -A2 '"cms":' | grep '"digest":' | sed 's/.*"digest": "\([^"]*\)".*/\1/')
    local www_digest=$(echo "$metadata" | grep -A2 '"www":' | grep '"digest":' | sed 's/.*"digest": "\([^"]*\)".*/\1/')
    local waf_digest=$(echo "$metadata" | grep -A2 '"waf":' | grep '"digest":' | sed 's/.*"digest": "\([^"]*\)".*/\1/')
    local cci_build=$(echo "$metadata" | grep -A2 '"cms":' | grep '"cci_build":' | sed 's/.*"cci_build": "\([^"]*\)".*/\1/')

    if [ -z "$cms_digest" ] || [ -z "$www_digest" ] || [ -z "$waf_digest" ]; then
        print_status $RED "❌ Failed to parse container digests from metadata"
        return 1
    fi

    # Determine environment
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Could not determine environment"
        echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
        return 1
    fi

    # Show what will be rolled back
    print_status $YELLOW "⚠️  CODE ROLLBACK"
    echo "Will redeploy containers from backup: $backup_tag"
    echo "Environment: $env"
    echo ""
    echo "  CMS: $cms_digest"
    echo "  WWW: $www_digest"
    echo "  WAF: $waf_digest"
    echo "  Build: $cci_build"
    echo ""

    if [ -n "$data_types" ]; then
        echo "Will also restore data: $data_types"
        echo ""
    fi

    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        return 0
    fi

    # STEP 1: Rollback code (always)
    print_status $BLUE "🚀 Redeploying containers..."
    echo ""

    if ! _deploy_app "cms" "$env" "$cci_build" "$cms_digest"; then
        print_status $RED "❌ Failed to deploy CMS"
        return 1
    fi

    if ! _deploy_app "www" "$env" "$cci_build" "$www_digest"; then
        print_status $RED "❌ Failed to deploy WWW"
        return 1
    fi

    if ! _deploy_app "waf" "$env" "$cci_build" "$waf_digest"; then
        print_status $RED "❌ Failed to deploy WAF"
        return 1
    fi

    print_status $GREEN "✅ Code rollback complete"

    # STEP 2: Data rollback (if requested and not code-only)
    if [ -n "$data_types" ]; then
        echo ""
        print_status $BLUE "📦 Restoring data..."

        case "$data_types" in
            full)
                # Restore all data types using backup system
                exec_restore_command "$backup_tag"
                ;;
            *)
                # Restore specific types (db, static, public, or comma-separated)
                exec_restore_command "$backup_tag" "--only=$data_types"
                ;;
        esac

        print_status $GREEN "✅ Data restore complete"
    fi

    echo ""
    print_status $GREEN "✅ Rollback complete!"
}

# Validate deployment
validate_deployment() {
    local only_apps=""
    local expected_commit=""
    local skip_http=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --only=*)
                only_apps="${1#*=}"
                shift
                ;;
            --commit=*)
                expected_commit="${1#*=}"
                shift
                ;;
            --skip-http)
                skip_http=true
                shift
                ;;
            *)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: validate [--only=app1,app2] [--commit=sha] [--skip-http]"
                return 1
                ;;
        esac
    done

    # Default to current HEAD if no commit specified
    if [ -z "$expected_commit" ]; then
        expected_commit=$(git rev-parse HEAD 2>/dev/null)
        if [ -z "$expected_commit" ]; then
            print_status $YELLOW "⚠️  Could not determine current commit (not in git repo)"
            expected_commit="unknown"
        fi
    fi

    # Determine which apps to validate
    local apps_to_validate
    if [ -n "$only_apps" ]; then
        apps_to_validate="$only_apps"
    else
        # Default: validate both cms and www
        apps_to_validate="cms,www"
    fi

    print_status $BLUE "🔍 Validating deployment..."
    echo "Expected commit: ${expected_commit:0:8}"
    echo "Apps to validate: $apps_to_validate"
    echo ""

    local overall_success=true
    local IFS=','
    for app in $apps_to_validate; do
        [ -z "$app" ] && continue

        print_status $BLUE "📦 Validating app: $app"
        echo "----------------------------------------"

        # Check if app exists and is running
        local app_info
        app_info=$(cf app "$app" 2>&1)

        if [ $? -ne 0 ]; then
            print_status $RED "❌ App '$app' not found or not accessible"
            overall_success=false
            echo ""
            continue
        fi

        # Check app state
        local app_state
        app_state=$(echo "$app_info" | grep "^requested state:" | awk '{print $3}')

        if [ "$app_state" != "started" ]; then
            print_status $RED "❌ App is not started (state: $app_state)"
            overall_success=false
        else
            print_status $GREEN "✅ App is started"
        fi

        # Check instances
        local instances_info
        instances_info=$(echo "$app_info" | grep "^instances:")

        if echo "$instances_info" | grep -q "running"; then
            local running_count
            running_count=$(echo "$instances_info" | grep -o "running" | wc -l | tr -d ' ')
            print_status $GREEN "✅ $running_count instance(s) running"
        else
            print_status $RED "❌ No running instances"
            overall_success=false
        fi

        # Check deployed commit SHA
        local deployed_commit
        deployed_commit=$(cf ssh "$app" -c "cd /var/www && git rev-parse HEAD 2>/dev/null || echo 'unknown'" 2>/dev/null | tail -1 | tr -d '\r')

        if [ "$deployed_commit" = "unknown" ] || [ -z "$deployed_commit" ]; then
            print_status $YELLOW "⚠️  Could not determine deployed commit (git not available in container)"
        elif [ "$expected_commit" = "unknown" ]; then
            print_status $YELLOW "⚠️  Deployed commit: ${deployed_commit:0:8} (cannot verify - expected commit unknown)"
        elif [ "$deployed_commit" = "$expected_commit" ]; then
            print_status $GREEN "✅ Deployed commit matches expected: ${deployed_commit:0:8}"
        else
            print_status $RED "❌ Commit mismatch!"
            echo "   Expected: ${expected_commit:0:8}"
            echo "   Deployed: ${deployed_commit:0:8}"
            overall_success=false
        fi

        # HTTP endpoint check (if not skipped)
        if [ "$skip_http" = false ]; then
            local app_url
            app_url=$(cf app "$app" | grep "^routes:" | awk '{print $2}' | head -1)

            if [ -n "$app_url" ]; then
                local http_status
                http_status=$(curl -s -o /dev/null -w "%{http_code}" -L "https://$app_url" --max-time 10 2>/dev/null)

                if [ "$http_status" = "200" ]; then
                    print_status $GREEN "✅ HTTP endpoint responding (200)"
                elif [ -n "$http_status" ]; then
                    print_status $YELLOW "⚠️  HTTP endpoint returned: $http_status"
                else
                    print_status $RED "❌ HTTP endpoint not responding"
                    overall_success=false
                fi
            else
                print_status $YELLOW "⚠️  Could not determine app URL for HTTP check"
            fi
        fi

        echo ""
    done

    # Check if pre-deploy backups were created (if context is set)
    if [ -n "$DEPLOY_ROLLBACK_STATIC_TAG" ] || [ -n "$DEPLOY_ROLLBACK_PUBLIC_TAG" ] || [ -n "$DEPLOY_ROLLBACK_DB_TAG" ]; then
        print_status $BLUE "📦 Checking pre-deploy backups..."
        echo "----------------------------------------"

        local backup_check_failed=false

        if [ -n "$DEPLOY_ROLLBACK_STATIC_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info static "$DEPLOY_ROLLBACK_STATIC_TAG" >/dev/null 2>&1; then
                print_status $GREEN "✅ Static backup exists: $DEPLOY_ROLLBACK_STATIC_TAG"
            else
                print_status $RED "❌ Static backup not found: $DEPLOY_ROLLBACK_STATIC_TAG"
                backup_check_failed=true
            fi
        fi

        if [ -n "$DEPLOY_ROLLBACK_PUBLIC_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info public "$DEPLOY_ROLLBACK_PUBLIC_TAG" >/dev/null 2>&1; then
                print_status $GREEN "✅ Public backup exists: $DEPLOY_ROLLBACK_PUBLIC_TAG"
            else
                print_status $RED "❌ Public backup not found: $DEPLOY_ROLLBACK_PUBLIC_TAG"
                backup_check_failed=true
            fi
        fi

        if [ -n "$DEPLOY_ROLLBACK_DB_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info db "$DEPLOY_ROLLBACK_DB_TAG" >/dev/null 2>&1; then
                print_status $GREEN "✅ Database backup exists: $DEPLOY_ROLLBACK_DB_TAG"
            else
                print_status $RED "❌ Database backup not found: $DEPLOY_ROLLBACK_DB_TAG"
                backup_check_failed=true
            fi
        fi

        if [ "$backup_check_failed" = true ]; then
            overall_success=false
        fi

        echo ""
    fi

    # Final summary
    if [ "$overall_success" = true ]; then
        print_status $GREEN "✅ Deployment validation PASSED"
        return 0
    else
        print_status $RED "❌ Deployment validation FAILED"
        return 1
    fi
}

# Main command dispatcher
COMMAND="${1:-}"

case "$COMMAND" in
    "set-context")
        shift
        set_context "$@"
        ;;
    "show-context")
        show_context
        ;;
    "last-backup")
        last_backup
        ;;
    "status")
        show_status
        ;;
    "motd")
        show_motd
        ;;
    "ccb")
        shift
        show_changes "$@"
        ;;
    "show-build-info")
        shift
        show_build_info "$@"
        ;;
    "deploy")
        shift
        DEPLOY_SUBCOMMAND="${1:-}"
        shift
        case "$DEPLOY_SUBCOMMAND" in
            "app")
                deploy_app "$@"
                ;;
            *)
                print_status $RED "❌ Unknown deploy subcommand: $DEPLOY_SUBCOMMAND"
                echo "Usage: deploy.sh deploy app <app-name> <cci-build> <digest>"
                exit 1
                ;;
        esac
        ;;
    "pre-deploy")
        shift
        pre_deploy "$@"
        ;;
    "post-deploy")
        shift
        post_deploy "$@"
        ;;
    "list-backups")
        shift
        list_backups "$@"
        ;;
    "rollback")
        shift
        rollback "$@"
        ;;
    "rollback-static")
        shift
        rollback_static "$@"
        ;;
    "rollback-db")
        shift
        rollback_db "$@"
        ;;
    "snapshot")
        shift
        snapshot "$@"
        ;;
    "snapshot-db")
        shift
        snapshot_db "$@"
        ;;
    "switch")
        shift
        switch_env "$@"
        ;;
    "validate")
        shift
        validate_deployment "$@"
        ;;
    "help"|"--help"|"-h"|"")
        show_usage
        ;;
    *)
        print_status $RED "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac
