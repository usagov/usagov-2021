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
    echo "  set-context <env> <ticket>            Set deployment context (creates env vars)"
    echo "                                        Example: deploy.sh set-context prod USAGOV-1234"
    echo "                                        Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX"
    echo ""
    echo "  show-context                          Show current deployment context"
    echo ""
    echo "Status Commands:"
    echo "  last-backup                           Show when last backup of each type was taken"
    echo "  status                                Show CF target and recent activity"
    echo ""
    echo "Deployment Backup Commands:"
    echo "  pre-deploy                            Create pre-deployment backup using DEPLOY_PRE_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo "  post-deploy                           Create post-deployment backup using DEPLOY_POST_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo ""
    echo "Rollback Commands:"
    echo "  list-backups [days]                   List recent backups for rollback (default: 7 days)"
    echo "  rollback <tag>                        Restore from backup (all types, with confirmation)"
    echo "  rollback-static <tag>                 Restore static site only (with confirmation)"
    echo "  rollback-db <tag>                     Restore database only (with confirmation)"
    echo ""
    echo "Quick Backup Commands:"
    echo "  snapshot [suffix]                     Quick backup with auto-generated tag"
    echo "  snapshot-db [suffix]                  Quick database-only backup"
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
    local suffix="${3:-post-deploy}"

    if [ -z "$env" ] || [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Environment and ticket required"
        echo "Usage: deploy.sh set-context <env> <ticket> [suffix]"
        exit 1
    fi

    # Export variables for this session
    export DEPLOY_ENV="$env"
    export DEPLOY_TICKET="$ticket"
    export DEPLOY_SUFFIX="$suffix"

    print_status $GREEN "✅ Deployment context set"
    echo ""
    echo "Environment variables:"
    echo "  DEPLOY_ENV=$DEPLOY_ENV"
    echo "  DEPLOY_TICKET=$DEPLOY_TICKET"
    echo "  DEPLOY_SUFFIX=$DEPLOY_SUFFIX"
    echo ""
    print_status $YELLOW "💡 To use these in your current shell, run:"
    echo ""
    echo "export DEPLOY_ENV='$env'"
    echo "export DEPLOY_TICKET='$ticket'"
    echo "export DEPLOY_SUFFIX='$suffix'"
}

# Show current deployment context
show_context() {
    print_status \$BLUE "📋 Current Deployment Context"
    echo ""
    if [ -n "\$DEPLOY_ENV" ] || [ -n "\$DEPLOY_TICKET" ] || [ -n "\$DEPLOY_PRE_SUFFIX" ] || [ -n "\$DEPLOY_POST_SUFFIX" ]; then
        echo "  DEPLOY_ENV=\${DEPLOY_ENV:-(not set)}"
        echo "  DEPLOY_TICKET=\${DEPLOY_TICKET:-(not set)}"
        echo "  DEPLOY_PRE_SUFFIX=\${DEPLOY_PRE_SUFFIX:-(not set)}"
        echo "  DEPLOY_POST_SUFFIX=\${DEPLOY_POST_SUFFIX:-(not set)}"
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
    local tag="$1"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback <tag>"
        exit 1
    fi

    print_status $YELLOW "⚠️  ROLLBACK: This will restore all backup types (static, public, database)"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""
    printf "Continue with rollback? (y/N): "
    read -r confirmation

    if [ "$confirmation" != "y" ] && [ "$confirmation" != "Y" ]; then
        print_status $GREEN "❌ Rollback cancelled"
        exit 0
    fi

    echo ""
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag"
}

# Rollback static site only (with confirmation)
rollback_static() {
    local tag="$1"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-static <tag>"
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
    local tag="$1"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-db <tag>"
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

ccb() {
    COMMIT_A=${1:-stage}
    COMMIT_B=${2:-prod}

    if ! git cat-file -t $COMMIT_A > /dev/null; then
    echo "Commit '$COMMIT_A' not found in this repo"
    exit 1
    fi
    if ! git cat-file -t $COMMIT_B > /dev/null; then
    echo "Commit '$COMMIT_B' not found in this repo"
    exit 1
    fi

    # reorder the inputs by commit time
    COMMITS_FROM_A=$(git show -s --format="%ct %S" $COMMIT_A)
    COMMITS_FROM_B=$(git show -s --format="%ct %S" $COMMIT_B)
    COMMITS_SORTED=$( { echo "$COMMITS_FROM_A" & echo "$COMMITS_FROM_B"; } | sort -n | awk '{print $2}' );

    NEWEST=$(echo "$COMMITS_SORTED" | head -n 1)
    OLDEST=$(echo "$COMMITS_SORTED" | tail -n 1)

    if [ "$NEWEST" == "$OLDEST" ]; then
        echo "Commits are the same $NEWEST..$OLDEST"
        exit 0
    fi

    echo
    echo "Tickets between $NEWEST <-> $OLDEST"
    echo

    # the name of the repo shows up as a ticket so we have to explicitly ignore it
    # branch names have been sloppy so accept anything from "Usa 123" to "usa_123" to "USAGOV-123"
    # be careful because [-_ 	] has a space and a tab inside of the brackets
    COMMITS_BETWEEN=$(git log ${NEWEST}..${OLDEST})
    if [ -z "$COMMITS_BETWEEN" ]; then
    echo "  ... Same commits"
    else
    echo $COMMITS_BETWEEN | grep -Eio 'usa(gov)?[-_ 	]([0-9]+)' | sed -E 's/usa(gov)?[-_ 	]([0-9]+)/USAGOV-\2/ig' | grep -iv usagov-2021 | sort -u
    fi
    echo
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
