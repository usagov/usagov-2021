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
    echo "  push <name> <build> [digest] [--force]"
    echo "                                        🔥 DESTRUCTIVE: Deploy specific app with container digest"
    echo "                                        Example: push cms 5936 gsatts/usagov-2021@sha256:abc..."
    echo "                                        Digest optional if DEPLOY_{APP}_DIGEST set or git tag exists"
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
    echo "  download-backups [tag]                 Download latest backups locally (db/static/public)"
    echo "                                        Default tag: newest backup in current CF space"
    echo ""
    echo "Rollback Commands (DESTRUCTIVE):"
    echo "  list-backups [days]                   List recent backups for rollback (default: 7 days)"
    echo "  digests [env] [days] [limit]          Show available container digests with deployment history"
    echo "                                        env: current (default), dev, stage, prod, or all"
    echo "                                        days: show backups from last N days (default: 7)"
    echo "                                        limit: limit results per category (default: 10)"
    echo "                                        Flags: --backups-only, --git-only, --format=json,"
    echo "                                               --show-all-history (show deployments >1yr old)"
    echo "  rollback [types] <cms> <www> <waf> [tag] [--force]"
    echo "                                        🔥 DESTRUCTIVE: Rollback code (always) + optional data types"
    echo "                                        Requires: Full container digests for cms, www, and waf"
    echo "                                        Types: db, static, public, full, or comma-separated"
    echo "                                        Default: code only (no data restore)"
    echo "                                        Backup tag required if restoring data"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --force to skip)"
    echo "                                        Example: rollback gsatts/usagov-2021@sha256:abc... gsatts/usagov-2021@sha256:def... gsatts/usagov-2021@sha256:ghi..."
    echo "                                        Example: rollback db gsatts/usagov-2021@sha256:abc... gsatts/usagov-2021@sha256:def... gsatts/usagov-2021@sha256:ghi... AUTO-prod-2025-12-22-0"
    echo "  rollback-static [tag] [--force]       🔥 DESTRUCTIVE: Restore static site only (with confirmation)"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_STATIC_TAG is set"
    echo "  rollback-db [tag] [--force]           🔥 DESTRUCTIVE: Restore database only (with confirmation)"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_DB_TAG is set"
    echo ""
    echo "Downsync Commands (DESTRUCTIVE):"
    echo "  downsync <from> <to> [tag]            🔥 DESTRUCTIVE: Copy data from one space to another"
    echo "                                        Example: downsync prod dev"
    echo "                                        Copies database and public files from FROM space to TO space"
    echo "                                        Uses latest backup from FROM space if tag not specified"
    echo "                                        Automatically fixes MFA configuration after restore"
    echo ""
    echo "Tome Utilities:"
    echo "  tome-log                              Tail the latest Tome log and stop when it finishes"
    echo "  tome-disable [max_wait_mins]          Disable Tome + enable maintenance (via CMS container)"
    echo "  tome-enable                           Re-enable Tome + disable maintenance"
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

# Show command-specific help
show_command_help() {
    local command="$1"

    case "$command" in
        "set-context")
            echo "Set Deployment Context"
            echo ""
            echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix]"
            echo ""
            echo "Description:"
            echo "  Creates environment variables for a deployment session. This sets up"
            echo "  the context that other commands will use automatically."
            echo ""
            echo "Arguments:"
            echo "  env          - Environment name (dev, stage, prod)"
            echo "  ticket       - JIRA ticket number (e.g., USAGOV-1234)"
            echo "  pre-suffix   - Optional pre-deployment backup suffix (default: 'pre')"
            echo "  post-suffix  - Optional post-deployment backup suffix (default: 'post')"
            echo ""
            echo "Examples:"
            echo "  deploy.sh set-context prod USAGOV-1234"
            echo "  deploy.sh set-context stage USAGOV-5678 before after"
            echo ""
            ;;
        "show-context")
            echo "Show Deployment Context"
            echo ""
            echo "Usage: deploy.sh show-context"
            echo ""
            echo "Description:"
            echo "  Displays current deployment context variables including environment,"
            echo "  ticket number, and backup suffixes."
            echo ""
            ;;
        "last-backup")
            echo "Show Last Backup Times"
            echo ""
            echo "Usage: deploy.sh last-backup"
            echo ""
            echo "Description:"
            echo "  Shows when each type of backup (db, static, public) was last taken."
            echo "  Helps determine if backups are current before deployment."
            echo ""
            ;;
        "status")
            echo "Show Deployment Status"
            echo ""
            echo "Usage: deploy.sh status"
            echo ""
            echo "Description:"
            echo "  Displays current Cloud Foundry target information and recent"
            echo "  application events."
            echo ""
            ;;
        "motd")
            echo "Show Message of the Day"
            echo ""
            echo "Usage: deploy.sh motd"
            echo ""
            echo "Description:"
            echo "  Displays the MOTD from the CMS container, showing system info"
            echo "  and current deployment state."
            echo ""
            ;;
        "ccb")
            echo "Show Change Control Board Summary"
            echo ""
            echo "Usage: deploy.sh ccb [from] [to]"
            echo ""
            echo "Description:"
            echo "  Shows tickets/commits between two branches or commits."
            echo ""
            echo "Arguments:"
            echo "  from  - Starting branch/commit (default: prod)"
            echo "  to    - Ending branch/commit (default: stage or DEPLOY_ENV)"
            echo ""
            echo "Examples:"
            echo "  deploy.sh ccb"
            echo "  deploy.sh ccb prod stage"
            echo "  deploy.sh ccb abc123 def456"
            echo ""
            ;;
        "show-build-info")
            echo "Show Build Information"
            echo ""
            echo "Usage: deploy.sh show-build-info <env>"
            echo ""
            echo "Description:"
            echo "  Shows latest CCI build number and container digests from git tags."
            echo ""
            echo "Arguments:"
            echo "  env  - Environment (dev, stage, prod)"
            echo ""
            echo "Example:"
            echo "  deploy.sh show-build-info prod"
            echo ""
            ;;
        "push")
            echo "Push Application Deployment"
            echo ""
            echo "Usage: deploy.sh push <name> <build> [digest] [--force]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Deploy a specific application with container digest."
            echo "  Updates the app to use a specific container image."
            echo ""
            echo "Arguments:"
            echo "  name    - App name (cms, www, waf)"
            echo "  build   - CCI build number"
            echo "  digest  - Container digest (optional if DEPLOY_{APP}_DIGEST set)"
            echo "  --force - Skip space validation"
            echo ""
            echo "Examples:"
            echo "  deploy.sh push cms 5936 gsatts/usagov-2021@sha256:abc123..."
            echo "  deploy.sh push www 5936"
            echo ""
            ;;
        "pre-deploy")
            echo "Pre-Deployment Backup"
            echo ""
            echo "Usage: deploy.sh pre-deploy [--force]"
            echo ""
            echo "Description:"
            echo "  Creates a pre-deployment backup using DEPLOY_PRE_SUFFIX."
            echo "  Validates CF space matches DEPLOY_ENV."
            echo ""
            echo "Options:"
            echo "  --force  - Skip space validation"
            echo ""
            echo "Requires: DEPLOY_TICKET environment variable"
            echo ""
            ;;
        "post-deploy")
            echo "Post-Deployment Backup"
            echo ""
            echo "Usage: deploy.sh post-deploy [--force]"
            echo ""
            echo "Description:"
            echo "  Creates a post-deployment backup and annotated git tag."
            echo "  Git tag includes CCI build and container digests for tracking."
            echo ""
            echo "Options:"
            echo "  --force  - Skip space validation"
            echo ""
            echo "Requires: DEPLOY_TICKET, DEPLOY_ENV environment variables"
            echo ""
            ;;
        "list-backups")
            echo "List Available Backups"
            echo ""
            echo "Usage: deploy.sh list-backups [days]"
            echo ""
            echo "Description:"
            echo "  Lists recent backups available for rollback."
            echo ""
            echo "Arguments:"
            echo "  days  - Show backups from last N days (default: 7)"
            echo ""
            echo "Example:"
            echo "  deploy.sh list-backups 14"
            echo ""
            ;;
        "digests")
            echo "Show Available Container Digests"
            echo ""
            echo "Usage: deploy.sh digests [env] [days] [limit] [flags]"
            echo ""
            echo "Description:"
            echo "  Shows available container digests from CF deployments, git tags,"
            echo "  and backup metadata."
            echo ""
            echo "Arguments:"
            echo "  env    - Environment filter: current, dev, stage, prod, all (default: current)"
            echo "  days   - Show backups from last N days (default: 7)"
            echo "  limit  - Limit results per category (default: 10)"
            echo ""
            echo "Flags:"
            echo "  --backups-only        Show only backup digests"
            echo "  --git-only            Show only git tag digests"
            echo "  --format=json         Output in JSON format"
            echo "  --show-all-history    Show deployments >1 year old"
            echo ""
            echo "Examples:"
            echo "  deploy.sh digests"
            echo "  deploy.sh digests prod 30 5"
            echo "  deploy.sh digests all 7 10 --git-only"
            echo ""
            ;;
        "rollback")
            echo "Rollback Deployment"
            echo ""
            echo "Usage: deploy.sh rollback [types] <cms-digest> <www-digest> <waf-digest> [tag] [--force]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Rollback code and optionally data."
            echo "  Always rolls back code. Data restore is optional."
            echo ""
            echo "Arguments:"
            echo "  types       - Data types to restore: db, static, public, full, or comma-separated"
            echo "                (default: code only, no data restore)"
            echo "  cms-digest  - Full CMS container digest"
            echo "  www-digest  - Full WWW container digest"
            echo "  waf-digest  - Full WAF container digest"
            echo "  tag         - Backup tag (required if restoring data)"
            echo "  --force     - Skip space validation"
            echo ""
            echo "Examples:"
            echo "  # Code only"
            echo "  deploy.sh rollback gsatts/usagov-2021@sha256:cms... gsatts/usagov-2021@sha256:www... gsatts/usagov-2021@sha256:waf..."
            echo ""
            echo "  # Code + database"
            echo "  deploy.sh rollback db gsatts/usagov-2021@sha256:cms... gsatts/usagov-2021@sha256:www... gsatts/usagov-2021@sha256:waf... AUTO-prod-2025-12-22-0"
            echo ""
            ;;
        "rollback-static")
            echo "Rollback Static Site"
            echo ""
            echo "Usage: deploy.sh rollback-static [tag] [--force]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Restore static site only."
            echo ""
            echo "Arguments:"
            echo "  tag      - Backup tag (optional if DEPLOY_ROLLBACK_STATIC_TAG set)"
            echo "  --force  - Skip space validation"
            echo ""
            ;;
        "rollback-db")
            echo "Rollback Database"
            echo ""
            echo "Usage: deploy.sh rollback-db [tag] [--force]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Restore database only."
            echo ""
            echo "Arguments:"
            echo "  tag      - Backup tag (optional if DEPLOY_ROLLBACK_DB_TAG set)"
            echo "  --force  - Skip space validation"
            echo ""
            ;;
        "downsync")
            echo "Downsync Data Between Spaces"
            echo ""
            echo "Usage: deploy.sh downsync <from-space> <to-space> [backup-tag]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Copy database and public files from one space to another."
            echo "  Automatically finds latest backup if tag not specified."
            echo "  Fixes MFA configuration after restore."
            echo ""
            echo "Arguments:"
            echo "  from-space  - Source environment (dev, stage, prod)"
            echo "  to-space    - Destination environment (dev, stage, prod)"
            echo "  backup-tag  - Optional backup tag (uses latest if not specified)"
            echo ""
            echo "Example:"
            echo "  deploy.sh downsync prod dev"
            echo "  deploy.sh downsync prod dev AUTO-prod-2025-12-22-0"
            echo ""
            ;;
        "download-backups")
            echo "Download Backups Locally"
            echo ""
            echo "Usage: deploy.sh download-backups [tag]"
            echo ""
            echo "Description:"
            echo "  Downloads db/static/public backups to the current directory."
            echo "  If no tag is provided, the newest backup for the current CF space is used."
            echo ""
            echo "Example:"
            echo "  deploy.sh download-backups"
            echo "  deploy.sh download-backups AUTO-prod-2025-12-22-0"
            echo ""
            ;;
        "tome-log")
            echo "Tail Latest Tome Log"
            echo ""
            echo "Usage: deploy.sh tome-log"
            echo ""
            echo "Description:"
            echo "  Finds the newest Tome log on the CMS container and tails it."
            echo "  Stops automatically when Tome finishes or reports no changes."
            echo ""
            echo "Matches stop on:"
            echo "  'Tome static build looks fine', 'No changes detected', 'no changes', 'SYNC FINISHED'"
            echo ""
            ;;
        "tome-disable")
            echo "Disable Tome and Enable Maintenance"
            echo ""
            echo "Usage: deploy.sh tome-disable [max_wait_mins]"
            echo ""
            echo "Description:"
            echo "  Runs manager's try-tome-disable inside the CMS container."
            echo "  Waits for Tome to stop (up to max_wait_mins), disables Tome, enables maintenance."
            echo ""
            echo "Arguments:"
            echo "  max_wait_mins - Optional max wait (default: 25)"
            echo ""
            ;;
        "tome-enable")
            echo "Re-enable Tome and Disable Maintenance"
            echo ""
            echo "Usage: deploy.sh tome-enable"
            echo ""
            echo "Description:"
            echo "  Runs manager's try-tome-enable inside the CMS container."
            echo "  Disables maintenance mode and re-enables Tome."
            echo ""
            ;;
        "snapshot")
            echo "Create Quick Snapshot"
            echo ""
            echo "Usage: deploy.sh snapshot [suffix]"
            echo ""
            echo "Description:"
            echo "  Creates a quick backup with auto-generated tag."
            echo ""
            echo "Arguments:"
            echo "  suffix  - Optional backup suffix (default: current time)"
            echo ""
            echo "Example:"
            echo "  deploy.sh snapshot before-test"
            echo ""
            ;;
        "snapshot-db")
            echo "Create Database Snapshot"
            echo ""
            echo "Usage: deploy.sh snapshot-db [suffix]"
            echo ""
            echo "Description:"
            echo "  Creates a quick database-only backup."
            echo ""
            echo "Arguments:"
            echo "  suffix  - Optional backup suffix (default: current time)"
            echo ""
            ;;
        "switch")
            echo "Switch Cloud Foundry Space"
            echo ""
            echo "Usage: deploy.sh switch <env>"
            echo ""
            echo "Description:"
            echo "  Switches Cloud Foundry target to specified environment."
            echo ""
            echo "Arguments:"
            echo "  env  - Environment (dev, stage, prod)"
            echo ""
            echo "Example:"
            echo "  deploy.sh switch stage"
            echo ""
            ;;
        "validate")
            echo "Validate Deployment"
            echo ""
            echo "Usage: deploy.sh validate [options]"
            echo ""
            echo "Description:"
            echo "  Validates that deployment was successful."
            echo ""
            echo "Options:"
            echo "  --only=app1,app2  - Validate specific apps only (cms, www)"
            echo "  --commit=<sha>    - Expected commit SHA (default: HEAD)"
            echo "  --skip-http       - Skip HTTP endpoint checks"
            echo ""
            echo "Examples:"
            echo "  deploy.sh validate"
            echo "  deploy.sh validate --only=cms --skip-http"
            echo ""
            ;;
        *)
            echo "No help available for command: $command"
            echo ""
            echo "Run 'deploy.sh help' for list of all commands"
            exit 1
            ;;
    esac
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

    local loader=$(show_loading "Checking backup timestamps")
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
    stop_loading "$loader"
}

# Show current status
show_status() {
    print_status $BLUE "📊 Current Status"
    echo ""
    echo "CF Target:"
    local loader=$(show_loading "Fetching Cloud Foundry status")
    cf target
    stop_loading "$loader"
    echo ""
    echo "Recent Activity (last 10 events):"
    loader=$(show_loading "Loading recent events")
    cf events cms | head -15
    stop_loading "$loader"
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

    # Run backup command
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup $types $ticket $suffix"

    # Determine the backup tag that was created
    # Format: {ticket}-{env}-{container}-{date}-{suffix}-{sequence}
    local env="${DEPLOY_ENV:-dev}"
    local container=$(cf app cms 2>/dev/null | grep 'name:' | awk '{print $2}' | grep -oE '[0-9]+$' || echo "unknown")
    local date=$(date +%Y-%m-%d)
    local backup_tag="${ticket}-${env}-${container}-${date}--${suffix}-0"

    # Update metadata in S3 with container digests
    # Download existing metadata, update it, and re-upload
    local temp_metadata="/tmp/${backup_tag}-metadata-update.json"

    if fetch_deployment_metadata "$backup_tag" > "$temp_metadata" 2>/dev/null; then
        # Use sed to update the digest fields in place
        sed -i.bak "s|\"cms\": {[^}]*}|\"cms\": { \"cci_build\": \"$cci_build\", \"digest\": \"$cms_digest\" }|" "$temp_metadata"
        sed -i.bak "s|\"www\": {[^}]*}|\"www\": { \"cci_build\": \"$cci_build\", \"digest\": \"$www_digest\" }|" "$temp_metadata"
        sed -i.bak "s|\"waf\": {[^}]*}|\"waf\": { \"cci_build\": \"$cci_build\", \"digest\": \"$waf_digest\" }|" "$temp_metadata"

        # Re-upload updated metadata
        cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars && aws s3 cp - s3://\$BUCKET_NAME/deployment-metadata/${backup_tag}.json \$S3_EXTRA_PARAMS" < "$temp_metadata"

        rm -f "$temp_metadata" "${temp_metadata}.bak"
    fi
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

# Downsync data from one space to another
# Args:
#   $1: from_space - Source environment (e.g., prod)
#   $2: to_space - Destination environment (e.g., dev)
#   $3: backup_tag - Optional backup tag (uses latest from FROM space if not specified)
downsync() {
    local from_space="$1"
    local to_space="$2"
    local backup_tag="$3"

    if [ -z "$from_space" ] || [ -z "$to_space" ]; then
        print_status $RED "❌ Error: Both FROM and TO spaces required"
        echo "Usage: deploy.sh downsync <from-space> <to-space> [backup-tag]"
        echo "Example: deploy.sh downsync prod dev"
        exit 1
    fi

    # Validate space names
    if [ "$from_space" != "dev" ] && [ "$from_space" != "stage" ] && [ "$from_space" != "prod" ]; then
        print_status $RED "❌ Error: FROM space must be dev, stage, or prod"
        exit 1
    fi

    if [ "$to_space" != "dev" ] && [ "$to_space" != "stage" ] && [ "$to_space" != "prod" ]; then
        print_status $RED "❌ Error: TO space must be dev, stage, or prod"
        exit 1
    fi

    if [ "$from_space" = "$to_space" ]; then
        print_status $RED "❌ Error: FROM and TO spaces must be different"
        exit 1
    fi

    # Save current space to restore later
    local original_space=$(cf target 2>/dev/null | grep 'space:' | awk '{print $2}')

    # If no backup tag specified, get latest from FROM space
    if [ -z "$backup_tag" ]; then
        print_status $BLUE "🔍 Finding latest backup from $from_space..."

        # Switch to FROM space to query backups
        cf target -s "$from_space" >/dev/null 2>&1
        if [ $? -ne 0 ]; then
            print_status $RED "❌ Error: Failed to target FROM space: $from_space"
            exit 1
        fi

        # Get latest DB backup tag
        backup_tag=$(cf ssh cms -c "
            export AWS_DEFAULT_REGION='us-gov-west-1'
            . /home/vcap/app/scripts/snapshot/includes
            setup_s3_vars >/dev/null 2>&1
            aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ --recursive \$S3_EXTRA_PARAMS | \
            grep '\.sql\.gz\$' | \
            sort -r | \
            head -1 | \
            awk '{print \$4}' | \
            xargs basename | \
            sed 's/\.sql\.gz\$/'" 2>/dev/null | tail -1)

        if [ -z "$backup_tag" ]; then
            print_status $RED "❌ Error: No backups found in $from_space"
            [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
            exit 1
        fi

        print_status $GREEN "✅ Found latest backup: $backup_tag"
    fi

    # Show confirmation
    print_status $YELLOW "⚠️  DOWNSYNC CONFIRMATION"
    echo ""
    echo "  FROM:   $from_space"
    echo "  TO:     $to_space"
    echo "  BACKUP: $backup_tag"
    echo ""
    print_status $YELLOW "This will:"
    echo "  1. Copy database from $from_space to $to_space"
    echo "  2. Copy public files from $from_space to $to_space"
    echo "  3. Run database updates and config imports"
    echo "  4. Fix MFA configuration for $to_space"
    echo ""
    print_status $RED "⚠️  WARNING: This will OVERWRITE all data in $to_space!"
    echo ""
    read -p "Type 'yes' to proceed: " confirm

    if [ "$confirm" != "yes" ]; then
        print_status $YELLOW "❌ Downsync cancelled"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 0
    fi

    print_status $BLUE "🚀 Starting downsync..."
    echo ""

    # Create temporary directory for downloads
    local temp_dir=$(mktemp -d)
    local db_file="$temp_dir/${backup_tag}.sql.gz"
    local public_dir="$temp_dir/${backup_tag}_public"

    # Switch to FROM space and download backups
    print_status $BLUE "📥 Downloading backups from $from_space..."
    cf target -s "$from_space" >/dev/null 2>&1

    # Download via CMS container
    print_status $BLUE "  Downloading database..."
    cf ssh cms -c "
        export AWS_DEFAULT_REGION='us-gov-west-1'
        . /home/vcap/app/scripts/snapshot/includes
        setup_s3_vars >/dev/null 2>&1
        aws s3 cp s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/${backup_tag}.sql.gz - \$S3_EXTRA_PARAMS
    " > "$db_file" 2>/dev/null

    if [ ! -s "$db_file" ]; then
        print_status $RED "❌ Error: Failed to download database backup"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 1
    fi

    print_status $GREEN "  ✅ Database downloaded ($(du -h "$db_file" | cut -f1))"

    print_status $BLUE "  Downloading public files..."
    mkdir -p "$public_dir"
    cf ssh cms -c "
        export AWS_DEFAULT_REGION='us-gov-west-1'
        . /home/vcap/app/scripts/snapshot/includes
        setup_s3_vars >/dev/null 2>&1
        cd /tmp
        aws s3 sync s3://\$BUCKET_NAME/\$AUTO_PUBLIC_BACKUP_PATH/${backup_tag}/ . \$S3_EXTRA_PARAMS
        tar czf - .
    " > "$temp_dir/public.tar.gz" 2>/dev/null

    if [ ! -s "$temp_dir/public.tar.gz" ]; then
        print_status $RED "❌ Error: Failed to download public files backup"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 1
    fi

    tar xzf "$temp_dir/public.tar.gz" -C "$public_dir" 2>/dev/null
    rm "$temp_dir/public.tar.gz"
    print_status $GREEN "  ✅ Public files downloaded ($(du -sh "$public_dir" | cut -f1))"

    # Switch to TO space
    print_status $BLUE "🎯 Targeting $to_space..."
    cf target -s "$to_space" >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        print_status $RED "❌ Error: Failed to target TO space: $to_space"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 1
    fi

    # Get tome state before restore
    print_status $BLUE "📋 Checking tome state..."
    local tome_disabled=$(cf ssh cms -c ". /etc/profile; drush sget usagov.tome_run_disabled" 2>/dev/null | tail -1)

    # Disable tome and enable maintenance mode
    print_status $BLUE "🔒 Enabling maintenance mode..."
    cf ssh cms -c "/var/www/scripts/maintenance-mode-toggle.sh 1" >/dev/null 2>&1
    cf ssh cms -c ". /etc/profile; drush sset usagov.tome_run_disabled 1" >/dev/null 2>&1

    # Upload and restore database
    print_status $BLUE "📤 Uploading and restoring database..."
    cf ssh cms -c "cat > /tmp/${backup_tag}.sql.gz" < "$db_file"
    cf ssh cms -c "
        . /etc/profile
        cd /tmp
        gunzip -f ${backup_tag}.sql.gz
        drush sql-cli < ${backup_tag}.sql
        rm -f ${backup_tag}.sql
    " >/dev/null 2>&1

    if [ $? -ne 0 ]; then
        print_status $RED "❌ Error: Failed to restore database"
        print_status $YELLOW "⚠️  Site may be in maintenance mode - check manually"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 1
    fi
    print_status $GREEN "  ✅ Database restored"

    # Upload and restore public files
    print_status $BLUE "📤 Uploading public files to S3..."
    cf ssh cms -c "
        export AWS_DEFAULT_REGION='us-gov-west-1'
        . /home/vcap/app/scripts/snapshot/includes
        setup_s3_vars >/dev/null 2>&1
        cd /tmp
        rm -rf public_restore
        mkdir public_restore
    " >/dev/null 2>&1

    # Upload files in chunks via tar
    (cd "$public_dir" && tar czf - .) | cf ssh cms -c "cd /tmp/public_restore && tar xzf -" 2>/dev/null

    cf ssh cms -c "
        export AWS_DEFAULT_REGION='us-gov-west-1'
        . /home/vcap/app/scripts/snapshot/includes
        setup_s3_vars >/dev/null 2>&1
        aws s3 sync /tmp/public_restore/ s3://\$BUCKET_NAME/cms/public/ --acl public-read \$S3_EXTRA_PARAMS
        rm -rf /tmp/public_restore
    " >/dev/null 2>&1

    if [ $? -ne 0 ]; then
        print_status $RED "❌ Error: Failed to restore public files"
        print_status $YELLOW "⚠️  Database restored but public files failed"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 1
    fi
    print_status $GREEN "  ✅ Public files restored"

    # Run database updates
    print_status $BLUE "🔄 Running database updates..."
    cf ssh cms -c "
        . /etc/profile
        drush cr
        drush updatedb --no-cache-clear -y
        drush cim -y || drush cim -y
        drush cim -y
        drush php-eval 'node_access_rebuild();' -y
    " >/dev/null 2>&1

    if [ $? -ne 0 ]; then
        print_status $YELLOW "⚠️  Warning: Some database updates may have failed"
    else
        print_status $GREEN "  ✅ Database updates complete"
    fi

    # Disable maintenance mode
    print_status $BLUE "🔓 Disabling maintenance mode..."
    cf ssh cms -c "/var/www/scripts/maintenance-mode-toggle.sh 0" >/dev/null 2>&1

    # Restore tome state
    if [ "$tome_disabled" != "1" ]; then
        cf ssh cms -c ". /etc/profile; drush sset usagov.tome_run_disabled 0" >/dev/null 2>&1
    fi

    # Fix MFA configuration
    print_status $BLUE "🔐 Fixing MFA configuration for $to_space..."
    cf ssh cms -c "
        . /etc/profile
        /var/www/scripts/gsaauth/configset.sh $to_space
    " >/dev/null 2>&1

    if [ $? -ne 0 ]; then
        print_status $YELLOW "⚠️  Warning: MFA configuration may have failed"
    else
        print_status $GREEN "  ✅ MFA configuration updated"
    fi

    # Cleanup
    rm -rf "$temp_dir"

    # Restore original space
    if [ -n "$original_space" ] && [ "$original_space" != "$to_space" ]; then
        cf target -s "$original_space" >/dev/null 2>&1
    fi

    echo ""
    print_status $GREEN "✅ Downsync complete!"
    echo ""
    echo "  Data from $from_space has been copied to $to_space"
    echo "  Backup used: $backup_tag"
    echo ""
}

# List backups for rollback
list_backups() {
    local days="${1:-7}"

    print_status $BLUE "📋 Available backups (last $days days)"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh list all $days"
}

# List available container digests with deployment history
# Args:
#   $1: env - Environment filter (default: current from cf target)
#   $2: days - Show backups from last N days (default: 7)
#   $3: limit - Limit results per category (default: 10)
#   Additional flags: --backups-only, --git-only, --format=json
list_digests() {
    local target_env=""
    local days="7"
    local limit="10"
    local backups_only=false
    local git_only=false
    local format="table"
    local show_all_history=false

    # Parse all arguments (flags and positional)
    while [ $# -gt 0 ]; do
        case "$1" in
            --backups-only) backups_only=true ;;
            --git-only) git_only=true ;;
            --format=*) format="${1#*=}" ;;
            --show-all-history) show_all_history=true ;;
            *)
                # First non-flag arg is env, second is days, third is limit
                if [ -z "$target_env" ]; then
                    target_env="$1"
                elif [ "$days" = "7" ]; then
                    days="$1"
                elif [ "$limit" = "10" ]; then
                    limit="$1"
                fi
                ;;
        esac
        shift
    done

    # Determine target environment
    if [ -z "$target_env" ] || [ "$target_env" = "current" ]; then
        target_env="${DEPLOY_ENV:-$(cf target 2>/dev/null | grep 'space:' | awk '{print $2}')}"
        if [ -z "$target_env" ]; then
            print_status $RED "❌ Error: Could not determine environment"
            echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
            return 1
        fi
    fi

    # Validate environment
    case "$target_env" in
        dev|stage|prod|all) ;;
        *)
            print_status $RED "❌ Invalid environment: $target_env"
            echo "Valid options: dev, stage, prod, all, current"
            return 1
            ;;
    esac

    print_status $BLUE "🔍 Container Digests - ${target_env} environment"
    echo ""

    # Determine which environments to query
    local envs_to_query
    if [ "$target_env" = "all" ]; then
        envs_to_query="dev stage prod"
    else
        envs_to_query="$target_env"
    fi

    for env in $envs_to_query; do
        if [ "$target_env" = "all" ]; then
            print_status $YELLOW "=== $env ==="
            echo ""
        fi

        # Get current and previous deployments from CF
        if ! $git_only; then
            _show_current_and_previous_digests "$env"
            echo ""
        fi

        # Get recent deployments from git tags
        if ! $backups_only; then
            _show_git_tag_digests "$env" "$limit" "$show_all_history"
            echo ""
        fi

        # Get backups with digest metadata
        if ! $git_only; then
            _show_backup_digests "$env" "$days"
            echo ""
        fi

        # Show quick rollback command
        if [ "$target_env" != "all" ]; then
            _show_rollback_command "$env"
        fi
    done
}

# Helper: Show current and previous deployments from CF
_show_current_and_previous_digests() {
    local env="$1"

    print_status $GREEN "CURRENTLY DEPLOYED:"
    show_loading "Querying Cloud Foundry"
    # Get current digests for all apps
    local cms_current=$(get_app_digest "cms" 2>/dev/null || echo "")
    local www_current=$(get_app_digest "www" 2>/dev/null || echo "")
    local waf_current=$(get_app_digest "waf" 2>/dev/null || echo "")
    stop_loading

    if [ -z "$cms_current" ]; then
        echo "  Unable to query CF (check 'cf target' and login)"
        return 1
    fi

    # Get deployment time from app info
    local cms_updated=$(cf app cms 2>/dev/null | grep "^last uploaded:" | sed 's/^last uploaded: *//')

    # Extract build number if present (only in specific formats)
    local build_num="unknown"
    if echo "$cms_current" | grep -qE 'usagov[_-](cms|2021):[0-9]+@'; then
        build_num=$(echo "$cms_current" | sed -E 's/.*usagov[_-](cms|2021):([0-9]+)@.*/\2/')
    fi

    echo "  Deployed: ${cms_updated:-unknown}"
    echo "  Build: $build_num"
    echo "  ✓ CMS: $cms_current"
    echo "  ✓ WWW: $www_current"
    echo "  ✓ WAF: $waf_current"

    # Try to get previous deployment from CF revisions API
    print_status $YELLOW "PREVIOUSLY DEPLOYED:"

    local cms_guid=$(cf app cms --guid 2>/dev/null)
    if [ -n "$cms_guid" ]; then
        # Get droplet history (current is index 0, previous is index 1)
        local droplets=$(cf curl "/v3/apps/${cms_guid}/droplets?order_by=-created_at&per_page=5" 2>/dev/null)

        # Extract second droplet (previous deployment)
        local prev_droplet_guid=$(echo "$droplets" | grep -o '"guid":"[^"]*"' | head -2 | tail -1 | cut -d'"' -f4)

        if [ -n "$prev_droplet_guid" ] && [ "$prev_droplet_guid" != "guid" ]; then
            # Get droplet details to find docker image
            local prev_droplet_info=$(cf curl "/v3/droplets/${prev_droplet_guid}" 2>/dev/null)
            local cms_prev=$(echo "$prev_droplet_info" | grep -o '"image":"[^"]*"' | cut -d'"' -f4)
            local prev_created=$(echo "$prev_droplet_info" | grep -o '"created_at":"[^"]*"' | cut -d'"' -f4)

            if [ -n "$cms_prev" ]; then
                # Get corresponding www and waf from same time period
                local www_guid=$(cf app www --guid 2>/dev/null)
                local waf_guid=$(cf app waf --guid 2>/dev/null)

                local www_prev=""
                local waf_prev=""

                if [ -n "$www_guid" ]; then
                    local www_droplets=$(cf curl "/v3/apps/${www_guid}/droplets?order_by=-created_at&per_page=5" 2>/dev/null)
                    local www_prev_guid=$(echo "$www_droplets" | grep -o '"guid":"[^"]*"' | head -2 | tail -1 | cut -d'"' -f4)
                    if [ -n "$www_prev_guid" ]; then
                        www_prev=$(cf curl "/v3/droplets/${www_prev_guid}" 2>/dev/null | grep -o '"image":"[^"]*"' | cut -d'"' -f4)
                    fi
                fi

                if [ -n "$waf_guid" ]; then
                    local waf_droplets=$(cf curl "/v3/apps/${waf_guid}/droplets?order_by=-created_at&per_page=5" 2>/dev/null)
                    local waf_prev_guid=$(echo "$waf_droplets" | grep -o '"guid":"[^"]*"' | head -2 | tail -1 | cut -d'"' -f4)
                    if [ -n "$waf_prev_guid" ]; then
                        waf_prev=$(cf curl "/v3/droplets/${waf_prev_guid}" 2>/dev/null | grep -o '"image":"[^"]*"' | cut -d'"' -f4)
                    fi
                fi

                # Extract build number
                local prev_build="unknown"
                if echo "$cms_prev" | grep -qE 'usagov[_-](cms|2021):[0-9]+@'; then
                    prev_build=$(echo "$cms_prev" | sed -E 's/.*usagov[_-](cms|2021):([0-9]+)@.*/\2/')
                fi

                echo "  Deployed: ${prev_created:-unknown}"
                echo "  Build: $prev_build"
                echo "  ← CMS: ${cms_prev} (rollback target)"
                echo "  ← WWW: ${www_prev:-unknown} (rollback target)"
                echo "  ← WAF: ${waf_prev:-unknown} (rollback target)"

                # Store for rollback command generation
                ROLLBACK_CMS="$cms_prev"
                ROLLBACK_WWW="$www_prev"
                ROLLBACK_WAF="$waf_prev"
            else
                echo "  No previous deployment found in CF history"
            fi
        else
            echo "  No previous deployment found in CF history"
        fi
    else
        echo "  Unable to query CF API"
    fi
}

# Helper: Show git tag digests (excluding current and previous builds)
_show_git_tag_digests() {
    local env="$1"
    local limit="$2"
    local show_all_history="${3:-false}"

    print_status $CYAN "RECENT DEPLOYMENTS (from git tags, excluding current/previous):"

    # Calculate 1 year cutoff date unless showing all history
    local cutoff_date=""
    if [ "$show_all_history" != "true" ]; then
        cutoff_date=$(date -u -v-1y +"%Y-%m-%d" 2>/dev/null || date -u -d "1 year ago" +"%Y-%m-%d" 2>/dev/null)
    fi
    local prev_build=""

    if echo "$cms_current" | grep -qE 'usagov[_-](cms|2021):[0-9]+@'; then
        current_build=$(echo "$cms_current" | sed -E 's/.*usagov[_-](cms|2021):([0-9]+)@.*/\2/')
    fi

    # Try to get previous build from CF
    local cms_guid=$(cf app cms --guid 2>/dev/null)
    if [ -n "$cms_guid" ]; then
        local droplets=$(cf curl "/v3/apps/${cms_guid}/droplets?order_by=-created_at&per_page=5" 2>/dev/null)
        local prev_droplet_guid=$(echo "$droplets" | grep -o '"guid":"[^"]*"' | head -2 | tail -1 | cut -d'"' -f4)
        if [ -n "$prev_droplet_guid" ] && [ "$prev_droplet_guid" != "guid" ]; then
            local prev_droplet=$(cf curl "/v3/droplets/${prev_droplet_guid}" 2>/dev/null)
            local cms_prev=$(echo "$prev_droplet" | grep -o '"image":"[^"]*"' | cut -d'"' -f4)
            if echo "$cms_prev" | grep -qE 'usagov[_-](cms|2021):[0-9]+@'; then
                prev_build=$(echo "$cms_prev" | sed -E 's/.*usagov[_-](cms|2021):([0-9]+)@.*/\2/')
            fi
        fi
    fi

    # List git tags for this environment
    local tags=$(git tag -l "usagov-cci-build-*-${env}" --sort=-version:refname 2>/dev/null)

    if [ -z "$tags" ]; then
        echo "  No git tags found for $env environment"
        return
    fi

    local count=0
    local filtered_count=0
    local total_tags=0
    local has_output=false

    while IFS= read -r tag; do
        [ -z "$tag" ] && continue
        total_tags=$((total_tags + 1))

        # Extract build number from tag
        local build=$(echo "$tag" | sed 's/usagov-cci-build-\([0-9]*\)-.*/\1/')

        # Skip if this is current or previous build
        if [ "$build" = "$current_build" ] || [ "$build" = "$prev_build" ]; then
            continue
        fi

        # Get tag date for filtering
        local tag_date_full=$(git log -1 --format=%ai "$tag" 2>/dev/null)
        local tag_date_only=$(echo "$tag_date_full" | cut -d' ' -f1)

        # Check if tag is within date range (skip if too old)
        if [ -n "$cutoff_date" ] && [ -n "$tag_date_only" ] && [ "$tag_date_only" \< "$cutoff_date" ]; then
            filtered_count=$((filtered_count + 1))
            continue
        fi

        count=$((count + 1))
        if [ $count -gt $limit ]; then
            break
        fi

        has_output=true

        # Get tag message with digests
        local tag_msg=$(git tag -l --format='%(contents)' "$tag" 2>/dev/null)
        local cms_digest=$(echo "$tag_msg" | grep -o 'CMS_DIGEST=[^|]*' | cut -d= -f2)
        local www_digest=$(echo "$tag_msg" | grep -o 'WWW_DIGEST=[^|]*' | cut -d= -f2)
        local waf_digest=$(echo "$tag_msg" | grep -o 'WAF_DIGEST=[^|]*' | cut -d= -f2)

        # Handle missing digests (older tags may not have WWW)
        [ -z "$www_digest" ] && www_digest="(not tracked in this build)"

        # Format tag date (already fetched above)
        local tag_date=$(echo "$tag_date_full" | cut -d' ' -f1,2)

        echo "  Build $build ($env) - $tag_date"
        echo "    CMS: $cms_digest"
        echo "    WWW: $www_digest"
        echo "    WAF: $waf_digest"
        echo ""
    done <<EOF
$tags
EOF

    # Show summary message
    if [ "$has_output" = "false" ]; then
        if [ $filtered_count -gt 0 ]; then
            echo "  No deployments found in the last year."
            echo "  ($filtered_count older deployments hidden - use --show-all-history to view)"
        elif [ $total_tags -gt 0 ]; then
            echo "  No additional deployments found (only current/previous exist)"
        fi
    elif [ $filtered_count -gt 0 ]; then
        echo "  ($filtered_count older deployments hidden - use --show-all-history to view)"
    fi
}

# Helper: Show backups with digest metadata
_show_backup_digests() {
    local env="$1"
    local days="$2"

    print_status $MAGENTA "BACKUPS WITH DIGESTS (last $days days):"
    show_loading "Scanning S3 backup metadata"
    # Get S3 bucket name from CF environment
    local bucket_name=$(cf curl "/v3/apps/$(cf app cms --guid 2>/dev/null)/env" 2>/dev/null | grep -o '"bucket":"[^"]*"' | head -1 | cut -d'"' -f4)

    if [ -z "$bucket_name" ]; then
        # Fallback to known bucket for dev
        bucket_name="cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5"
    fi

    # Get current digests for comparison
    local cms_current=$(get_app_digest "cms" 2>/dev/null || echo "")

    # List recent metadata files from S3
    if ! command -v aws >/dev/null 2>&1; then
        echo "  AWS CLI not available"
        return 1
    fi

    local cutoff_date=$(date -u -v-${days}d +"%Y-%m-%d" 2>/dev/null || date -u -d "${days} days ago" +"%Y-%m-%d" 2>/dev/null)
    local metadata_files=$(aws s3 ls "s3://${bucket_name}/deployment-metadata/" --recursive 2>/dev/null | grep "\.json$" | grep -v "\.current_digests\.json" | awk '{print $4}')

    if [ -z "$metadata_files" ]; then
        echo "  No backup metadata found"
        return
    fi

    # Download and parse each metadata file
    local shown=0
    local total_checked=0
    while read -r file; do
        [ -z "$file" ] && continue
        local backup_tag=$(basename "$file" .json)

        # Download metadata
        local metadata=$(aws s3 cp "s3://${bucket_name}/${file}" - 2>/dev/null)

        if [ -z "$metadata" ]; then
            continue
        fi

        # Parse metadata
        local created=$(echo "$metadata" | grep '"timestamp"' | sed 's/.*"timestamp": "\([^"]*\)".*/\1/')
        local ticket=$(echo "$metadata" | grep '"ticket"' | sed 's/.*"ticket": "\([^"]*\)".*/\1/')
        local backup_type=$(echo "$metadata" | grep '"backup_type"' | sed 's/.*"backup_type": "\([^"]*\)".*/\1/')
        local cms_digest=$(echo "$metadata" | grep -A2 '"cms":' | grep '"digest":' | sed 's/.*"digest": "\([^"]*\)".*/\1/')

        # Check if within date range
        if [ -n "$cutoff_date" ] && [ -n "$created" ]; then
            local backup_date=$(echo "$created" | cut -d'T' -f1)
            if [ "$backup_date" \< "$cutoff_date" ]; then
                continue
            fi
        fi

        # Check environment match
        if ! echo "$backup_tag" | grep -q -- "-${env}-"; then
            continue
        fi

        total_checked=$((total_checked + 1))

        # Skip backups without digests (only show backups WITH digests)
        if [ -z "$cms_digest" ]; then
            continue
        fi

        shown=$((shown + 1))

        # Determine status
        local status="✅"
        local annotation=""
        if [ "$cms_digest" = "$cms_current" ]; then
            annotation=" ← CURRENT"
        fi

        echo "  $status $backup_tag"
        echo "     Created: $created"
        echo "     Ticket: $ticket | Type: $backup_type"
        echo "     CMS: ${cms_digest}${annotation}"
        echo ""

        if [ $shown -ge 10 ]; then
            break
        fi
    done <<EOF
$metadata_files
EOF

    stop_loading

    # If no backups with digests were found, show a message
    if [ $shown -eq 0 ]; then
        if [ $total_checked -eq 0 ]; then
            echo "  No backups found in the last $days days"
        else
            echo "  No backups with digests found in the last $days days"
        fi
    fi
}

# Helper: Show quick rollback command
_show_rollback_command() {
    local env="$1"

    if [ -n "$ROLLBACK_CMS" ] && [ -n "$ROLLBACK_WWW" ] && [ -n "$ROLLBACK_WAF" ]; then
        print_status $BLUE "QUICK ROLLBACK:"
        echo "  To rollback to previous deployment:"
        echo "    ./scripts/devops/deploy.sh rollback \\"
        echo "      $ROLLBACK_CMS \\"
        echo "      $ROLLBACK_WWW \\"
        echo "      $ROLLBACK_WAF"
        echo ""
        echo "  With data restore (requires backup tag):"
        echo "    ./scripts/devops/deploy.sh rollback db \\"
        echo "      $ROLLBACK_CMS \\"
        echo "      $ROLLBACK_WWW \\"
        echo "      $ROLLBACK_WAF \\"
        echo "      <backup-tag>"
    fi
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

# Download backups locally - defaults to latest backup for current space
download_backups() {
    local tag="${1:-}"
    local output_dir="$(pwd)"

    # If no tag provided, find the most recent backup
    if [ -z "$tag" ]; then
        print_status $BLUE "🔍 Finding most recent backup..."

        # Query S3 to get the most recent backup tag
        tag=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars &&
            aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ \$S3_EXTRA_PARAMS |
            grep '\.sql\.gz$' |
            sort -r |
            head -1 |
            awk '{print \$4}' |
            sed 's/\.sql\.gz$//'" 2>/dev/null | tail -1 | tr -d '\r')

        if [ -z "$tag" ]; then
            print_status $RED "❌ Error: Could not find any backups"
            return 1
        fi

        print_status $GREEN "✅ Found latest backup: $tag"
    fi

    print_status $BLUE "📥 Downloading backups for: $tag"
    echo "Output directory: $output_dir"
    echo ""

    local failed=0

    # Download database backup
    print_status $YELLOW "📦 Downloading database..."
    cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $tag db - --stream" > "${output_dir}/${tag}-database.sql.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-database.sql.gz" ]; then
        print_status $GREEN "  ✅ Database downloaded ($(du -h "${output_dir}/${tag}-database.sql.gz" | cut -f1))"
    else
        print_status $RED "  ❌ Database download failed"
        failed=$((failed + 1))
    fi

    # Download static site backup
    print_status $YELLOW "📦 Downloading static site..."
    cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $tag static - --stream" > "${output_dir}/${tag}-static.tar.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-static.tar.gz" ]; then
        print_status $GREEN "  ✅ Static site downloaded ($(du -h "${output_dir}/${tag}-static.tar.gz" | cut -f1))"
    else
        print_status $RED "  ❌ Static site download failed"
        failed=$((failed + 1))
    fi

    # Download public files backup
    print_status $YELLOW "📦 Downloading public files..."
    cf ssh cms -c "source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download $tag public - --stream" > "${output_dir}/${tag}-public.tar.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-public.tar.gz" ]; then
        print_status $GREEN "  ✅ Public files downloaded ($(du -h "${output_dir}/${tag}-public.tar.gz" | cut -f1))"
    else
        print_status $RED "  ❌ Public files download failed"
        failed=$((failed + 1))
    fi

    echo ""
    if [ $failed -eq 0 ]; then
        print_status $GREEN "✅ Download complete!"
        echo ""
        echo "Downloaded files:"
        ls -lh "${output_dir}/${tag}"-* 2>/dev/null
        return 0
    else
        print_status $YELLOW "⚠️  Download completed with $failed error(s)"
        echo ""
        echo "Downloaded files:"
        ls -lh "${output_dir}/${tag}"-* 2>/dev/null
        return 1
    fi
}

# Tail the latest Tome log and stop when it finishes
tome_log() {
    print_status $BLUE "🔍 Finding latest Tome log..."

    # Find the most recent log file in /tmp/tome-log/YYYY/MM/DD/
    local latest_log=$(cf ssh cms -c "find /tmp/tome-log -type f -name '*.log' 2>/dev/null | sort -r | head -1" 2>/dev/null | tail -1 | tr -d '\r')

    if [ -z "$latest_log" ]; then
        print_status $RED "❌ Error: No Tome logs found"
        echo "Logs are stored in: /tmp/tome-log/YYYY/MM/DD/"
        return 1
    fi

    print_status $GREEN "✅ Found log: $latest_log"
    echo ""
    print_status $YELLOW "📄 Tailing log (will stop when Tome finishes)..."
    echo ""

    # Tail the log and stop on completion markers
    # Use cf ssh with tail -f, grep for completion markers
    cf ssh cms -c "
        tail -f -n 50 $latest_log 2>/dev/null |
        while IFS= read -r line; do
            echo \"\$line\"
            # Check for completion markers
            if echo \"\$line\" | grep -qiE '(Tome static build looks fine|No changes detected|no changes|SYNC FINISHED)'; then
                echo ''
                echo '✅ Tome process completed.'
                break
            fi
        done
    "
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

    if [ -z "$app_name" ] || [ -z "$cci_build" ]; then
        print_status $RED "❌ Error: Missing required parameters"
        echo "Usage: deploy.sh deploy app <app-name> <cci-build> [digest]"
        echo "Example: deploy.sh deploy app cms 5936 gsatts/usagov-2021@sha256:abc123..."
        echo ""
        echo "If digest not provided, will look up from:"
        echo "  1. DEPLOY_{APP}_DIGEST environment variable (set by show-build-info)"
        echo "  2. Git tag: usagov-cci-build-{build}-{env}"
        return 1
    fi

    # Digest fallback logic
    if [ -z "$digest" ]; then
        # Try environment variable first (set by show-build-info)
        local env_var_name="DEPLOY_$(echo "$app_name" | tr '[:lower:]' '[:upper:]')_DIGEST"
        digest=$(eval echo "\$$env_var_name")

        if [ -z "$digest" ]; then
            # Try looking up from git tag
            local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"
            if [ -n "$env" ]; then
                print_status $BLUE "🔍 Looking up digest from git tag: usagov-cci-build-${cci_build}-${env}"
                local tag_name="usagov-cci-build-${cci_build}-${env}"

                if git rev-parse "$tag_name" >/dev/null 2>&1; then
                    local tag_msg=$(git tag -l --format='%(contents)' "$tag_name")

                    case "$app_name" in
                        cms)
                            digest=$(echo "$tag_msg" | grep -o 'CMS_DIGEST=[^|]*' | cut -d= -f2)
                            ;;
                        waf)
                            digest=$(echo "$tag_msg" | grep -o 'WAF_DIGEST=[^|]*' | cut -d= -f2)
                            ;;
                        www)
                            digest=$(echo "$tag_msg" | grep -o 'WWW_DIGEST=[^|]*' | cut -d= -f2)
                            ;;
                    esac

                    if [ -n "$digest" ]; then
                        print_status $GREEN "✅ Found digest in git tag"
                    fi
                fi
            fi
        fi

        if [ -z "$digest" ]; then
            print_status $RED "❌ Error: Could not determine digest for $app_name"
            echo ""
            echo "Options:"
            echo "  1. Provide digest as third argument"
            echo "  2. Run 'deploy.sh show-build-info $env' first to set env vars"
            echo "  3. Ensure git tag exists: usagov-cci-build-${cci_build}-${env}"
            return 1
        fi
    fi

    # Normalize digest format
    # If digest doesn't contain '/', it's just @sha256:..., prepend default registry
    if ! echo "$digest" | grep -q '/'; then
        digest="gsatts/usagov-2021${digest}"
        print_status $BLUE "📦 Using default registry: $digest"
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

# Rollback command - redeploy containers with specified digests
rollback() {
    local data_types=""
    local cms_digest=""
    local www_digest=""
    local waf_digest=""
    local backup_tag=""
    local force=""

    # Parse: [types] <cms-digest> <www-digest> <waf-digest> [backup-tag] [--force]
    # Check for --force flag in any position
    for arg in "$@"; do
        if [ "$arg" = "--force" ]; then
            force="force"
        fi
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$force"

    # Parse: [types] <cms-digest> <www-digest> <waf-digest> [backup-tag]
    # If first arg looks like a data type, it's types; otherwise first digest
    case "$1" in
        db|static|public|full|*,*)
            data_types="$1"
            cms_digest="$2"
            www_digest="$3"
            waf_digest="$4"
            backup_tag="$5"
            ;;
        *)
            # No type = code only (default)
            cms_digest="$1"
            www_digest="$2"
            waf_digest="$3"
            backup_tag="$4"
            ;;
    esac

    if [ -z "$cms_digest" ] || [ -z "$www_digest" ] || [ -z "$waf_digest" ]; then
        print_status $RED "❌ Error: Container digests required"
        echo "Usage: deploy.sh rollback [db|static|public|full] <cms-digest> <www-digest> <waf-digest> [backup-tag]"
        echo "       deploy.sh rollback <cms-digest> <www-digest> <waf-digest>  (defaults to code only)"
        echo ""
        echo "Examples:"
        echo "  deploy.sh rollback gsatts/usagov-2021@sha256:abc... gsatts/usagov-2021@sha256:def... gsatts/usagov-2021@sha256:ghi..."
        echo "  deploy.sh rollback db gsatts/usagov-2021@sha256:abc... gsatts/usagov-2021@sha256:def... gsatts/usagov-2021@sha256:ghi... AUTO-prod-2025-12-22-0"
        echo ""
        echo "Use 'deploy.sh list-digests' to see available container digests."
        return 1
    fi

    # Determine environment
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Could not determine environment"
        echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
        return 1
    fi

    # Extract CCI build from digest if possible
    local cci_build="unknown"
    if echo "$cms_digest" | grep -qE 'usagov_cms:[0-9]+@'; then
        cci_build=$(echo "$cms_digest" | sed 's/.*usagov_cms:\([0-9]*\)@.*/\1/')
    fi

    # Show what will be rolled back
    print_status $YELLOW "⚠️  CODE ROLLBACK"
    echo "Will redeploy containers with specified digests"
    echo "Environment: $env"
    echo ""
    echo "  CMS: $cms_digest"
    echo "  WWW: $www_digest"
    echo "  WAF: $waf_digest"
    echo "  Build: $cci_build"
    if [ -n "$backup_tag" ]; then
        echo "  Data backup: $backup_tag"
    fi
    echo ""

    if [ -n "$data_types" ]; then
        echo "Will also restore data: $data_types"
        if [ -z "$backup_tag" ]; then
            print_status $RED "❌ Error: Backup tag required for data restoration"
            echo "Usage: deploy.sh rollback $data_types <cms-digest> <www-digest> <waf-digest> <backup-tag>"
            return 1
        fi
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
        local loader=$(show_loading "Checking deployment status")

        # Check if app exists and is running
        local app_info
        app_info=$(cf app "$app" 2>&1)
        stop_loading "$loader"

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
            loader=$(show_loading "Testing HTTP endpoint")
            local app_url
            app_url=$(cf app "$app" | grep "^routes:" | awk '{print $2}' | head -1)

            if [ -n "$app_url" ]; then
                local http_status
                http_status=$(curl -s -o /dev/null -w "%{http_code}" -L "https://$app_url" --max-time 10 2>/dev/null)
                stop_loading "$loader"

                if [ "$http_status" = "200" ]; then
                    print_status $GREEN "✅ HTTP endpoint responding (200)"
                elif [ -n "$http_status" ]; then
                    print_status $YELLOW "⚠️  HTTP endpoint returned: $http_status"
                else
                    print_status $RED "❌ HTTP endpoint not responding"
                    overall_success=false
                fi
            else
                stop_loading "$loader"
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
shift || true

# Handle -h/--help flag
if [ "$COMMAND" = "-h" ] || [ "$COMMAND" = "--help" ] || [ "$COMMAND" = "help" ] || [ -z "$COMMAND" ]; then
    show_usage
    exit 0
fi

# Check if first arg after command is -h/--help
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    show_command_help "$COMMAND"
    exit 0
fi

case "$COMMAND" in
    "set-context")
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
        show_changes "$@"
        ;;
    "show-build-info")
        show_build_info "$@"
        ;;
    "push")
        deploy_app "$@"
        ;;
    "pre-deploy")
        pre_deploy "$@"
        ;;
    "post-deploy")
        post_deploy "$@"
        ;;
    "list-backups")
        list_backups "$@"
        ;;
    "digests")
        list_digests "$@"
        ;;
    "rollback")
        rollback "$@"
        ;;
    "rollback-static")
        rollback_static "$@"
        ;;
    "rollback-db")
        rollback_db "$@"
        ;;
    "snapshot")
        snapshot "$@"
        ;;
    "snapshot-db")
        snapshot_db "$@"
        ;;
    "downsync")
        downsync "$@"
        ;;
    "download-backups")
        download_backups "$@"
        ;;
    "tome-log")
        tome_log
        ;;
    "tome-disable")
        # Call common.sh directly
        cf ssh cms -c "source /etc/profile && cd /var/www && . scripts/common.sh && tome_disable ${1:-25}"
        ;;
    "tome-enable")
        # Call common.sh directly
        cf ssh cms -c "source /etc/profile && cd /var/www && . scripts/common.sh && tome_enable"
        ;;
    "switch")
        switch_env "$@"
        ;;
    "validate")
        validate_deployment "$@"
        ;;
    *)
        print_status $RED "❌ Unknown command: $COMMAND"
        echo ""
        show_usage
        exit 1
        ;;
esac
