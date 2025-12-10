#!/bin/sh

# Deployment Helper Script
# Simplified commands for common deployment workflows integrated with backup system
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
    echo "Pre-Deployment Commands:"
    echo "  pre-deploy [env] [ticket] [suffix]    Create pre-deployment backup"
    echo "                                        Example: deploy.sh pre-deploy prod USAGOV-1234 before-deploy"
    echo ""
    echo "Post-Deployment Commands:"
    echo "  post-deploy [env] [ticket] [suffix]   Create post-deployment backup"
    echo "                                        Example: deploy.sh post-deploy prod USAGOV-1234 after-deploy"
    echo ""
    echo "Deployment Verification:"
    echo "  verify [env]                          Check deployment health and recent backups"
    echo ""
    echo "Rollback Commands:"
    echo "  list-rollback [env] [days]            List recent backups for rollback (default: 7 days)"
    echo "  rollback [env] <tag>                  Restore from backup (interactive)"
    echo "  rollback-static [env] <tag>           Restore static site only"
    echo "  rollback-db [env] <tag>               Restore database only"
    echo ""
    echo "Quick Backup Commands:"
    echo "  snapshot [env] [suffix]               Quick backup with auto-generated tag"
    echo "  snapshot-db [env] [suffix]            Quick database-only backup"
    echo ""
    echo "Environment Management:"
    echo "  switch <env>                          Switch cf target to environment"
    echo "  status                                Show current cf target and recent activity"
    echo ""
    echo "Examples:"
    echo "  $0 pre-deploy prod USAGOV-1234 before-deploy"
    echo "  $0 verify prod"
    echo "  $0 list-rollback prod 3"
    echo "  $0 rollback prod AUTO-prod-14855-2025-12-08-0"
    echo "  $0 snapshot prod emergency-fix"
    echo ""
}

# Get environment name (prod/stage/dev)
get_env() {
    local env="${1:-}"
    if [ -z "$env" ]; then
        # Try to detect from cf target
        env=$(cf target | grep "space:" | awk '{print $2}')
    fi
    echo "$env"
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

# Pre-deployment backup
pre_deploy() {
    local env=$(get_env "$1")
    local ticket="${2:-}"
    local suffix="${3:-pre-deploy}"

    if [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Ticket number required"
        echo "Usage: deploy.sh pre-deploy [env] <ticket> [suffix]"
        exit 1
    fi

    print_status $BLUE "📦 Creating pre-deployment backup for $env"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    # Use manager.sh backup command via cf ssh
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all $ticket $suffix"
}

# Post-deployment backup
post_deploy() {
    local env=$(get_env "$1")
    local ticket="${2:-}"
    local suffix="${3:-post-deploy}"

    if [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Ticket number required"
        echo "Usage: deploy.sh post-deploy [env] <ticket> [suffix]"
        exit 1
    fi

    print_status $BLUE "📦 Creating post-deployment backup for $env"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    # Use manager.sh backup command via cf ssh
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all $ticket $suffix"
}

# Quick snapshot with auto-generated suffix
snapshot() {
    local env=$(get_env "$1")
    local suffix="${2:-$(date +%H%M)}"

    print_status $BLUE "📸 Creating snapshot for $env"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup all SNAPSHOT $suffix"
}

# Quick database snapshot
snapshot_db() {
    local env=$(get_env "$1")
    local suffix="${2:-$(date +%H%M)}"

    print_status $BLUE "💾 Creating database snapshot for $env"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh backup db SNAPSHOT $suffix"
}

# List backups for rollback
list_rollback() {
    local env=$(get_env "$1")
    local days="${2:-7}"

    print_status $BLUE "📋 Available backups for rollback (last $days days)"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh list all $days"
}

# Verify deployment health
verify_deploy() {
    local env=$(get_env "$1")

    print_status $BLUE "🔍 Verifying $env deployment"
    echo ""

    echo "=== App Status ==="
    cf app cms
    echo ""

    echo "=== Recent Backups ==="
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh list all 1"
    echo ""

    echo "=== Recent Events ==="
    cf events cms | head -10
}

# Rollback to a specific backup
rollback() {
    local env=$(get_env "$1")
    local tag="$2"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback [env] <tag>"
        exit 1
    fi

    print_status $YELLOW "⚠️  Rollback: This will restore from backup"
    print_status $YELLOW "Environment: $env"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag"
}

# Rollback static site only
rollback_static() {
    local env=$(get_env "$1")
    local tag="$2"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-static [env] <tag>"
        exit 1
    fi

    print_status $YELLOW "⚠️  Rolling back static site only"
    print_status $YELLOW "Environment: $env"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag --only=static"
}

# Rollback database only
rollback_db() {
    local env=$(get_env "$1")
    local tag="$2"

    if [ -z "$tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        echo "Usage: deploy.sh rollback-db [env] <tag>"
        exit 1
    fi

    print_status $YELLOW "⚠️  Rolling back database only"
    print_status $YELLOW "Environment: $env"
    print_status $YELLOW "Backup Tag: $tag"
    echo ""

    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh restore $tag --only=db"
}

# Main command dispatcher
COMMAND="${1:-}"

case "$COMMAND" in
    "pre-deploy")
        shift
        pre_deploy "$@"
        ;;
    "post-deploy")
        shift
        post_deploy "$@"
        ;;
    "verify")
        shift
        verify_deploy "$@"
        ;;
    "list-rollback")
        shift
        list_rollback "$@"
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
    "status")
        show_status
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
