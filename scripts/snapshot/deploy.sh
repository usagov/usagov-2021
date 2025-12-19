#!/bin/sh

# Deployment Helper Script
# Simplified commands for deployment workflows integrated with backup system
# Usage: deploy.sh <command> [options]

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/common.sh"

# Initialize backup system
init_backup_system

show_usage() {
    echo "Deployment Helper - Simplified deployment workflows"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Setup Commands:"
    echo "  set-context <env> <ticket> [post] [pre]  Set deployment context (creates env vars)"
    echo "                                        Example: deploy.sh set-context prod USAGOV-1234"
    echo "                                        Optional: deploy.sh set-context prod USAGOV-1234 post-deploy pre-deploy"
    echo "                                        Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX"
    echo ""
    echo "  show-context                          Show current deployment context"
    echo ""
    echo "Status Commands:"
    echo "  last-backup                           Show when last backup of each type was taken"
    echo "  status                                Show CF target and recent activity"
    echo "  motd                                  Show message of the day from CMS container"
    echo "  changes [from] [to]                   Show tickets between two commits/branches"
    echo "                                        Default: from=prod, to=stage"
    echo "                                        Uses DEPLOY_ENV if set (e.g., stage vs prod)"
    echo ""
    echo "Deployment Backup Commands:"
    echo "  pre-deploy                            Create pre-deployment backup using DEPLOY_PRE_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo "  post-deploy                           Create post-deployment backup using DEPLOY_POST_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo ""
    echo "Rollback Commands:"
    echo "  list-backups [days]                   List recent backups for rollback (default: 7 days)"
    echo "  rollback [tag]                        Restore from backup (all types, with confirmation)"
    echo "                                        Uses separate tags per type if set via set-context"
    echo "                                        Or specify tag to use same tag for all types"
    echo "  rollback-static [tag]                 Restore static site only (with confirmation)"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_STATIC_TAG is set"
    echo "  rollback-db [tag]                     Restore database only (with confirmation)"
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
    local post_suffix="${3:-post-deploy}"
    local pre_suffix="${4:-pre-deploy}"

    if [ -z "$env" ] || [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Environment and ticket required"
        echo "Usage: deploy.sh set-context <env> <ticket> [post-suffix] [pre-suffix]"
        exit 1
    fi

    print_status $BLUE "🔍 Capturing most recent backup tags for rollback..."

    # Query S3 to get the most recent valid backup tag for each type
    local backup_tags=$(cf ssh cms -c "cd /var/www && . scripts/snapshot/common.sh && init_backup_system && setup_s3_vars && \
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
    if [ -n "\$DEPLOY_ENV" ] || [ -n "\$DEPLOY_TICKET" ] || [ -n "\$DEPLOY_PRE_SUFFIX" ] || [ -n "\$DEPLOY_POST_SUFFIX" ]; then
        echo "  DEPLOY_ENV=\${DEPLOY_ENV:-(not set)}"
        echo "  DEPLOY_TICKET=\${DEPLOY_TICKET:-(not set)}"
        echo "  DEPLOY_PRE_SUFFIX=\${DEPLOY_PRE_SUFFIX:-(not set)}"
        echo "  DEPLOY_POST_SUFFIX=\${DEPLOY_POST_SUFFIX:-(not set)}"
        echo ""
        echo "Rollback tags:"
        echo "  DEPLOY_ROLLBACK_STATIC_TAG=\${DEPLOY_ROLLBACK_STATIC_TAG:-(not set)}"
        echo "  DEPLOY_ROLLBACK_PUBLIC_TAG=\${DEPLOY_ROLLBACK_PUBLIC_TAG:-(not set)}"
        echo "  DEPLOY_ROLLBACK_DB_TAG=\${DEPLOY_ROLLBACK_DB_TAG:-(not set)}"
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

    cf ssh cms -c 'cd /var/www && . scripts/snapshot/common.sh && init_backup_system && setup_s3_vars &&
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

# Pre-deployment backup using context variables
pre_deploy() {
    local ticket="${DEPLOY_TICKET:-}"
    local suffix="${DEPLOY_PRE_SUFFIX:-pre-deploy}"

    if [ -z "$ticket" ]; then
        print_status $RED "❌ Error: DEPLOY_TICKET not set"
        echo "Run: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $BLUE "📦 Creating pre-deployment backup"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all $ticket $suffix"
}

# Post-deployment backup using context variables
post_deploy() {
    local ticket="${DEPLOY_TICKET:-}"
    local suffix="${DEPLOY_POST_SUFFIX:-post-deploy}"

    if [ -z "$ticket" ]; then
        print_status $RED "❌ Error: DEPLOY_TICKET not set"
        echo "Run: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $BLUE "📦 Creating post-deployment backup"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all $ticket $suffix"
}

# Quick snapshot with auto-generated suffix
snapshot() {
    local suffix="${1:-$(date +%H%M)}"

    print_status $BLUE "📸 Creating snapshot"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all SNAPSHOT $suffix"
}

# Quick database snapshot
snapshot_db() {
    local suffix="${1:-$(date +%H%M)}"

    print_status $BLUE "💾 Creating database snapshot"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup db SNAPSHOT $suffix"
}

# List backups for rollback
list_backups() {
    local days="${1:-7}"

    print_status $BLUE "📋 Available backups (last $days days)"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh list all $days"
}

# Rollback to a specific backup (with confirmation)
rollback() {
    # Allow explicit tag override, otherwise use context
    local static_tag="${1:-$DEPLOY_ROLLBACK_STATIC_TAG}"
    local public_tag="${1:-$DEPLOY_ROLLBACK_PUBLIC_TAG}"
    local db_tag="${1:-$DEPLOY_ROLLBACK_DB_TAG}"

    # If user provided a tag, use it for all types
    if [ -n "$1" ]; then
        static_tag="$1"
        public_tag="$1"
        db_tag="$1"
    fi

    if [ -z "$static_tag" ] || [ -z "$public_tag" ] || [ -z "$db_tag" ]; then
        print_status $RED "❌ Error: Backup tags required"
        echo "Usage: deploy.sh rollback <tag>"
        echo "Or set deployment context first: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $YELLOW "⚠️  ROLLBACK: This will restore all backup types (static, public, database)"
    echo "Tags:"
    echo "  Static: $static_tag"
    echo "  Public: $public_tag"
    echo "  DB: $db_tag"
    echo ""
    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        exit 0
    fi

    echo ""
    print_status $BLUE "Restoring static site..."
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $static_tag --only=static"

    print_status $BLUE "Restoring public files..."
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $public_tag --only=public"

    print_status $BLUE "Restoring database..."
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $db_tag --only=db"
}

# Rollback static site only (with confirmation)
rollback_static() {
    local tag="${1:-$DEPLOY_ROLLBACK_STATIC_TAG}"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-static <tag>"
        echo "Or set deployment context first: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $YELLOW "⚠️  ROLLBACK: This will restore static site only"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""
    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        exit 0
    fi

    echo ""
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag --only=static"
}

# Rollback database only (with confirmation)
rollback_db() {
    local tag="${1:-$DEPLOY_ROLLBACK_DB_TAG}"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-db <tag>"
        echo "Or set deployment context first: deploy.sh set-context <env> <ticket>"
        exit 1
    fi

    print_status $YELLOW "⚠️  ROLLBACK: This will restore database only"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""
    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        exit 0
    fi

    echo ""
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag --only=db"
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
    "pre-deploy")
        pre_deploy
        ;;
    "post-deploy")
        post_deploy
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
