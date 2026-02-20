#!/bin/sh

# Deployment Helper Script
# Simplified commands for deployment workflows integrated with backup system
# Usage: deploy.sh <command> [options]

# Set restrictive permissions for all created files
umask 077

# Load common utilities
SCRIPT_DIR=$(dirname "$0")
. "$SCRIPT_DIR/../common.sh"

# Initialize backup system
init_backup_system

# ===================================================================
# FORMATTING SERVICE
# ===================================================================

# Check for --json flag in arguments
has_json_flag() {
    for arg in "$@"; do
        if [ "$arg" = "--json" ]; then
            return 0
        fi
    done
    return 1
}

# Format data as JSON
# Usage: format_json <json_data>
format_json() {
    local json_data="$1"
    echo "$json_data" | jq .
}

# ===================================================================
# VALIDATION AND SETUP
# ===================================================================

# Validate app name against whitelist
validate_app_name() {
    local app_name="$1"

    # Get allowed app names from config, default to cms www waf
    local allowed_apps="${ALLOWED_APP_NAMES:-cms}"

    # Check if app name is in the allowed list
    for allowed in $allowed_apps; do
        if [ "$app_name" = "$allowed" ]; then
            return 0
        fi
    done

    # Not found in whitelist
    print_status $RED "❌ Error: Invalid app name: $app_name"
    print_status $YELLOW "   Valid apps: $allowed_apps"
    print_status $YELLOW "   To add more apps, edit ALLOWED_APP_NAMES in scripts/snapshot/backup-system.conf"
    return 2
}

show_usage() {
    echo "Deployment Helper - Simplified deployment workflows"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Setup Commands:"
    echo "  set-context <env> <ticket> [pre] [post]  Set deployment context (creates env vars)"
    echo "                                        Example: deploy.sh set-context prod USAGOV-1234"
    echo "                                        Optional: deploy.sh set-context prod USAGOV-1234 pre-deploy post-deploy"
    echo "                                        From tag: deploy.sh set-context --from-tag=USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
    echo "                                        Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX"
    echo ""
    echo "  show-context                          Show current deployment context"
    echo "  clear-context                         Clear all deployment context variables"
    echo "  contexts list [limit]                 Show recently used contexts (default: 10)"
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
    echo "  digests current [space]               Show what's CURRENTLY RUNNING in environment"
    echo "                                        Use: Verify deployment, check live state, see what backup captures"
    echo "  digests build [env]                   Show what was BUILT in latest CircleCI build"
    echo "                                        Use: Get digests to deploy a specific build (defaults to current space)"
    echo "  digests history [env] [days] [limit]  Show deployment history with digests"
    echo "                                        Flags: --backups-only, --git-only, --json, --show-all-history"
    echo ""
    echo "Deployment Commands (DESTRUCTIVE):"
    echo "  push <name> <build> [digest] [--skip-validation]"
    echo "                                        🔥 DESTRUCTIVE: Deploy specific app with container digest"
    echo "                                        Example: push cms 5936 gsatts/usagov-2021@sha256:abc..."
    echo "                                        Digest optional if DEPLOY_{APP}_DIGEST set or git tag exists"
    echo "                                        Use --skip-validation to skip space validation"
    echo ""
    echo "Deployment Backup Commands:"
    echo "  pre-deploy [--skip-validation] [--skip-confirmation]  Create pre-deployment backup using DEPLOY_PRE_SUFFIX"
    echo "                                        Requires: DEPLOY_TICKET"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --skip-validation to skip)"
    echo "  post-deploy [--skip-validation] [--skip-confirmation]  Create post-deployment backup using DEPLOY_POST_SUFFIX"
    echo "                                        Automatically creates annotated git tag for deployment tracking"
    echo "                                        Requires: DEPLOY_TICKET, DEPLOY_ENV"
    echo "                                        Validates CF space matches DEPLOY_ENV (use --skip-validation to skip)"
    echo "  download-backups [tag]                 Download latest backups locally (db/static/public)"
    echo "                                        Default tag: newest backup in current CF space"
    echo ""
    echo "Rollback Commands (DESTRUCTIVE):"
    echo "  list-backups [days]                   List recent backups for rollback (default: 7 days)"
    echo "  rollback [tag] [--apps=...] [--restore=...] [--skip-validation] [--skip-confirmation]"
    echo "                                        🔥 DESTRUCTIVE: Rollback code + optional data"
    echo "                                        Uses backup metadata to fetch container digests"
    echo "                                        tag: Backup tag (optional - uses latest if omitted)"
    echo "                                        --apps: Apps to rollback (default: cms,www,waf)"
    echo "                                        --restore: Data types (db, static, public, all, or comma-separated)"
    echo "                                        Example: rollback                                    # Latest backup, code only"
    echo "                                        Example: rollback AUTO-prod-2025-12-22-0             # Specific backup"
    echo "                                        Example: rollback AUTO-prod-2025-12-22-0 --restore=all"
    echo "  rollback-static [tag] [--skip-validation] [--skip-confirmation]"
    echo "                                        🔥 DESTRUCTIVE: Restore static site data ONLY (no code rollback)"
    echo "                                        Shortcut for data restore without changing deployed containers"
    echo "                                        Tag optional if DEPLOY_ROLLBACK_STATIC_TAG is set"
    echo "  rollback-db [tag] [--skip-validation] [--skip-confirmation]"
    echo "                                        🔥 DESTRUCTIVE: Restore database data ONLY (no code rollback)"
    echo "                                        Shortcut for data restore without changing deployed containers"
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
    echo "  tome-log [--recent]                   Tail the latest running Tome log (--recent shows last 50 lines of most recent log)"
    echo "  state <action> <type> [max_wait_mins] Manage Drupal state (action: enable|disable, type: tome|sm|both)"
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
            echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix] [--from-tag=TAG]"
            echo ""
            echo "Description:"
            echo "  Creates environment variables for a deployment session. This sets up"
            echo "  the context that other commands will use automatically."
            echo "  Automatically saves context to history for 'contexts list' command."
            echo ""
            echo "Arguments:"
            echo "  env          - Environment name (dev, stage, prod, dr)"
            echo "  ticket       - JIRA ticket number (e.g., USAGOV-1234)"
            echo "  pre-suffix   - Optional pre-deployment backup suffix (default: 'pre-deploy')"
            echo "  post-suffix  - Optional post-deployment backup suffix (default: 'post-deploy')"
            echo ""
            echo "Options:"
            echo "  --from-tag=TAG  - Extract env/ticket from backup tag"
            echo "  --export        - Output export commands for eval"
            echo ""
            echo "Examples:"
            echo "  deploy.sh set-context prod USAGOV-1234"
            echo "  deploy.sh set-context stage USAGOV-5678 pre-deploy post-deploy"
            echo "  deploy.sh set-context --from-tag=USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
            echo "  eval \$(deploy.sh set-context prod USAGOV-1234 --export)"
            echo ""
            ;;
        "clear-context")
            echo "Clear Deployment Context"
            echo ""
            echo "Usage: deploy.sh clear-context [--export]"
            echo ""
            echo "Description:"
            echo "  Clears all deployment context environment variables."
            echo ""
            echo "Options:"
            echo "  --export  - Output unset commands for eval"
            echo ""
            echo "Examples:"
            echo "  deploy.sh clear-context"
            echo "  eval \$(deploy.sh clear-context --export)"
            echo ""
            ;;
        "contexts")
            echo "List Recent Deployment Contexts"
            echo ""
            echo "Usage: deploy.sh contexts list [limit] [--json] [--limit=N]"
            echo ""
            echo "Description:"
            echo "  Shows recently used deployment contexts from history."
            echo "  Highlights currently active context if set."
            echo ""
            echo "Arguments:"
            echo "  limit  - Number of contexts to show (default: 10)"
            echo ""
            echo "Options:"
            echo "  --json      - Output as JSON"
            echo "  --limit=N   - Limit results to N contexts"
            echo ""
            echo "Examples:"
            echo "  deploy.sh contexts list"
            echo "  deploy.sh contexts list 20"
            echo "  deploy.sh contexts list --json"
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
            echo "Usage: deploy.sh last-backup [--json]"
            echo ""
            echo "Description:"
            echo "  Shows when each type of backup (db, static, public) was last taken."
            echo "  Helps determine if backups are current before deployment."
            echo ""
            echo "Options:"
            echo "  --json  - Output as JSON"
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
        "digests")
            # Check if subcommand help requested
            if [ "$2" = "current" ]; then
                echo "Show Currently Running Container Digests"
                echo ""
                echo "Usage: deploy.sh digests current [space] [--json]"
                echo ""
                echo "Description:"
                echo "  Shows what container digests are CURRENTLY RUNNING in the environment."
                echo "  Source: Cron bucket file updated every 5 minutes by automated capture."
                echo ""
                echo "When to use this:"
                echo "  • Verify a deployment worked (check if new digest is running)"
                echo "  • See what's actually deployed right now"
                echo "  • Check what backup metadata would contain"
                echo "  • Compare running state across environments"
                echo ""
                echo "Arguments:"
                echo "  space - Optional space name (dr, stage, prod). Defaults to current space."
                echo "          If different from current, will switch spaces temporarily."
                echo "  --json - Output in JSON format"
                echo ""
                echo "Examples:"
                echo "  deploy.sh digests current        # Show what's running in current space"
                echo "  deploy.sh digests current stage  # Show what's running in stage"
                echo "  deploy.sh digests current --json # Show current digests in JSON format"
                echo ""
            elif [ "$2" = "build" ]; then
                echo "Show CircleCI Build Information"
                echo ""
                echo "Usage: deploy.sh digests build [env] [--json]"
                echo ""
                echo "Description:"
                echo "  Shows container digests from the latest CircleCI BUILD for an environment."
                echo "  Source: Annotated git tags created by CircleCI pipeline."
                echo "  Shows: CMS, WAF, WWW containers from that build (not cron/analytics)."
                echo ""
                echo "When to use this:"
                echo "  • Get container digests to deploy a specific CircleCI build"
                echo "  • See what was built in the latest pipeline run"
                echo "  • Find the build number and digests for deployment commands"
                echo ""
                echo "Arguments:"
                echo "  env    - Environment (dev, stage, prod, dr). Defaults to current space."
                echo "  --json - Output in JSON format"
                echo ""
                echo "Examples:"
                echo "  deploy.sh digests build          # Show latest build for current space"
                echo "  deploy.sh digests build prod     # Show latest build for prod"
                echo "  deploy.sh digests build --json   # Show build info in JSON format"
                echo "  # Then use the digests shown to deploy:"
                echo "  deploy.sh push cms 12034 @sha256:abc..."
                echo ""
            else
                # General digests help
                echo "Container Digest Commands"
                echo ""
                echo "Usage: deploy.sh digests <subcommand> [options]"
                echo ""
                echo "Subcommands:"
                echo "  current [space]               Show what's CURRENTLY RUNNING"
                echo "                                Use: Verify deployment, check live state"
                echo ""
                echo "  build [env]                   Show what was BUILT in latest CircleCI build"
                echo "                                Use: Get digests to deploy (defaults to current space)"
                echo ""
                echo "  history [env] [days] [limit]  Show deployment history"
                echo "                                Flags: --backups-only, --git-only, --json"
                echo ""
                echo "Examples:"
                echo "  deploy.sh digests current"
                echo "  deploy.sh digests build prod"
                echo "  deploy.sh digests history prod 7"
                echo ""
                echo "Get detailed help:"
                echo "  deploy.sh digests current -h"
                echo "  deploy.sh digests build -h"
                echo ""
            fi
            ;;
        "push")
            echo "Push Application Deployment"
            echo ""
            echo "Usage: deploy.sh push <name> <build> [digest] [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE & REQUIRES CONFIRMATION: Deploy a specific application with container digest."
            echo "  Updates the app to use a specific container image."
            echo ""
            echo "Arguments:"
            echo "  name                 - App name (apps in ALLOWED_APP_NAMES)"
            echo "  build                - CCI build number (technically arbitrary, but should match git tag in actual deployments)"
            echo "  digest               - Container digest (optional if DEPLOY_{APP}_DIGEST set)"
            echo "  --skip-validation    - Skip space validation"
            echo "  --skip-confirmation  - Skip confirmation prompt"
            echo ""
            echo "Examples:"
            echo "  deploy.sh push cms 5936 gsatts/usagov-2021@sha256:abc123..."
            echo "  deploy.sh push www 5936"
            echo "  deploy.sh push cron 5936 gsatts/usagov-2021@sha256:def456..."
            echo ""
            ;;
        "pre-deploy")
            echo "Pre-Deployment Backup"
            echo ""
            echo "Usage: deploy.sh pre-deploy [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 REQUIRES CONFIRMATION: Creates a pre-deployment backup using DEPLOY_PRE_SUFFIX."
            echo "  Validates CF space matches DEPLOY_ENV."
            echo ""
            echo "Options:"
            echo "  --skip-validation    - Skip space validation"
            echo "  --skip-confirmation  - Skip confirmation prompt"
            echo ""
            echo "Requires: DEPLOY_TICKET environment variable"
            echo ""
            ;;
        "post-deploy")
            echo "Post-Deployment Backup"
            echo ""
            echo "Usage: deploy.sh post-deploy [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 REQUIRES CONFIRMATION: Creates a post-deployment backup and annotated git tag."
            echo "  Git tag includes CCI build and container digests for tracking."
            echo ""
            echo "Options:"
            echo "  --skip-validation    - Skip space validation"
            echo "  --skip-confirmation  - Skip confirmation prompt"
            echo ""
            echo "Requires: DEPLOY_TICKET, DEPLOY_ENV environment variables"
            echo ""
            ;;
        "list-backups")
            echo "List Available Backups"
            echo ""
            echo "Usage: deploy.sh list-backups [days] [--json]"
            echo ""
            echo "Description:"
            echo "  Lists recent backups available for rollback."
            echo ""
            echo "Arguments:"
            echo "  days  - Show backups from last N days (default: 7)"
            echo ""
            echo "Options:"
            echo "  --json  - Output as JSON"
            echo ""
            echo "Example:"
            echo "  deploy.sh list-backups 14"
            echo "  deploy.sh list-backups --json"
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
            echo "  --json                Output in JSON format"
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
            echo "Usage: deploy.sh rollback [backup-tag] [--apps=cms,www,waf] [--restore=db,static,public,all] [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Rollback code and optionally data."
            echo "  Uses backup tags to automatically fetch container digests from metadata."
            echo "  If no backup tag provided, uses the most recent backup."
            echo ""
            echo "Arguments:"
            echo "  backup-tag              - Backup tag (optional - uses latest if omitted)"
            echo "  --apps=cms,www,waf      - Apps to rollback (default: cms,www,waf)"
            echo "  --restore=types         - Data types to restore: db, static, public, all, or comma-separated"
            echo "                            'all' restores all data types (db, static, public)"
            echo "  --skip-validation       - Skip space validation"
            echo "  --skip-confirmation     - Skip production confirmation prompt"
            echo ""
            echo "Examples:"
            echo "  deploy.sh rollback                                    # Latest backup, code only"
            echo "  deploy.sh rollback AUTO-prod-2025-12-22-0             # Specific backup, code only"
            echo "  deploy.sh rollback AUTO-prod-2025-12-22-0 --restore=db,static  # With data restore"
            echo "  deploy.sh rollback AUTO-prod-2025-12-22-0 --restore=all  # Restore all data types"
            echo ""
            ;;
        "rollback-static")
            echo "Restore Static Site Data (No Code Rollback)"
            echo ""
            echo "Usage: deploy.sh rollback-static [tag] [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Restore static site data ONLY without changing deployed code."
            echo "  This is a shortcut for restoring data without rolling back container versions."
            echo "  Deployed containers (cms, www, waf) remain unchanged."
            echo ""
            echo "  To rollback both code AND data, use: deploy.sh rollback <tag> --restore=static"
            echo ""
            echo "Arguments:"
            echo "  tag                   - Backup tag (optional if DEPLOY_ROLLBACK_STATIC_TAG set)"
            echo "  --skip-validation     - Skip space validation"
            echo "  --skip-confirmation   - Skip production confirmation prompt"
            echo ""
            ;;
        "rollback-db")
            echo "Restore Database Data (No Code Rollback)"
            echo ""
            echo "Usage: deploy.sh rollback-db [tag] [--skip-validation] [--skip-confirmation]"
            echo ""
            echo "Description:"
            echo "  🔥 DESTRUCTIVE: Restore database data ONLY without changing deployed code."
            echo "  This is a shortcut for restoring data without rolling back container versions."
            echo "  Deployed containers (cms, www, waf) remain unchanged."
            echo ""
            echo "  To rollback both code AND data, use: deploy.sh rollback <tag> --restore=db"
            echo ""
            echo "Arguments:"
            echo "  tag                   - Backup tag (optional if DEPLOY_ROLLBACK_DB_TAG set)"
            echo "  --skip-validation     - Skip space validation"
            echo "  --skip-confirmation   - Skip production confirmation prompt"
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
            echo "Usage: deploy.sh download-backups [tag] [--json] [--output-dir=<path>]"
            echo ""
            echo "Description:"
            echo "  Downloads db/static/public backups to the current directory."
            echo "  If no tag is provided, the newest backup for the current CF space is used."
            echo ""
            echo "Options:"
            echo "  --json              - Output as JSON"
            echo "  --output-dir=<path> - Output directory (default: current directory)"
            echo ""
            echo "Example:"
            echo "  deploy.sh download-backups"
            echo "  deploy.sh download-backups AUTO-prod-2025-12-22-0"
            echo "  deploy.sh download-backups --json"
            echo ""
            ;;
        "tome-log")
            echo "Tail Latest Tome Log"
            echo ""
            echo "Usage: deploy.sh tome-log [--recent]"
            echo ""
            echo "Description:"
            echo "  Finds the newest running Tome log on the CMS container and tails it."
            echo "  Skips blocked logs ('already running') and completed logs."
            echo "  Stops automatically when Tome finishes or reports no changes."
            echo ""
            echo "Options:"
            echo "  --recent    Show last 50 lines of most recent non-blocked log (running or completed)"
            echo ""
            echo "Matches stop on:"
            echo "  'Tome static build looks fine', 'No changes detected', 'no changes', 'SYNC FINISHED'"
            echo ""
            ;;
        "state")
            echo "Manage Drupal State"
            echo ""
            echo "Usage: deploy.sh state <action> <type> [max_wait_mins]"
            echo ""
            echo "Description:"
            echo "  Enable or disable Drupal state management for backups/maintenance."
            echo "  Runs inside the CMS container."
            echo ""
            echo "Arguments:"
            echo "  action         - 'enable' or 'disable'"
            echo "  type           - 'tome', 'sm' (site maintenance), or 'both' (default)"
            echo "  max_wait_mins  - Maximum minutes to wait for Tome (default: 25, only used with disable)"
            echo ""
            echo "Examples:"
            echo "  deploy.sh state disable tome 30"
            echo "  deploy.sh state enable tome"
            echo "  deploy.sh state disable both"
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
            echo "  --json            - Output as JSON"
            echo ""
            echo "Examples:"
            echo "  deploy.sh validate"
            echo "  deploy.sh validate --only=cms --skip-http"
            echo "  deploy.sh validate --json"
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
    local export_only=false
    local env=""
    local ticket=""
    local pre_suffix="pre-deploy"
    local post_suffix="post-deploy"
    local from_tag=""

    # Parse arguments with while loop for proper flag and positional handling
    while [ $# -gt 0 ]; do
        case "$1" in
            --export)
                export_only=true
                shift
                ;;
            --from-tag=*)
                from_tag="${1#*=}"
                shift
                ;;
            --)
                shift
                break
                ;;
            -*)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix] [--export] [--from-tag=TAG]"
                exit 2
                ;;
            *)
                # First positional is env, second is ticket, third is pre_suffix, fourth is post_suffix
                if [ -z "$env" ]; then
                    env="$1"
                elif [ -z "$ticket" ]; then
                    ticket="$1"
                elif [ "$pre_suffix" = "pre-deploy" ]; then
                    pre_suffix="$1"
                elif [ "$post_suffix" = "post-deploy" ]; then
                    post_suffix="$1"
                else
                    print_status $RED "❌ Too many arguments"
                    echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix] [--export] [--from-tag=TAG]"
                    exit 2
                fi
                shift
                ;;
        esac
    done

    # If --from-tag provided, parse it to extract env and ticket
    if [ -n "$from_tag" ]; then
        if [ "$export_only" = "false" ]; then
            print_status $BLUE "🔍 Parsing backup tag: $from_tag"
        fi

        local parsed
        parsed=$(parse_backup_tag "$from_tag")

        if [ $? -ne 0 ] || [ -z "$parsed" ]; then
            handle_error "Could not parse backup tag: $from_tag" "validation" "exit"
        fi

        ticket=$(echo "$parsed" | cut -d'|' -f1)
        env=$(echo "$parsed" | cut -d'|' -f2)

        if [ "$export_only" = "false" ]; then
            print_status $GREEN "✅ Extracted: ticket=$ticket, env=$env"
            echo ""
        fi
    fi

    if [ -z "$env" ] || [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Environment and ticket required"
        echo "Usage: deploy.sh set-context <env> <ticket> [pre-suffix] [post-suffix] [--export] [--from-tag=TAG]"
        echo ""
        echo "Examples:"
        echo "  deploy.sh set-context prod USAGOV-1234"
        echo "  deploy.sh set-context --from-tag=USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
        exit 2
    fi

    # Validate ticket format (basic check)
    if ! validate_backup_tag "$ticket"; then
        handle_error "Invalid ticket format" "validation" "exit"
    fi

    # Validate suffix formats
    if ! validate_backup_tag "$pre_suffix"; then
        handle_error "Invalid pre-suffix format" "validation" "exit"
    fi

    if ! validate_backup_tag "$post_suffix"; then
        handle_error "Invalid post-suffix format" "validation" "exit"
    fi

    if [ "$export_only" = "false" ]; then
        print_status $BLUE "🔍 Capturing most recent backup tags for rollback..."
    fi

    # Query S3 to get the most recent valid backup tag for each type
    local backup_tags=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars && \
        echo 'STATIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_STATIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'PUBLIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_PUBLIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'DB:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep '\.sql\.gz$' | sort -r | head -1 | awk '{print \$4}' | sed 's/\.sql\.gz$//'")

    local static_tag=$(echo "$backup_tags" | grep -A1 "^STATIC:" | tail -1)
    local public_tag=$(echo "$backup_tags" | grep -A1 "^PUBLIC:" | tail -1)
    local db_tag=$(echo "$backup_tags" | grep -A1 "^DB:" | tail -1)

    # If --export flag, output only export commands for eval
    if [ "$export_only" = "true" ]; then
        echo "export DEPLOY_ENV='$env'"
        echo "export DEPLOY_TICKET='$ticket'"
        echo "export DEPLOY_PRE_SUFFIX='$pre_suffix'"
        echo "export DEPLOY_POST_SUFFIX='$post_suffix'"
        echo "export DEPLOY_ROLLBACK_STATIC_TAG='$static_tag'"
        echo "export DEPLOY_ROLLBACK_PUBLIC_TAG='$public_tag'"
        echo "export DEPLOY_ROLLBACK_DB_TAG='$db_tag'"
        return
    fi

    # Export variables for this session (only affects this script execution)
    export DEPLOY_ENV="$env"
    export DEPLOY_TICKET="$ticket"
    export DEPLOY_PRE_SUFFIX="$pre_suffix"
    export DEPLOY_POST_SUFFIX="$post_suffix"
    export DEPLOY_ROLLBACK_STATIC_TAG="$static_tag"
    export DEPLOY_ROLLBACK_PUBLIC_TAG="$public_tag"
    export DEPLOY_ROLLBACK_DB_TAG="$db_tag"

    # Save context to history for 'contexts list' command
    save_context_to_history "$env" "$ticket" "$pre_suffix" "$post_suffix"

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
    print_status $RED "⚠️  IMPORTANT: These variables are NOT set in your current shell!"
    echo ""
    print_status $YELLOW "To use these variables, you MUST run ONE of the following:"
    echo ""
    echo "Option 1 - Use eval (recommended):"
    echo "  eval \$(scripts/devops/deploy.sh set-context $env $ticket --export)"
    echo ""
    echo "Option 2 - Manually export each variable:"
    echo "  export DEPLOY_ENV='$env'"
    echo "  export DEPLOY_TICKET='$ticket'"
    echo "  export DEPLOY_PRE_SUFFIX='$pre_suffix'"
    echo "  export DEPLOY_POST_SUFFIX='$post_suffix'"
    echo "  export DEPLOY_ROLLBACK_STATIC_TAG='$static_tag'"
    echo "  export DEPLOY_ROLLBACK_PUBLIC_TAG='$public_tag'"
    echo "  export DEPLOY_ROLLBACK_DB_TAG='$db_tag'"
}

# Show current deployment context
show_context() {
    # Check for --json flag
    if has_json_flag "$@"; then
        local deploy_env="${DEPLOY_ENV:-(not set)}"
        local deploy_ticket="${DEPLOY_TICKET:-(not set)}"
        local deploy_pre="${DEPLOY_PRE_SUFFIX:-(not set)}"
        local deploy_post="${DEPLOY_POST_SUFFIX:-(not set)}"
        local rollback_static="${DEPLOY_ROLLBACK_STATIC_TAG:-(not set)}"
        local rollback_public="${DEPLOY_ROLLBACK_PUBLIC_TAG:-(not set)}"
        local rollback_db="${DEPLOY_ROLLBACK_DB_TAG:-(not set)}"

        local has_context=false
        if [ -n "$DEPLOY_ENV" ] || [ -n "$DEPLOY_TICKET" ] || [ -n "$DEPLOY_PRE_SUFFIX" ] || [ -n "$DEPLOY_POST_SUFFIX" ]; then
            has_context=true
        fi

        local json_data=$(cat <<EOF
{
  "deployment_context": {
    "environment": "$deploy_env",
    "ticket": "$deploy_ticket",
    "pre_suffix": "$deploy_pre",
    "post_suffix": "$deploy_post"
  },
  "rollback_tags": {
    "static": "$rollback_static",
    "public": "$rollback_public",
    "database": "$rollback_db"
  },
  "has_context": $has_context
}
EOF
)
        format_json "$json_data"
        return
    fi

    # Default table output
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

# Clear deployment context
clear_context() {
    local export_only=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --export)
                export_only=true
                shift
                ;;
            *)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh clear-context [--export]"
                exit 2
                ;;
        esac
    done

    # If --export flag, output only unset commands for eval
    if [ "$export_only" = "true" ]; then
        echo "unset DEPLOY_ENV"
        echo "unset DEPLOY_TICKET"
        echo "unset DEPLOY_PRE_SUFFIX"
        echo "unset DEPLOY_POST_SUFFIX"
        echo "unset DEPLOY_ROLLBACK_STATIC_TAG"
        echo "unset DEPLOY_ROLLBACK_PUBLIC_TAG"
        echo "unset DEPLOY_ROLLBACK_DB_TAG"
        return
    fi

    # Show current context before clearing
    if [ -n "$DEPLOY_ENV" ] || [ -n "$DEPLOY_TICKET" ]; then
        print_status $YELLOW "🗑️  Clearing deployment context:"
        echo "  Current ENV: ${DEPLOY_ENV:-(not set)}"
        echo "  Current TICKET: ${DEPLOY_TICKET:-(not set)}"
        echo ""
    fi

    # Unset variables for this session
    unset DEPLOY_ENV
    unset DEPLOY_TICKET
    unset DEPLOY_PRE_SUFFIX
    unset DEPLOY_POST_SUFFIX
    unset DEPLOY_ROLLBACK_STATIC_TAG
    unset DEPLOY_ROLLBACK_PUBLIC_TAG
    unset DEPLOY_ROLLBACK_DB_TAG

    print_status $GREEN "✅ Deployment context cleared"
    echo ""
    print_status $RED "⚠️  IMPORTANT: These variables are NOT unset in your current shell!"
    echo ""
    print_status $YELLOW "To clear these variables in your shell, run:"
    echo "  eval \$(scripts/devops/deploy.sh clear-context --export)"
    echo ""
}

# List recently used deployment contexts
list_contexts() {
    local use_json=false
    local limit=10

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            --limit=*)
                limit="${1#*=}"
                shift
                ;;
            *)
                # First non-flag arg is limit
                if [ "$1" -eq "$1" ] 2>/dev/null; then
                    limit="$1"
                else
                    print_status $RED "❌ Unknown option: $1"
                    echo "Usage: deploy.sh contexts list [limit] [--json] [--limit=N]"
                    exit 2
                fi
                shift
                ;;
        esac
    done

    local contexts_file="${HOME}/.deploy-contexts"

    # Check if contexts file exists
    if [ ! -f "$contexts_file" ]; then
        if [ "$use_json" = true ]; then
            echo '{"contexts":[],"message":"No contexts saved yet"}'
            return 0
        else
            print_status $YELLOW "📋 No deployment contexts saved yet"
            echo ""
            echo "Contexts are automatically saved when you use 'set-context'"
            return 0
        fi
    fi

    # Read and sort contexts (most recent first)
    # Use tail + awk for reverse order (tac not available on macOS)
    local contexts=$(tail -n "$limit" "$contexts_file" | awk '{a[i++]=$0} END {for (j=i-1; j>=0;) print a[j--] }')

    # Get current context for highlighting
    local current_env="${DEPLOY_ENV:-}"
    local current_ticket="${DEPLOY_TICKET:-}"

    if [ "$use_json" = true ]; then
        # Build JSON array
        local json_output='{"contexts":['
        local first=true

        while IFS='|' read -r timestamp env ticket pre post; do
            if [ "$first" = true ]; then
                first=false
            else
                json_output="${json_output},"
            fi

            local is_current=false
            if [ "$env" = "$current_env" ] && [ "$ticket" = "$current_ticket" ]; then
                is_current=true
            fi

            json_output="${json_output}{\"timestamp\":\"$timestamp\",\"environment\":\"$env\",\"ticket\":\"$ticket\",\"pre_suffix\":\"$pre\",\"post_suffix\":\"$post\",\"is_current\":$is_current}"
        done <<EOF
$contexts
EOF

        json_output="${json_output}]}"
        format_json "$json_output"
    else
        print_status $BLUE "📋 Recently Used Deployment Contexts (last $limit)"
        echo ""
        printf "%-20s %-8s %-20s %-15s %-15s %s\n" "TIMESTAMP" "ENV" "TICKET" "PRE-SUFFIX" "POST-SUFFIX" "STATUS"
        printf "%-20s %-8s %-20s %-15s %-15s %s\n" "--------------------" "--------" "--------------------" "---------------" "---------------" "------"

        while IFS='|' read -r timestamp env ticket pre post; do
            local status=""
            if [ "$env" = "$current_env" ] && [ "$ticket" = "$current_ticket" ]; then
                status="← CURRENT"
            fi
            printf "%-20s %-8s %-20s %-15s %-15s %s\n" "$timestamp" "$env" "$ticket" "$pre" "$post" "$status"
        done <<EOF
$contexts
EOF
        echo ""
    fi
}

# Helper: Save context to history file
save_context_to_history() {
    local env="$1"
    local ticket="$2"
    local pre_suffix="${3:-pre-deploy}"
    local post_suffix="${4:-post-deploy}"

    local contexts_file="${HOME}/.deploy-contexts"
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")

    # Create file if it doesn't exist
    touch "$contexts_file"

    # Add new context (keep last 100 entries)
    echo "${timestamp}|${env}|${ticket}|${pre_suffix}|${post_suffix}" >> "$contexts_file"

    # Keep only last 100 entries
    if [ -f "$contexts_file" ]; then
        local temp_file="${contexts_file}.tmp"
        tail -n 100 "$contexts_file" > "$temp_file"
        mv "$temp_file" "$contexts_file"
    fi
}

# Helper: Parse backup tag to extract environment and ticket
parse_backup_tag() {
    local tag="$1"

    # Tag format: {ticket}-{env}-{container}-{date}--{suffix}-{sequence}
    # Example: USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0

    # Extract ticket (everything before the second-to-last dash before double dash)
    # This is complex because ticket can contain dashes (e.g., USAGOV-1234)

    # Split on double dash first to isolate the base part
    local base_part=$(echo "$tag" | sed 's/--.*$//')

    # Now we have: USAGOV-1234-prod-12345-2025-12-22
    # We need to extract ticket and env

    # A simpler approach: env is one of known values (dev, stage, prod, dr)
    # Find env in the tag
    local env=""
    local ticket=""

    if echo "$base_part" | grep -q -- "-prod-"; then
        env="prod"
        ticket=$(echo "$base_part" | sed 's/-prod-.*$//')
    elif echo "$base_part" | grep -q -- "-stage-"; then
        env="stage"
        ticket=$(echo "$base_part" | sed 's/-stage-.*$//')
    elif echo "$base_part" | grep -q -- "-dev-"; then
        env="dev"
        ticket=$(echo "$base_part" | sed 's/-dev-.*$//')
    elif echo "$base_part" | grep -q -- "-dr-"; then
        env="dr"
        ticket=$(echo "$base_part" | sed 's/-dr-.*$//')
    else
        # Could not parse
        return 1
    fi

    echo "${ticket}|${env}"
    return 0
}

# Show when last backup of each type was taken
last_backup() {
    local use_json=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            *)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: last-backup [--json]"
                return 2
                ;;
        esac
    done

    # Fetch backup data from S3
    local backup_data
    backup_data=$(cf ssh cms -c 'cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars &&
    latest_static=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_STATIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -n1)
    if [ -n "$latest_static" ]; then
        static_tag=$(echo "$latest_static" | awk "{print \$2}" | tr -d "/")
        static_date=$(echo "$static_tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
    fi

    latest_public=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_PUBLIC_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "PRE" | sort -r | head -n1)
    if [ -n "$latest_public" ]; then
        public_tag=$(echo "$latest_public" | awk "{print \$2}" | tr -d "/")
        public_date=$(echo "$public_tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
    fi

    latest_db=$(aws s3 ls s3://$BUCKET_NAME/$AUTO_DB_BACKUP_PATH/ $S3_EXTRA_PARAMS | grep "\.sql\.gz$" | sort -r | head -n1)
    if [ -n "$latest_db" ]; then
        db_tag=$(echo "$latest_db" | awk "{print \$4}" | sed "s/\.sql\.gz$//")
        db_date=$(echo "$db_tag" | grep -oE "[0-9]{4}-[0-9]{2}-[0-9]{2}" | head -1)
    fi

    echo "STATIC_TAG=${static_tag:-(none)}"
    echo "STATIC_DATE=${static_date:-(none)}"
    echo "PUBLIC_TAG=${public_tag:-(none)}"
    echo "PUBLIC_DATE=${public_date:-(none)}"
    echo "DB_TAG=${db_tag:-(none)}"
    echo "DB_DATE=${db_date:-(none)}"' 2>/dev/null)

    if [ -z "$backup_data" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Could not fetch backup data"}' | jq .
            return 3
        else
            print_status $RED "❌ System Error: Could not fetch backup data"
            return 3
        fi
    fi

    # Parse backup data
    local static_tag=$(echo "$backup_data" | grep "^STATIC_TAG=" | cut -d= -f2)
    local static_date=$(echo "$backup_data" | grep "^STATIC_DATE=" | cut -d= -f2)
    local public_tag=$(echo "$backup_data" | grep "^PUBLIC_TAG=" | cut -d= -f2)
    local public_date=$(echo "$backup_data" | grep "^PUBLIC_DATE=" | cut -d= -f2)
    local db_tag=$(echo "$backup_data" | grep "^DB_TAG=" | cut -d= -f2)
    local db_date=$(echo "$backup_data" | grep "^DB_DATE=" | cut -d= -f2)

    if [ "$use_json" = true ]; then
        local json_data=$(cat <<EOF
{
  "static": {
    "tag": "$static_tag",
    "date": "$static_date"
  },
  "public": {
    "tag": "$public_tag",
    "date": "$public_date"
  },
  "database": {
    "tag": "$db_tag",
    "date": "$db_date"
  }
}
EOF
)
        format_json "$json_data"
    else
        print_status $BLUE "🕒 Last Backup Times"
        echo ""
        echo "Static Site Backups:"
        if [ "$static_tag" != "(none)" ]; then
            echo "  Latest: $static_tag"
            if [ "$static_date" != "(none)" ]; then
                echo "  Date: $static_date"
            fi
        else
            echo "  No backups found"
        fi
        echo ""

        echo "Public Files Backups:"
        if [ "$public_tag" != "(none)" ]; then
            echo "  Latest: $public_tag"
            if [ "$public_date" != "(none)" ]; then
                echo "  Date: $public_date"
            fi
        else
            echo "  No backups found"
        fi
        echo ""

        echo "Database Backups:"
        if [ "$db_tag" != "(none)" ]; then
            echo "  Latest: $db_tag"
            if [ "$db_date" != "(none)" ]; then
                echo "  Date: $db_date"
            fi
        else
            echo "  No backups found"
        fi
        echo ""
    fi
}

# Show current status
show_status() {
    # Check for --json flag
    if has_json_flag "$@"; then
        # Get CF target info
        local cf_target_output=$(cf target 2>/dev/null)
        local cf_org=$(echo "$cf_target_output" | grep "^org:" | awk '{print $2}')
        local cf_space=$(echo "$cf_target_output" | grep "^space:" | awk '{print $2}')
        local cf_api=$(echo "$cf_target_output" | grep "^API endpoint:" | awk '{print $3}')
        local cf_user=$(echo "$cf_target_output" | grep "^user:" | awk '{print $2}')

        # Get app info
        local cms_state=$(cf app cms 2>/dev/null | grep "^requested state:" | awk '{print $3}')
        local www_state=$(cf app www 2>/dev/null | grep "^requested state:" | awk '{print $3}')
        local waf_state=$(cf app waf 2>/dev/null | grep "^requested state:" | awk '{print $3}')

        # Get current digests
        local cms_digest=$(get_app_digest "cms" 2>/dev/null || echo "unknown")
        local www_digest=$(get_app_digest "www" 2>/dev/null || echo "unknown")
        local waf_digest=$(get_app_digest "waf" 2>/dev/null || echo "unknown")

        # Get deployment time
        local cms_updated=$(cf app cms 2>/dev/null | grep "^last uploaded:" | sed 's/^last uploaded: *//')

        # Get recent events
        local recent_events=$(cf events cms 2>/dev/null | tail -n +4 | head -5 | awk '{print $1" "$2" "$3}' | jq -R . | jq -s .)

        local json_data=$(cat <<EOF
{
  "cf_target": {
    "api": "$cf_api",
    "org": "$cf_org",
    "space": "$cf_space",
    "user": "$cf_user"
  },
  "deployment_context": {
    "env": "${DEPLOY_ENV:-(not set)}",
    "ticket": "${DEPLOY_TICKET:-(not set)}"
  },
  "apps": {
    "cms": {
      "state": "${cms_state:-unknown}",
      "digest": "$cms_digest",
      "last_uploaded": "${cms_updated:-unknown}"
    },
    "www": {
      "state": "${www_state:-unknown}",
      "digest": "$www_digest"
    },
    "waf": {
      "state": "${waf_state:-unknown}",
      "digest": "$waf_digest"
    }
  },
  "recent_events": $recent_events
}
EOF
)
        format_json "$json_data"
        return
    fi

    # Table format: Enhanced, thorough display
    print_status $BLUE "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    print_status $BLUE "📊 DEPLOYMENT STATUS"
    print_status $BLUE "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""

    # Section 1: Cloud Foundry Target
    print_status $CYAN "🎯 Cloud Foundry Target"
    echo "────────────────────────────────────────────────────────────────────────────────"
    local cf_target_output=$(cf target 2>/dev/null)
    local cf_org=$(echo "$cf_target_output" | grep "^org:" | awk '{print $2}')
    local cf_space=$(echo "$cf_target_output" | grep "^space:" | awk '{print $2}')
    local cf_api=$(echo "$cf_target_output" | grep "^API endpoint:" | awk '{print $3}')
    local cf_user=$(echo "$cf_target_output" | grep "^user:" | awk '{print $2}')

    echo "  Organization: $cf_org"
    echo "  Space:        $cf_space"
    echo "  User:         $cf_user"
    echo "  API:          $cf_api"
    echo ""

    # Section 2: Deployment Context
    print_status $CYAN "📝 Deployment Context"
    echo "────────────────────────────────────────────────────────────────────────────────"
    if [ -n "$DEPLOY_ENV" ] || [ -n "$DEPLOY_TICKET" ]; then
        echo "  Environment:  ${DEPLOY_ENV:-(not set)}"
        echo "  Ticket:       ${DEPLOY_TICKET:-(not set)}"
        echo "  Pre-suffix:   ${DEPLOY_PRE_SUFFIX:-(not set)}"
        echo "  Post-suffix:  ${DEPLOY_POST_SUFFIX:-(not set)}"
        if [ -n "$DEPLOY_ROLLBACK_STATIC_TAG" ]; then
            echo ""
            echo "  Rollback Tags:"
            echo "    Static:     $DEPLOY_ROLLBACK_STATIC_TAG"
            echo "    Public:     $DEPLOY_ROLLBACK_PUBLIC_TAG"
            echo "    Database:   $DEPLOY_ROLLBACK_DB_TAG"
        fi
    else
        print_status $YELLOW "  ⚠️  No deployment context set"
        echo "  Run: deploy.sh set-context <env> <ticket>"
    fi
    echo ""

    # Section 3: Currently Deployed Applications
    print_status $CYAN "🚀 Currently Deployed Applications"
    echo "────────────────────────────────────────────────────────────────────────────────"

    # Get current digests for all apps
    local cms_current=$(get_app_digest "cms" 2>/dev/null || echo "")
    local www_current=$(get_app_digest "www" 2>/dev/null || echo "")
    local waf_current=$(get_app_digest "waf" 2>/dev/null || echo "")

    if [ -z "$cms_current" ]; then
        print_status $RED "  ❌ Unable to query apps (check 'cf target' and login)"
    else
        # Get app states and instances
        local cms_info=$(cf app cms 2>/dev/null)
        local www_info=$(cf app www 2>/dev/null)
        local waf_info=$(cf app waf 2>/dev/null)

        local cms_state=$(echo "$cms_info" | grep "^requested state:" | awk '{print $3}')
        local www_state=$(echo "$www_info" | grep "^requested state:" | awk '{print $3}')
        local waf_state=$(echo "$waf_info" | grep "^requested state:" | awk '{print $3}')

        local cms_instances=$(echo "$cms_info" | grep "^\#[0-9].*running" | wc -l | tr -d ' ')
        local www_instances=$(echo "$www_info" | grep "^\#[0-9].*running" | wc -l | tr -d ' ')
        local waf_instances=$(echo "$waf_info" | grep "^\#[0-9].*running" | wc -l | tr -d ' ')

        # Get deployment time from app info
        local cms_updated=$(echo "$cms_info" | grep "^last uploaded:" | sed 's/^last uploaded: *//')

        # Extract build number if present
        local build_num=$(extract_build_from_digest "$cms_current")
        build_num="${build_num:-unknown}"

        echo "  Last Deployed: ${cms_updated:-unknown}"
        echo "  Build Number:  $build_num"
        echo ""

        # CMS
        print_status $GREEN "  CMS Application:"
        echo "    State:     $([ "$cms_state" = "started" ] && echo "✅ $cms_state" || echo "⚠️  $cms_state")"
        echo "    Instances: $cms_instances running"
        echo "    Digest:    ${cms_current:0:70}..."
        echo ""

        # WWW
        print_status $GREEN "  WWW Application:"
        echo "    State:     $([ "$www_state" = "started" ] && echo "✅ $www_state" || echo "⚠️  $www_state")"
        echo "    Instances: $www_instances running"
        echo "    Digest:    ${www_current:0:70}..."
        echo ""

        # WAF
        print_status $GREEN "  WAF Application:"
        echo "    State:     $([ "$waf_state" = "started" ] && echo "✅ $waf_state" || echo "⚠️  $waf_state")"
        echo "    Instances: $waf_instances running"
        echo "    Digest:    ${waf_current:0:70}..."
    fi
    echo ""

    # Section 4: Recent Activity
    print_status $CYAN "📋 Recent Activity (Last 5 Events)"
    echo "────────────────────────────────────────────────────────────────────────────────"
    local recent_events=$(cf events cms 2>/dev/null | head -9 | tail -5)
    if [ -n "$recent_events" ]; then
        echo "$recent_events" | while read -r line; do
            echo "  $line"
        done
    else
        print_status $YELLOW "  ⚠️  Could not retrieve recent events"
    fi
    echo ""

    # Section 5: Quick Actions
    print_status $CYAN "⚡ Quick Actions"
    echo "────────────────────────────────────────────────────────────────────────────────"
    echo "  View digests:        deploy.sh digests current"
    echo "  Check changes:       deploy.sh ccb prod stage"
    echo "  Validate deployment: deploy.sh validate"
    echo "  Set context:         deploy.sh set-context <env> <ticket>"
    echo ""

    print_status $BLUE "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# Show message of the day from CMS container
show_motd() {
    cf ssh cms -c "cat /etc/motd"
}

# ===================================================================
# HELPER FUNCTIONS
# ===================================================================

# Validate CF target matches DEPLOY_ENV (safety check for destructive operations)
# NIST 800-53: CM-3 - Configuration Change Control
# NIST 800-53: CM-6 - Configuration Settings
# Args:
#   $1: skip_validation - If "--skip-validation", skip validation
# Returns: 0 if valid, exits if mismatch
validate_target_space() {
    local skip_validation="$1"

    # Skip validation if flag provided
    if [ "$skip_validation" = "--skip-validation" ]; then
        print_status $YELLOW "⚠️  --skip-validation flag used, skipping space validation"
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
        echo "  2. Use --skip-validation flag to skip validation (NOT RECOMMENDED)"
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
        echo "  3. Use --skip-validation flag (NOT RECOMMENDED)"
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
    local cmd
    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/manager.sh backup %q %q %q' "$types" "$ticket" "$suffix")
    cf ssh cms -c "$cmd"

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
# NIST 800-53: CP-10 - Information System Recovery and Reconstitution
# NIST 800-53: CM-5 - Access Restrictions for Change
# Args:
#   $1: tag - Backup tag
#   $2: only_flag - Optional --only=type flag
exec_restore_command() {
    local tag="$1"
    local only_flag="${2:-}"

    # Use printf %q for safe shell escaping to prevent command injection
    local cmd
    if [ -n "$only_flag" ]; then
        cmd=$(printf 'cd /var/www && scripts/snapshot/manager.sh restore %q %q' "$tag" "$only_flag")
    else
        cmd=$(printf 'cd /var/www && scripts/snapshot/manager.sh restore %q' "$tag")
    fi
    cf ssh cms -c "$cmd"
}

# Prompt for rollback confirmation
# Args:
#   $1: rollback_type - Description of what's being rolled back
#   $2: tag - Backup tag
#   $3: skip_confirmation - Optional "--skip-confirmation" flag to bypass confirmation
# Returns: 0 if confirmed, exits if cancelled
confirm_rollback() {
    local rollback_type="$1"
    local tag="$2"
    local skip_confirmation="$3"

    # Validate tag
    if ! validate_backup_tag "$tag"; then
        exit 1
    fi

    # Check if we're in production environment
    local current_space=$(cf target | grep space: | awk '{print $2}')
    local is_prod=false
    if [ "$current_space" = "prod" ]; then
        is_prod=true
    fi

    # Build confirmation prompt
    local prompt="⚠️  ROLLBACK: This will restore $rollback_type\nBackup Tag: $tag"

    if [ "$is_prod" = "true" ]; then
        # Production requires exact confirmation string
        print_status $RED "⚠️  PRODUCTION ENVIRONMENT DETECTED"
        if ! confirm_action "$prompt" "exact" "CONFIRM PROD ROLLBACK" 3 "$skip_confirmation"; then
            print_status $YELLOW "💡 Tip: Use --skip-confirmation flag to bypass this check"
            exit 1
        fi
    else
        # Non-production uses simple yes confirmation
        if ! confirm_action "$prompt" "yn" "" "" "$skip_confirmation"; then
            exit 0
        fi
    fi
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
        handle_error "DEPLOY_TICKET not set. Run: deploy.sh set-context <env> <ticket>" "validation" "exit"
    fi

    print_status $BLUE "📦 Creating ${backup_type}-deployment backup"
    print_status $YELLOW "Ticket: $ticket"
    print_status $YELLOW "Suffix: $suffix"
    echo ""

    exec_backup_command "$ticket" "$suffix" "all"
}

# Pre-deployment backup using context variables
pre_deploy() {
    local skip_validation=""
    local skip_confirmation=""
    local ticket="${DEPLOY_TICKET:-}"
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"

    # Parse flags from arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --skip-confirmation)
                skip_confirmation="--skip-confirmation"
                shift
                ;;
            --skip-validation)
                skip_validation="--skip-validation"
                shift
                ;;
            -*)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh pre-deploy [--skip-validation] [--skip-confirmation]"
                return 2
                ;;
            *)
                print_status $RED "❌ Unexpected argument: $1"
                echo "Usage: deploy.sh pre-deploy [--skip-validation] [--skip-confirmation]"
                return 2
                ;;
        esac
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$skip_validation"

    # Confirm backup creation
    local prompt="⚠️  PRE-DEPLOYMENT BACKUP: Creating backup before deployment\nEnvironment: $env\nTicket: ${ticket:-not set}"
    if ! confirm_action "$prompt" "yn" "" "" "$skip_confirmation"; then
        return 0
    fi

    create_deployment_backup "PRE" "pre-deploy"
}

# Post-deployment backup using context variables
# Now automatically creates annotated git tags for deployment tracking
post_deploy() {
    local skip_validation=""
    local skip_confirmation=""
    local ticket="${DEPLOY_TICKET:-}"
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"

    # Parse flags from arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --skip-confirmation)
                skip_confirmation="--skip-confirmation"
                shift
                ;;
            --skip-validation)
                skip_validation="--skip-validation"
                shift
                ;;
            -*)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh post-deploy [--skip-validation] [--skip-confirmation]"
                return 2
                ;;
            *)
                print_status $RED "❌ Unexpected argument: $1"
                echo "Usage: deploy.sh post-deploy [--skip-validation] [--skip-confirmation]"
                return 2
                ;;
        esac
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$skip_validation"

    # Confirm backup creation
    local prompt="⚠️  POST-DEPLOYMENT BACKUP: Creating backup after deployment\nEnvironment: $env\nTicket: ${ticket:-not set}"
    if ! confirm_action "$prompt" "yn" "" "" "$skip_confirmation"; then
        return 0
    fi

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

    # Extract CCI build number from digest using common function with motd fallback
    local cci_build=""
    if [ -n "$cms_digest" ]; then
        cci_build=$(extract_build_from_digest "$cms_digest")
        # If extraction fails, sanitize the digest for use as identifier
        if [ "$cci_build" = "unknown" ]; then
            # Extract short SHA from digest and sanitize: replace /, @, : with -
            cci_build=$(echo "$cms_digest" | sed 's/@sha256:/--/' | sed 's/[/:@]/-/g' | cut -c1-40)
        fi
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
        exit 2
    fi

    # Validate space names
    if [ "$from_space" != "dev" ] && [ "$from_space" != "stage" ] && [ "$from_space" != "prod" ]; then
        handle_error "FROM space must be dev, stage, or prod" "validation" "exit"
    fi

    if [ "$to_space" != "dev" ] && [ "$to_space" != "stage" ] && [ "$to_space" != "prod" ]; then
        handle_error "TO space must be dev, stage, or prod" "validation" "exit"
    fi

    if [ "$from_space" = "$to_space" ]; then
        handle_error "FROM and TO spaces must be different" "validation" "exit"
    fi

    # Save current space to restore later
    local original_space=$(cf target 2>/dev/null | grep 'space:' | awk '{print $2}')

    # If no backup tag specified, get latest from FROM space
    if [ -z "$backup_tag" ]; then
        print_status $BLUE "🔍 Finding latest backup from $from_space..."

        # Switch to FROM space to query backups
        cf target -s "$from_space" >/dev/null 2>&1
        if [ $? -ne 0 ]; then
            print_status $RED "❌ System Error: Failed to target FROM space: $from_space"
            exit 3
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
            exit 3
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
        print_status $RED "❌ System Error: Failed to download database backup"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 3
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
        print_status $RED "❌ System Error: Failed to download public files backup"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 3
    fi

    tar xzf "$temp_dir/public.tar.gz" -C "$public_dir" 2>/dev/null
    rm "$temp_dir/public.tar.gz"
    print_status $GREEN "  ✅ Public files downloaded ($(du -sh "$public_dir" | cut -f1))"

    # Switch to TO space
    print_status $BLUE "🎯 Targeting $to_space..."
    cf target -s "$to_space" >/dev/null 2>&1
    if [ $? -ne 0 ]; then
        print_status $RED "❌ System Error: Failed to target TO space: $to_space"
        rm -rf "$temp_dir"
        [ -n "$original_space" ] && cf target -s "$original_space" >/dev/null 2>&1
        exit 3
    fi

    # Get tome state before restore
    print_status $BLUE "📋 Checking tome state..."
    local tome_disabled=$(cf ssh cms -c ". /etc/profile; drush sget usagov.tome_run_disabled" 2>/dev/null | tail -1)

    # Enable maintenance mode and disable tome using unified state command
    print_status $BLUE "🔒 Preparing for restore (maintenance mode + disable tome)..."
    cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh state disable both" >/dev/null 2>&1

    # Upload and restore database
    print_status $BLUE "📤 Uploading and restoring database..."
    # Use printf %q for safe shell escaping
    local upload_cmd
    upload_cmd=$(printf 'cat > /tmp/%q.sql.gz' "$backup_tag")
    cf ssh cms -c "$upload_cmd" < "$db_file"

    local restore_cmd
    restore_cmd=$(printf '. /etc/profile; cd /tmp; gunzip -f %q.sql.gz; drush sql-cli < %q.sql; rm -f %q.sql' "$backup_tag" "$backup_tag" "$backup_tag")
    cf ssh cms -c "$restore_cmd" >/dev/null 2>&1
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

    # Restore original state (disable maintenance mode, restore tome state)
    print_status $BLUE "🔓 Restoring site state..."
    if [ "$tome_disabled" != "1" ]; then
        # Tome was enabled before, restore both to enabled state
        cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh state enable both" >/dev/null 2>&1
    else
        # Tome was disabled before, only disable maintenance mode
        cf ssh cms -c "cd /var/www && scripts/snapshot/manager.sh state enable sm" >/dev/null 2>&1
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
    local days="7"
    local use_json=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            *)
                # First non-flag arg is days
                days="$1"
                shift
                ;;
        esac
    done

    if [ "$use_json" = true ]; then
        # Use printf %q for safe shell escaping, add --json flag for manager.sh
        local cmd
        cmd=$(printf 'cd /var/www && scripts/snapshot/manager.sh list all %q --json' "$days")
        cf ssh cms -c "$cmd"
    else
        print_status $BLUE "📋 Available backups (last $days days)"
        echo ""

        # Use printf %q for safe shell escaping
        local cmd
        cmd=$(printf 'cd /var/www && scripts/snapshot/manager.sh list all %q' "$days")
        cf ssh cms -c "$cmd"
    fi
}

# List available container digests with deployment history
# Args:
#   $1: env - Environment filter (default: current from cf target)
#   $2: days - Show backups from last N days (default: 7)
#   $3: limit - Limit results per category (default: 10)
#   Additional flags: --backups-only, --git-only, --json
list_digests() {
    local target_env=""
    local days="7"
    local limit="10"
    local backups_only=false
    local git_only=false
    local use_json=false
    local show_all_history=false

    # Parse all arguments (flags and positional)
    while [ $# -gt 0 ]; do
        case "$1" in
            --backups-only) backups_only=true ;;
            --git-only) git_only=true ;;
            --json) use_json=true ;;
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
        dr|dev|stage|prod|all) ;;
        *)
            print_status $RED "❌ Invalid environment: $target_env"
            echo "Valid options: dev, stage, prod, all, current"
            return 1
            ;;
    esac

    # For JSON output, collect data and format at the end
    if [ "$use_json" = true ]; then
        local envs_to_query
        if [ "$target_env" = "all" ]; then
            envs_to_query="dev stage prod"
        else
            envs_to_query="$target_env"
        fi

        # Build JSON object for each environment
        local json_output="{"
        local first_env=true

        for env in $envs_to_query; do
            # Get current and previous digests
            local cms_current=$(get_app_digest "cms" 2>/dev/null || echo "unknown")
            local www_current=$(get_app_digest "www" 2>/dev/null || echo "unknown")
            local waf_current=$(get_app_digest "waf" 2>/dev/null || echo "unknown")
            local cms_updated=$(cf app cms 2>/dev/null | grep "^last uploaded:" | sed 's/^last uploaded: *//')
            local build_num=$(extract_build_from_digest "$cms_current")

            # Add comma between environments
            if [ "$first_env" = false ]; then
                json_output="${json_output},"
            fi
            first_env=false

            # Build properly quoted JSON for this environment
            json_output="${json_output}\"${env}\":{\"current\":{\"cms\":\"${cms_current}\",\"www\":\"${www_current}\",\"waf\":\"${waf_current}\",\"deployed\":\"${cms_updated:-unknown}\",\"build\":\"${build_num:-unknown}\"}}"
        done

        json_output="${json_output}}"

        format_json "$json_output"
        return
    fi

    # Table format: Use original display logic
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

    if [ -z "$cms_current" ]; then
        echo "  Unable to query CF (check 'cf target' and login)"
        return 1
    fi

    # Get deployment time from app info
    local cms_updated=$(cf app cms 2>/dev/null | grep "^last uploaded:" | sed 's/^last uploaded: *//')

    # Extract build number if present (only in specific formats)
    local build_num=$(extract_build_from_digest "$cms_current")
    build_num="${build_num:-unknown}"

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
                local prev_build=$(extract_build_from_digest "$cms_prev")
                prev_build="${prev_build:-unknown}"

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

    current_build=$(extract_build_from_digest "$cms_current")

    # Try to get previous build from CF
    local cms_guid=$(cf app cms --guid 2>/dev/null)
    if [ -n "$cms_guid" ]; then
        local droplets=$(cf curl "/v3/apps/${cms_guid}/droplets?order_by=-created_at&per_page=5" 2>/dev/null)
        local prev_droplet_guid=$(echo "$droplets" | grep -o '"guid":"[^"]*"' | head -2 | tail -1 | cut -d'"' -f4)
        if [ -n "$prev_droplet_guid" ] && [ "$prev_droplet_guid" != "guid" ]; then
            local prev_droplet=$(cf curl "/v3/droplets/${prev_droplet_guid}" 2>/dev/null)
            local cms_prev=$(echo "$prev_droplet" | grep -o '"image":"[^"]*"' | cut -d'"' -f4)
            prev_build=$(extract_build_from_digest "$cms_prev")
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
#   $3: skip_validation - Optional "--skip-validation" flag to skip validation
#   $4: skip_confirmation - Optional "--skip-confirmation" flag to bypass confirmation
rollback_single_type() {
    local type="$1"
    local tag="${2:-}"
    local skip_validation="$3"
    local skip_confirmation="$4"

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$skip_validation"

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
        echo "Usage: deploy.sh rollback-$type <tag> [--skip-validation] [--skip-confirmation]"
        echo "Or set deployment context first: deploy.sh set-context <env> <ticket>"
        exit 2
    fi

    # Use confirmation helper with skip_confirmation flag
    confirm_rollback "$type only" "$tag" "$skip_confirmation"

    exec_restore_command "$tag" "--only=$type"
}

# Rollback static site only (with confirmation)
rollback_static() {
    rollback_single_type "static" "$1" "$2" "$3"
}

# Rollback database only (with confirmation)
rollback_db() {
    rollback_single_type "db" "$1" "$2" "$3"
}

# Download backups locally - defaults to latest backup for current space
download_backups() {
    local tag=""
    local output_dir="$(pwd)"
    local use_json=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            --output-dir=*)
                output_dir="${1#*=}"
                shift
                ;;
            *)
                # First non-flag arg is tag
                if [ -z "$tag" ]; then
                    tag="$1"
                fi
                shift
                ;;
        esac
    done

    # If no tag provided, find the most recent backup
    if [ -z "$tag" ]; then
        if [ "$use_json" = false ]; then
            print_status $BLUE "🔍 Finding most recent backup..."
        fi

        # Query S3 to get the most recent backup tag
        tag=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars &&
            aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ \$S3_EXTRA_PARAMS |
            grep '\.sql\.gz$' |
            sort -r |
            head -1 |
            awk '{print \$4}' |
            sed 's/\.sql\.gz$//'" 2>/dev/null | tail -1 | tr -d '\r')

        if [ -z "$tag" ]; then
            if [ "$use_json" = true ]; then
                echo '{"error":"Could not find any backups"}' | jq .
                return 3
            else
                print_status $RED "❌ System Error: Could not find any backups"
                return 3
            fi
        fi

        if [ "$use_json" = false ]; then
            print_status $GREEN "✅ Found latest backup: $tag"
        fi
    fi

    if [ "$use_json" = false ]; then
        print_status $BLUE "📥 Downloading backups for: $tag"
        echo "Output directory: $output_dir"
        echo ""
    fi

    local failed=0
    local db_success=false
    local db_size="0"
    local static_success=false
    local static_size="0"
    local public_success=false
    local public_size="0"

    # Download database backup
    if [ "$use_json" = false ]; then
        print_status $YELLOW "📦 Downloading database..."
    fi
    local cmd
    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download %q db - --stream' "$tag")
    cf ssh cms -c "$cmd" > "${output_dir}/${tag}-database.sql.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-database.sql.gz" ]; then
        db_success=true
        db_size=$(du -h "${output_dir}/${tag}-database.sql.gz" | cut -f1)
        if [ "$use_json" = false ]; then
            print_status $GREEN "  ✅ Database downloaded ($db_size)"
        fi
    else
        if [ "$use_json" = false ]; then
            print_status $RED "  ❌ Database download failed"
        fi
        failed=$((failed + 1))
    fi

    # Download static site backup
    if [ "$use_json" = false ]; then
        print_status $YELLOW "📦 Downloading static site..."
    fi
    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download %q static - --stream' "$tag")
    cf ssh cms -c "$cmd" > "${output_dir}/${tag}-static.tar.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-static.tar.gz" ]; then
        static_success=true
        static_size=$(du -h "${output_dir}/${tag}-static.tar.gz" | cut -f1)
        if [ "$use_json" = false ]; then
            print_status $GREEN "  ✅ Static site downloaded ($static_size)"
        fi
    else
        if [ "$use_json" = false ]; then
            print_status $RED "  ❌ Static site download failed"
        fi
        failed=$((failed + 1))
    fi

    # Download public files backup
    if [ "$use_json" = false ]; then
        print_status $YELLOW "📦 Downloading public files..."
    fi
    cmd=$(printf 'source /etc/profile && cd /var/www && scripts/snapshot/manager.sh download %q public - --stream' "$tag")
    cf ssh cms -c "$cmd" > "${output_dir}/${tag}-public.tar.gz" 2>/dev/null
    if [ $? -eq 0 ] && [ -s "${output_dir}/${tag}-public.tar.gz" ]; then
        public_success=true
        public_size=$(du -h "${output_dir}/${tag}-public.tar.gz" | cut -f1)
        if [ "$use_json" = false ]; then
            print_status $GREEN "  ✅ Public files downloaded ($public_size)"
        fi
    else
        if [ "$use_json" = false ]; then
            print_status $RED "  ❌ Public files download failed"
        fi
        failed=$((failed + 1))
    fi

    # Output results
    if [ "$use_json" = true ]; then
        local json_data=$(cat <<EOF
{
  "tag": "$tag",
  "output_directory": "$output_dir",
  "downloads": {
    "database": {
      "success": $db_success,
      "file": "${tag}-database.sql.gz",
      "size": "$db_size"
    },
    "static": {
      "success": $static_success,
      "file": "${tag}-static.tar.gz",
      "size": "$static_size"
    },
    "public": {
      "success": $public_success,
      "file": "${tag}-public.tar.gz",
      "size": "$public_size"
    }
  },
  "failed_count": $failed,
  "success": $([ $failed -eq 0 ] && echo true || echo false)
}
EOF
)
        format_json "$json_data"
    else
        echo ""
        if [ $failed -eq 0 ]; then
            print_status $GREEN "✅ Download complete!"
            echo ""
            echo "Downloaded files:"
            ls -lh "${output_dir}/${tag}"-* 2>/dev/null
        else
            print_status $YELLOW "⚠️  Download completed with $failed error(s)"
            echo ""
            echo "Downloaded files:"
            ls -lh "${output_dir}/${tag}"-* 2>/dev/null
        fi
    fi

    if [ $failed -eq 0 ]; then
        return 0
    else
        return 1
    fi
}

# Tail the latest Tome log and stop when it finishes
tome_log() {
    local recent_mode="no"

    # Check for --recent flag
    if [ "$1" = "--recent" ]; then
        recent_mode="yes"
        print_status $BLUE "🔍 Finding most recent Tome log..."
    else
        print_status $BLUE "🔍 Finding active Tome log..."
    fi

    # Do all the filtering in a single SSH session for efficiency
    # This script finds logs, checks their content, and returns the appropriate one
    if [ "$recent_mode" = "yes" ]; then
        # Recent mode: skip only "already running" logs
        local target_log=$(cf ssh cms -c '
            for log_file in $(find /tmp/tome-log/20* -type f -name "*.log" 2>/dev/null | sort -r); do
                if grep -q "Another Tome is already running. Exiting." "$log_file" 2>/dev/null; then
                    continue
                fi
                echo "$log_file"
                break
            done
        ' 2>/dev/null | tail -1 | tr -d '\r')
    else
        # Active mode: skip both "already running" AND completed logs
        local target_log=$(cf ssh cms -c '
            for log_file in $(find /tmp/tome-log/20* -type f -name "*.log" 2>/dev/null | sort -r); do
                if grep -q "Another Tome is already running. Exiting." "$log_file" 2>/dev/null; then
                    continue
                fi
                if grep -qiE "(Tome static build looks fine|No changes detected|SYNC FINISHED)" "$log_file" 2>/dev/null; then
                    continue
                fi
                echo "$log_file"
                break
            done
        ' 2>/dev/null | tail -1 | tr -d '\r')
    fi

    if [ -z "$target_log" ]; then
        if [ "$recent_mode" = "yes" ]; then
            print_status $YELLOW "⚠️  No valid Tome log found"
            echo "All recent logs show 'Another Tome is already running. Exiting.'"
        else
            print_status $YELLOW "⚠️  No active Tome log found"
            echo "All recent logs are either blocked ('already running') or completed."
            echo "Try --recent to see the most recent log regardless of status."
        fi
        return 1
    fi

    print_status $GREEN "✅ Found log: $target_log"
    echo ""

    if [ "$recent_mode" = "yes" ]; then
        # Just show last 50 lines
        print_status $YELLOW "📄 Last 50 lines:"
        echo ""
        cf ssh cms -c "tail -n 50 $target_log 2>/dev/null"
    else
        # Tail the running log
        print_status $YELLOW "📄 Tailing log (will stop when Tome finishes)..."
        echo ""
        cf ssh cms -c "
            tail -f -n 50 $target_log 2>/dev/null |
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
    fi
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

    # Check for --json flag
    if has_json_flag "$@"; then
        # Fetch latest from remote to ensure we have current refs
        git fetch --all 2>/dev/null

        # Validate that both refs exist
        if ! git cat-file -t "$from" > /dev/null 2>&1; then
            echo '{"error":"'"$from"' not found in this repo"}' | jq .
            exit 2
        fi
        if ! git cat-file -t "$to" > /dev/null 2>&1; then
            echo '{"error":"'"$to"' not found in this repo"}' | jq .
            exit 2
        fi

        # Find the common ancestor (merge base) to handle non-linear history
        local merge_base
        merge_base=$(git merge-base "$from" "$to" 2>/dev/null)

        if [ -z "$merge_base" ]; then
            echo '{"error":"No common ancestor found between '"$from"' and '"$to"'"}' | jq .
            exit 1
        fi

        # Check if refs are the same
        local from_sha
        local to_sha
        from_sha=$(git rev-parse "$from")
        to_sha=$(git rev-parse "$to")

        if [ "$from_sha" = "$to_sha" ]; then
            local json_data='{"from":"'"$from"'","to":"'"$to"'","same_commit":true,"commits":[],"tickets":[]}'
            format_json "$json_data"
            return 0
        fi

        # Show commits in 'to' that are not in 'from'
        local commits_ahead
        commits_ahead=$(git log --first-parent --oneline "$from..$to" 2>/dev/null)

        if [ -z "$commits_ahead" ]; then
            # Check if 'from' is ahead instead
            local commits_behind
            commits_behind=$(git log --first-parent --oneline "$to..$from" 2>/dev/null)
            local behind_count=0
            if [ -n "$commits_behind" ]; then
                behind_count=$(echo "$commits_behind" | wc -l | tr -d ' ')
            fi

            local json_data='{"from":"'"$from"'","to":"'"$to"'","no_changes":true,"behind_count":'"$behind_count"',"commits":[],"tickets":[]}'
            format_json "$json_data"
            return 0
        fi

        # Extract tickets from commit messages
        local tickets
        tickets=$(git log --first-parent "$from..$to" | \
            grep -Eio 'usa(gov)?[-_[:space:]]([0-9]+)' | \
            sed -E 's/usa(gov)?[-_[:space:]]([0-9]+)/USAGOV-\2/ig' | \
            grep -iv usagov-2021 | \
            sort -u)

        # Show commit count
        local commit_count
        commit_count=$(echo "$commits_ahead" | wc -l | tr -d ' ')

        # Build JSON
        local tickets_json="[]"
        if [ -n "$tickets" ]; then
            tickets_json=$(echo "$tickets" | jq -R . | jq -s .)
        fi

        local commits_json=$(echo "$commits_ahead" | head -10 | jq -R . | jq -s .)
        local total_commits=$commit_count

        local json_data=$(cat <<EOF
{
  "from": "$from",
  "to": "$to",
  "commit_count": $total_commits,
  "tickets": $tickets_json,
  "recent_commits": $commits_json
}
EOF
)
        format_json "$json_data"
        return
    fi

    # Default table format
    print_status $BLUE "🔄 Fetching latest changes..."
    git fetch --all 2>/dev/null

    # Validate that both refs exist
    if ! git cat-file -t "$from" > /dev/null 2>&1; then
        handle_error "'$from' not found in this repo" "validation" "exit"
    fi
    if ! git cat-file -t "$to" > /dev/null 2>&1; then
        print_status $RED "❌ Error: '$to' not found in this repo"
        exit 2
    fi

    # Find the common ancestor (merge base) to handle non-linear history
    local merge_base
    merge_base=$(git merge-base "$from" "$to" 2>/dev/null)

    if [ -z "$merge_base" ]; then
        handle_error "No common ancestor found between $from and $to" "validation" "exit"
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

    # Show commits in 'to' that are not in 'from'
    # Using --first-parent to follow main branch history and avoid seeing every merged commit
    local commits_ahead
    commits_ahead=$(git log --first-parent --oneline "$from..$to" 2>/dev/null)

    if [ -z "$commits_ahead" ]; then
        # Check if 'from' is ahead instead
        local commits_behind
        commits_behind=$(git log --first-parent --oneline "$to..$from" 2>/dev/null)
        local behind_count=0
        if [ -n "$commits_behind" ]; then
            behind_count=$(echo "$commits_behind" | wc -l | tr -d ' ')
        fi

        print_status $YELLOW "ℹ️  No new commits in $to (may be behind $from)"
        if [ -n "$commits_behind" ]; then
            print_status $YELLOW "⚠️  Warning: $to is behind $from by $behind_count commits"
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

    # Show commit count
    local commit_count
    commit_count=$(echo "$commits_ahead" | wc -l | tr -d ' ')

    print_status $BLUE "📋 Changes from $from to $to"
    echo ""

    if [ -n "$tickets" ]; then
        echo "Tickets:"
        echo "$tickets" | while read -r ticket; do
            echo "  • $ticket"
        done
        echo ""
    fi

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
show_build_digests() {
    local use_json=false
    local env=""

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            -*)
                if [ "$use_json" = true ]; then
                    echo '{"error":"Unknown option: '"$1"'"}' | jq .
                else
                    print_status $RED "❌ Unknown option: $1"
                    echo "Usage: deploy.sh show-build-info [env] [--json]"
                fi
                return 2
                ;;
            *)
                env="$1"
                shift
                ;;
        esac
    done

    # Default to current space if not provided
    if [ -z "$env" ]; then
        env=$(cf target | grep 'space:' | awk '{print $2}')
        if [ -z "$env" ]; then
            if [ "$use_json" = true ]; then
                echo '{"error":"Could not determine current space"}' | jq .
            else
                print_status $RED "❌ Could not determine current space"
            fi
            return 1
        fi
    fi

    env=$(echo "$env" | tr '[:upper:]' '[:lower:]')

    if [ "$use_json" != true ]; then
        print_status $BLUE "🔍 Searching for latest build information for: $env"
        echo ""
    fi

    # Find the most recent annotated tag for this environment
    local annotated_tag
    annotated_tag=$(git for-each-ref refs/tags/usagov-cci-build-*-${env} --sort='-*authordate' \
        --format '%(objecttype) %(refname:short)' | \
        while read ty name; do [ "$ty" = "tag" ] && echo "$name" && break; done)

    if [ -z "$annotated_tag" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"No git tag found matching pattern: usagov-cci-build-*-'"$env"'"}' | jq .
        else
            print_status $RED "❌ No git tag found matching pattern: usagov-cci-build-*-${env}"
            echo ""
            echo "Available tags:"
            git tag -l "usagov-cci-build-*" | tail -10
        fi
        return 1
    fi

    if [ "$use_json" != true ]; then
        print_status $GREEN "✅ Found tag: $annotated_tag"
        echo ""
    fi

    # Parse the tag annotation
    local tag_content
    tag_content=$(git for-each-ref refs/tags/$annotated_tag --format "%(contents)" | sed "s/'//g")

    if [ -z "$tag_content" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Tag annotation is empty"}' | jq .
        else
            print_status $RED "❌ Tag annotation is empty"
        fi
        return 1
    fi

    # Parse the fields dynamically
    local cci_build=""
    local app_digests_list=""  # Store as "APP:DIGEST|APP:DIGEST|..."

    # Parse using POSIX-compatible approach
    old_ifs="$IFS"
    IFS='|'
    for field in $tag_content; do
        case "$field" in
            CCI_BUILD=*)
                cci_build="${field#CCI_BUILD=}"
                ;;
            *_DIGEST=*)
                # Extract app name and digest dynamically
                local app_name="${field%%_DIGEST=*}"
                local digest="${field#*_DIGEST=}"
                app_digests_list="${app_digests_list}${app_name}:${digest}|"
                ;;
        esac
    done
    IFS="$old_ifs"

    # For JSON output, format and return early
    if [ "$use_json" = true ]; then
        local json_output='{"environment":"'"$env"'","tag":"'"$annotated_tag"'","cci_build":"'"$cci_build"'","digests":{'
        local first=true

        old_ifs="$IFS"
        IFS='|'
        for pair in $app_digests_list; do
            if [ -n "$pair" ]; then
                local app="${pair%%:*}"
                local digest="${pair#*:}"
                local app_lower=$(echo "$app" | tr '[:upper:]' '[:lower:]')

                if [ "$first" = false ]; then
                    json_output="${json_output},"
                fi
                first=false

                json_output="${json_output}\"${app_lower}\":\"${digest}\""
            fi
        done
        IFS="$old_ifs"

        json_output="${json_output}}}"
        format_json "$json_output"
        return
    fi

    # Display the information
    print_status $BLUE "📦 Build Information"
    echo "----------------------------------------"
    echo "Environment:    $env"
    echo "Tag:            $annotated_tag"
    echo "CCI Build:      $cci_build"
    echo ""
    echo "Container Digests:"

    # Display each app:digest pair
    old_ifs="$IFS"
    IFS='|'
    for pair in $app_digests_list; do
        if [ -n "$pair" ]; then
            local app="${pair%%:*}"
            local digest="${pair#*:}"
            printf "  %-12s %s\n" "${app}:" "${digest}"
        fi
    done
    IFS="$old_ifs"
    echo ""

    # Generate deployment commands
    print_status $BLUE "🚀 Deployment Commands"
    echo "----------------------------------------"
    echo ""
    old_ifs="$IFS"
    IFS='|'
    for pair in $app_digests_list; do
        if [ -n "$pair" ]; then
            local app="${pair%%:*}"
            local digest="${pair#*:}"
            local app_lower=$(echo "$app" | tr '[:upper:]' '[:lower:]')
            echo "To deploy ${app}:"
            echo "  deploy.sh push ${app_lower} $cci_build ${digest}"
            echo ""
        fi
    done
    IFS="$old_ifs"

    # Optionally set these as environment variables if DEPLOY_ENV matches
    if [ -n "$DEPLOY_ENV" ] && [ "$DEPLOY_ENV" = "$env" ]; then
        export DEPLOY_CCI_BUILD="$cci_build"
        old_ifs="$IFS"
        IFS='|'
        for pair in $app_digests_list; do
            if [ -n "$pair" ]; then
                local app="${pair%%:*}"
                local digest="${pair#*:}"
                local var_name="DEPLOY_${app}_DIGEST"
                export "${var_name}=${digest}"
            fi
        done
        IFS="$old_ifs"

        print_status $GREEN "✅ Build info exported to environment variables"
        echo "  DEPLOY_CCI_BUILD=$DEPLOY_CCI_BUILD"
        old_ifs="$IFS"
        IFS='|'
        for pair in $app_digests_list; do
            if [ -n "$pair" ]; then
                local app="${pair%%:*}"
                local var_name="DEPLOY_${app}_DIGEST"
                eval "echo \"  ${var_name}=\$${var_name}\""
            fi
        done
        IFS="$old_ifs"
        echo ""
    fi
}

# Create and push annotated git tag for deployment tracking
# NIST 800-53: CM-3 - Configuration Change Control
# NIST 800-53: AU-3 - Content of Audit Records
# Args:
#   $1: env - Environment name
#   $2: cci_build - CircleCI build number
#   $3+: app_name=digest pairs (e.g., cms=sha256:abc... waf=sha256:def...)
create_deployment_tag() {
    local env="$1"
    local cci_build="$2"
    shift 2

    if [ -z "$env" ] || [ -z "$cci_build" ]; then
        log_message "⚠️ Missing env or cci_build for git tag creation, skipping"
        return 0
    fi

    # Check if we're in a git repository
    if ! git rev-parse --git-dir >/dev/null 2>&1; then
        log_message "⚠️ Not in a git repository, skipping tag creation"
        return 0
    fi

    # Build tag message with all provided digests
    local tag_msg="CCI_BUILD=${cci_build}"

    for arg in "$@"; do
        if echo "$arg" | grep -q '='; then
            # Format: app=digest
            local app_name="${arg%%=*}"
            local digest="${arg#*=}"
            local app_upper=$(echo "$app_name" | tr '[:lower:]' '[:upper:]')
            tag_msg="${tag_msg}|${app_upper}_DIGEST=${digest}"
        else
            # Just digest provided - this shouldn't happen in practice
            log_message "⚠️ Digest without app name: $arg (skipping)"
        fi
    done

    local tag_name="usagov-cci-build-${cci_build}-${env}"

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
# NIST 800-53: SI-7 - Software, Firmware, and Information Integrity
# NIST 800-53: CM-11 - User-Installed Software
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

    # Validate app name against whitelist
    if ! validate_app_name "$app_name"; then
        return 1
    fi

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
    local app_name=""
    local cci_build=""
    local digest=""
    local skip_validation=""
    local skip_confirmation=""

    # Parse flags from all arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --skip-confirmation)
                skip_confirmation="--skip-confirmation"
                shift
                ;;
            --skip-validation)
                skip_validation="--skip-validation"
                shift
                ;;
            --digest=*)
                digest="${1#*=}"
                shift
                ;;
            --)
                shift
                break
                ;;
            -*)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh deploy app <app-name> <cci-build> [digest] [--skip-validation] [--skip-confirmation]"
                return 2
                ;;
            *)
                # Collect positional arguments
                if [ -z "$app_name" ]; then
                    app_name="$1"
                elif [ -z "$cci_build" ]; then
                    cci_build="$1"
                elif [ -z "$digest" ]; then
                    digest="$1"
                else
                    print_status $RED "❌ Unexpected argument: $1"
                    echo "Usage: deploy.sh deploy app <app-name> <cci-build> [digest] [--skip-validation] [--skip-confirmation]"
                    return 2
                fi
                shift
                ;;
        esac
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$skip_validation"

    if [ -z "$app_name" ] || [ -z "$cci_build" ]; then
        print_status $RED "❌ Error: Missing required parameters"
        echo "Usage: deploy.sh deploy app <app-name> <cci-build> [digest] [--skip-validation] [--skip-confirmation]"
        echo "Example: deploy.sh deploy app cms 5936 gsatts/usagov-2021@sha256:abc123..."
        echo ""
        echo "If digest not provided, will look up from:"
        echo "  1. DEPLOY_{APP}_DIGEST environment variable (set by show-build-info)"
        echo "  2. Git tag: usagov-cci-build-{build}-{env}"
        return 2
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

                    # Look for digest in format: APPNAME_DIGEST=...
                    local app_upper=$(echo "$app_name" | tr '[:lower:]' '[:upper:]')
                    digest=$(echo "$tag_msg" | grep -o "${app_upper}_DIGEST=[^|]*" | cut -d= -f2)

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
            return 3
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

    # Confirm deployment before proceeding
    local prompt="⚠️  DEPLOYMENT: This will deploy $app_name to $env\nBuild: $cci_build\nDigest: $digest"
    if ! confirm_action "$prompt" "yn" "" "" "$skip_confirmation"; then
        return 0
    fi

    _deploy_app "$app_name" "$env" "$cci_build" "$digest"
}

# Helper function to fetch deployment metadata from S3 via CMS container
# Wraps the common.sh function but executes it remotely via cf ssh
fetch_deployment_metadata() {
    local backup_tag="$1"

    # If no tag provided, find the most recent
    if [ -z "$backup_tag" ]; then
        backup_tag=$(fetch_latest_backup_tag)
        if [ -z "$backup_tag" ]; then
            return 1
        fi
    fi

    # Fetch metadata from CMS container (needs S3 access)
    cf ssh cms -c "cd /var/www && source scripts/common.sh && fetch_deployment_metadata '$backup_tag'" 2>/dev/null
}

# Helper function to fetch the latest backup tag from S3 via CMS container
fetch_latest_backup_tag() {
    cf ssh cms -c "cd /var/www && source scripts/common.sh && init_backup_system && setup_s3_vars && fetch_latest_backup_tag" 2>/dev/null | tail -1
}

# Show current container digests - wrapper that handles space switching
show_current_digests_wrapper() {
    local use_json=false
    local target_space=""

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            -*)
                if [ "$use_json" = true ]; then
                    echo '{"error":"Unknown option: '"$1"'"}' | jq .
                else
                    print_status $RED "❌ Unknown option: $1"
                    echo "Usage: deploy.sh digests [space] [--json]"
                fi
                return 2
                ;;
            *)
                target_space="$1"
                shift
                ;;
        esac
    done

    local current_space=$(cf target | grep 'space:' | awk '{print $2}')

    if [ "$use_json" = true ]; then
        # For JSON output, query directly from CF instead of using cron file
        local query_space="${target_space:-$current_space}"

        # Switch to target space if needed
        if [ -n "$target_space" ] && [ "$target_space" != "$current_space" ]; then
            cf target -s "$target_space" >/dev/null 2>&1
            if [ $? -ne 0 ]; then
                echo '{"error":"Failed to switch to space: '"$target_space"'"}' | jq .
                return 1
            fi
        fi

        # Get current digests
        local cms_digest=$(get_app_digest "cms" 2>/dev/null || echo "unknown")
        local www_digest=$(get_app_digest "www" 2>/dev/null || echo "unknown")
        local waf_digest=$(get_app_digest "waf" 2>/dev/null || echo "unknown")
        local cms_updated=$(cf app cms 2>/dev/null | grep "^last uploaded:" | sed 's/^last uploaded: *//')

        # Switch back if needed
        if [ -n "$target_space" ] && [ "$target_space" != "$current_space" ]; then
            cf target -s "$current_space" >/dev/null 2>&1
        fi

        # Output JSON
        local json_data=$(cat <<EOF
{
  "space": "$query_space",
  "cms": "$cms_digest",
  "www": "$www_digest",
  "waf": "$waf_digest",
  "last_deployed": "${cms_updated:-unknown}"
}
EOF
)
        format_json "$json_data"
        return
    fi

    # Default table output
    if [ -z "$target_space" ] || [ "$target_space" = "$current_space" ]; then
        # No space specified or same as current space
        cf ssh cms -c "cd /var/www && . scripts/common.sh && show_current_digests"
    else
        # Different space - switch, run, switch back
        print_status $BLUE "🔄 Switching to $target_space space..."
        cf target -s "$target_space" >/dev/null 2>&1
        if [ $? -ne 0 ]; then
            print_status $RED "❌ Failed to switch to space: $target_space"
            return 1
        fi

        cf ssh cms -c "cd /var/www && . scripts/common.sh && show_current_digests"

        print_status $BLUE "🔄 Switching back to $current_space space..."
        cf target -s "$current_space" >/dev/null 2>&1
    fi
}

# Validate deployment metadata completeness for a backup tag
# Args: <tag> [--json]
validate_digest_metadata() {
    local backup_tag=""
    local use_json=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            -*)
                if [ "$use_json" = true ]; then
                    echo '{"error":"Unknown option: '"$1"'"}' | jq .
                else
                    print_status $RED "❌ Unknown option: $1"
                    echo "Usage: deploy.sh digests validate <tag> [--json]"
                fi
                return 2
                ;;
            *)
                backup_tag="$1"
                shift
                ;;
        esac
    done

    if [ -z "$backup_tag" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Backup tag required"}' | jq .
        else
            print_status $RED "❌ Error: Backup tag required"
            echo "Usage: deploy.sh digests validate <tag> [--json]"
        fi
        return 1
    fi

    if [ "$use_json" != true ]; then
        print_status $BLUE "🔍 Validating metadata for backup: $backup_tag"
        echo ""
    fi

    # Fetch metadata
    local metadata_json=$(fetch_deployment_metadata "$backup_tag")

    if [ -z "$metadata_json" ]; then
        if [ "$use_json" = true ]; then
            echo '{"valid":false,"backup_tag":"'"$backup_tag"'","error":"Metadata not found"}' | jq .
        else
            print_status $RED "❌ Metadata not found for tag: $backup_tag"
        fi
        return 1
    fi

    # Validate required fields
    local errors=""
    local warnings=""
    local has_backup_tag=$(echo "$metadata_json" | grep -c '"backup_tag"')
    local has_timestamp=$(echo "$metadata_json" | grep -c '"timestamp"')
    local has_ticket=$(echo "$metadata_json" | grep -c '"ticket"')
    local has_environment=$(echo "$metadata_json" | grep -c '"environment"')
    local has_containers=$(echo "$metadata_json" | grep -c '"containers"')

    # Check required fields
    if [ "$has_backup_tag" -eq 0 ]; then
        errors="${errors}backup_tag field missing,"
    fi
    if [ "$has_timestamp" -eq 0 ]; then
        errors="${errors}timestamp field missing,"
    fi
    if [ "$has_environment" -eq 0 ]; then
        errors="${errors}environment field missing,"
    fi
    if [ "$has_containers" -eq 0 ]; then
        errors="${errors}containers object missing,"
    fi

    # Check optional but recommended fields
    if [ "$has_ticket" -eq 0 ]; then
        warnings="${warnings}ticket field missing (non-critical),"
    fi

    # Extract and validate container data
    local cms_digest=$(echo "$metadata_json" | sed -n '/"cms":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local www_digest=$(echo "$metadata_json" | sed -n '/"www":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local waf_digest=$(echo "$metadata_json" | sed -n '/"waf":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')

    # Validate digest format (should start with sha256:)
    if [ -n "$cms_digest" ] && ! echo "$cms_digest" | grep -q '^sha256:'; then
        errors="${errors}cms digest format invalid,"
    fi
    if [ -n "$www_digest" ] && ! echo "$www_digest" | grep -q '^sha256:'; then
        errors="${errors}www digest format invalid,"
    fi
    if [ -n "$waf_digest" ] && ! echo "$waf_digest" | grep -q '^sha256:'; then
        errors="${errors}waf digest format invalid,"
    fi

    # Check for missing critical containers
    if [ -z "$cms_digest" ]; then
        errors="${errors}cms digest missing,"
    fi
    if [ -z "$www_digest" ]; then
        errors="${errors}www digest missing,"
    fi
    if [ -z "$waf_digest" ]; then
        errors="${errors}waf digest missing,"
    fi

    # Remove trailing commas
    errors=$(echo "$errors" | sed 's/,$//')
    warnings=$(echo "$warnings" | sed 's/,$//')

    # Determine validity
    local is_valid=true
    if [ -n "$errors" ]; then
        is_valid=false
    fi

    # Output results
    if [ "$use_json" = true ]; then
        local error_array="[]"
        local warning_array="[]"

        if [ -n "$errors" ]; then
            error_array=$(echo "$errors" | tr ',' '\n' | jq -R . | jq -s .)
        fi
        if [ -n "$warnings" ]; then
            warning_array=$(echo "$warnings" | tr ',' '\n' | jq -R . | jq -s .)
        fi

        local json_output=$(cat <<EOF
{
  "valid": $is_valid,
  "backup_tag": "$backup_tag",
  "errors": $error_array,
  "warnings": $warning_array,
  "containers": {
    "cms": {"present": $([ -n "$cms_digest" ] && echo "true" || echo "false"), "digest": "$cms_digest"},
    "www": {"present": $([ -n "$www_digest" ] && echo "true" || echo "false"), "digest": "$www_digest"},
    "waf": {"present": $([ -n "$waf_digest" ] && echo "true" || echo "false"), "digest": "$waf_digest"}
  }
}
EOF
)
        format_json "$json_output"
    else
        # Table output
        if [ "$is_valid" = true ]; then
            print_status $GREEN "✅ Metadata validation: PASSED"
        else
            print_status $RED "❌ Metadata validation: FAILED"
        fi
        echo ""

        if [ -n "$errors" ]; then
            echo "Errors:"
            echo "$errors" | tr ',' '\n' | while read -r error; do
                [ -n "$error" ] && echo "  • $error"
            done
            echo ""
        fi

        if [ -n "$warnings" ]; then
            echo "Warnings:"
            echo "$warnings" | tr ',' '\n' | while read -r warning; do
                [ -n "$warning" ] && echo "  ⚠️  $warning"
            done
            echo ""
        fi

        echo "Container Digests:"
        echo "  CMS: ${cms_digest:-MISSING}"
        echo "  WWW: ${www_digest:-MISSING}"
        echo "  WAF: ${waf_digest:-MISSING}"
    fi

    if [ "$is_valid" = true ]; then
        return 0
    else
        return 1
    fi
}

# Compare two deployment metadata files
# Args: <tag1> <tag2> [--json]
compare_digest_metadata() {
    local tag1=""
    local tag2=""
    local use_json=false

    # Parse arguments
    while [ $# -gt 0 ]; do
        case "$1" in
            --json)
                use_json=true
                shift
                ;;
            -*)
                if [ "$use_json" = true ]; then
                    echo '{"error":"Unknown option: '"$1"'"}' | jq .
                else
                    print_status $RED "❌ Unknown option: $1"
                    echo "Usage: deploy.sh digests compare <tag1> <tag2> [--json]"
                fi
                return 2
                ;;
            *)
                if [ -z "$tag1" ]; then
                    tag1="$1"
                elif [ -z "$tag2" ]; then
                    tag2="$1"
                fi
                shift
                ;;
        esac
    done

    if [ -z "$tag1" ] || [ -z "$tag2" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Two backup tags required"}' | jq .
        else
            print_status $RED "❌ Error: Two backup tags required"
            echo "Usage: deploy.sh digests compare <tag1> <tag2> [--json]"
        fi
        return 1
    fi

    if [ "$use_json" != true ]; then
        print_status $BLUE "🔍 Comparing deployments"
        echo "  Tag 1: $tag1"
        echo "  Tag 2: $tag2"
        echo ""
    fi

    # Fetch both metadata files
    local metadata1=$(fetch_deployment_metadata "$tag1")
    local metadata2=$(fetch_deployment_metadata "$tag2")

    if [ -z "$metadata1" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Metadata not found for tag1: '"$tag1"'"}' | jq .
        else
            print_status $RED "❌ Metadata not found for tag1: $tag1"
        fi
        return 1
    fi

    if [ -z "$metadata2" ]; then
        if [ "$use_json" = true ]; then
            echo '{"error":"Metadata not found for tag2: '"$tag2"'"}' | jq .
        else
            print_status $RED "❌ Metadata not found for tag2: $tag2"
        fi
        return 1
    fi

    # Extract container digests from both
    local cms1=$(echo "$metadata1" | sed -n '/"cms":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local www1=$(echo "$metadata1" | sed -n '/"www":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local waf1=$(echo "$metadata1" | sed -n '/"waf":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')

    local cms2=$(echo "$metadata2" | sed -n '/"cms":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local www2=$(echo "$metadata2" | sed -n '/"www":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')
    local waf2=$(echo "$metadata2" | sed -n '/"waf":/,/"digest":/p' | grep '"digest"' | head -1 | sed 's/.*"digest": *"\([^"]*\)".*/\1/')

    # Extract timestamps
    local timestamp1=$(echo "$metadata1" | grep '"timestamp"' | sed 's/.*"timestamp": *"\([^"]*\)".*/\1/')
    local timestamp2=$(echo "$metadata2" | grep '"timestamp"' | sed 's/.*"timestamp": *"\([^"]*\)".*/\1/')

    # Extract tickets
    local ticket1=$(echo "$metadata1" | grep '"ticket"' | sed 's/.*"ticket": *"\([^"]*\)".*/\1/')
    local ticket2=$(echo "$metadata2" | grep '"ticket"' | sed 's/.*"ticket": *"\([^"]*\)".*/\1/')

    # Compare containers
    local cms_changed=false
    local www_changed=false
    local waf_changed=false
    local changes_count=0

    if [ "$cms1" != "$cms2" ]; then
        cms_changed=true
        changes_count=$((changes_count + 1))
    fi
    if [ "$www1" != "$www2" ]; then
        www_changed=true
        changes_count=$((changes_count + 1))
    fi
    if [ "$waf1" != "$waf2" ]; then
        waf_changed=true
        changes_count=$((changes_count + 1))
    fi

    # Output results
    if [ "$use_json" = true ]; then
        local json_output=$(cat <<EOF
{
  "tag1": "$tag1",
  "tag2": "$tag2",
  "timestamp1": "$timestamp1",
  "timestamp2": "$timestamp2",
  "ticket1": "$ticket1",
  "ticket2": "$ticket2",
  "changes_count": $changes_count,
  "containers": {
    "cms": {
      "changed": $cms_changed,
      "tag1_digest": "$cms1",
      "tag2_digest": "$cms2"
    },
    "www": {
      "changed": $www_changed,
      "tag1_digest": "$www1",
      "tag2_digest": "$www2"
    },
    "waf": {
      "changed": $waf_changed,
      "tag1_digest": "$waf1",
      "tag2_digest": "$waf2"
    }
  }
}
EOF
)
        format_json "$json_output"
    else
        # Table output
        if [ $changes_count -eq 0 ]; then
            print_status $GREEN "✅ No changes detected - deployments are identical"
        else
            print_status $YELLOW "📊 Found $changes_count container change(s)"
        fi
        echo ""

        echo "Metadata Comparison:"
        echo "  Timestamp 1: ${timestamp1:-unknown}"
        echo "  Timestamp 2: ${timestamp2:-unknown}"
        echo "  Ticket 1:    ${ticket1:-none}"
        echo "  Ticket 2:    ${ticket2:-none}"
        echo ""

        echo "Container Digest Comparison:"
        echo ""

        # CMS
        if [ "$cms_changed" = true ]; then
            print_status $YELLOW "  CMS: CHANGED"
            echo "    Tag 1: $cms1"
            echo "    Tag 2: $cms2"
        else
            print_status $GREEN "  CMS: UNCHANGED"
            echo "    Digest: $cms1"
        fi
        echo ""

        # WWW
        if [ "$www_changed" = true ]; then
            print_status $YELLOW "  WWW: CHANGED"
            echo "    Tag 1: $www1"
            echo "    Tag 2: $www2"
        else
            print_status $GREEN "  WWW: UNCHANGED"
            echo "    Digest: $www1"
        fi
        echo ""

        # WAF
        if [ "$waf_changed" = true ]; then
            print_status $YELLOW "  WAF: CHANGED"
            echo "    Tag 1: $waf1"
            echo "    Tag 2: $waf2"
        else
            print_status $GREEN "  WAF: UNCHANGED"
            echo "    Digest: $waf1"
        fi
    fi

    return 0
}

# Check if a backup tag exists (all components: static, public, db)
# Args: <tag>
# Returns: 0 if all components exist, 1 if any missing
check_backup_exists() {
    local backup_tag="$1"

    if [ -z "$backup_tag" ]; then
        print_status $RED "❌ Error: Backup tag required"
        return 1
    fi

    # Check via cf ssh to cms container (has S3 access)
    local check_result=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system >/dev/null 2>&1 && setup_s3_vars && \
        static_exists=\$(aws s3 ls s3://\$BUCKET_NAME/\$AUTO_STATIC_BACKUP_PATH/$backup_tag/ \$S3_EXTRA_PARAMS 2>/dev/null | wc -l) && \
        public_exists=\$(aws s3 ls s3://\$BUCKET_NAME/\$AUTO_PUBLIC_BACKUP_PATH/$backup_tag/ \$S3_EXTRA_PARAMS 2>/dev/null | wc -l) && \
        db_exists=\$(aws s3 ls s3://\$BUCKET_NAME/\$S3_DB_PATH/$backup_tag.sql.gz \$S3_EXTRA_PARAMS 2>/dev/null | wc -l) && \
        echo \"\$static_exists|\$public_exists|\$db_exists\"" 2>/dev/null | tail -1)

    if [ -z "$check_result" ]; then
        return 1
    fi

    local static_count=$(echo "$check_result" | cut -d'|' -f1 | tr -d ' ')
    local public_count=$(echo "$check_result" | cut -d'|' -f2 | tr -d ' ')
    local db_count=$(echo "$check_result" | cut -d'|' -f3 | tr -d ' ')

    # Return success only if all components exist (count > 0)
    if [ "$static_count" -gt 0 ] && [ "$public_count" -gt 0 ] && [ "$db_count" -gt 0 ]; then
        return 0
    else
        return 1
    fi
}

# Validate that a backup tag exists before attempting operations
# Args: <tag> [--quiet]
# Returns: 0 if exists, 1 if missing
validate_backup_tag_exists() {
    local backup_tag="$1"
    local quiet=false

    if [ "$2" = "--quiet" ]; then
        quiet=true
    fi

    if [ -z "$backup_tag" ]; then
        [ "$quiet" = false ] && print_status $RED "❌ Error: Backup tag required"
        return 1
    fi

    if [ "$quiet" = false ]; then
        print_status $BLUE "🔍 Checking if backup exists: $backup_tag"
    fi

    if check_backup_exists "$backup_tag"; then
        if [ "$quiet" = false ]; then
            print_status $GREEN "✅ Backup found: All components present"
        fi
        return 0
    else
        if [ "$quiet" = false ]; then
            print_status $RED "❌ Backup incomplete or not found"
            echo "Use 'deploy.sh list-backups' to see available backups."
        fi
        return 1
    fi
}

# Rollback command - uses backup metadata to restore containers
rollback() {
    local data_types=""
    local cms_digest=""
    local www_digest=""
    local waf_digest=""
    local backup_tag=""
    local skip_validation=""
    local skip_confirmation=""
    local apps="cms,www,waf"

    # Parse first positional arg as backup tag (optional)
    if [ -n "$1" ] && [ "${1:0:2}" != "--" ]; then
        backup_tag="$1"
        shift
    fi

    # Parse flags
    while [ $# -gt 0 ]; do
        case "$1" in
            --apps=*)
                apps="${1#*=}"
                shift
                ;;
            --restore=*)
                data_types="${1#*=}"
                shift
                ;;
            --skip-validation)
                skip_validation="--skip-validation"
                shift
                ;;
            --skip-confirmation)
                skip_confirmation="--skip-confirmation"
                shift
                ;;
            *)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: deploy.sh rollback [backup-tag] [--apps=cms,www,waf] [--restore=db,static,public,all] [--skip-validation] [--skip-confirmation]"
                return 1
                ;;
        esac
    done

    # Validate CF space matches DEPLOY_ENV
    validate_target_space "$skip_validation"

    # Fetch metadata from backup tag (or latest if empty)
    print_status $BLUE "📦 Fetching backup metadata..."
    local metadata_json=$(fetch_deployment_metadata "$backup_tag")

    if [ -z "$metadata_json" ]; then
        if [ -z "$backup_tag" ]; then
            print_status $RED "❌ Error: No backup metadata found"
        else
            print_status $RED "❌ Error: Backup metadata not found for tag: $backup_tag"
        fi
        echo "Use 'deploy.sh list-backups' to see available backups."
        return 3
    fi

    # Extract backup tag from metadata if it was auto-detected
    if [ -z "$backup_tag" ]; then
        backup_tag=$(echo "$metadata_json" | sed -n 's/.*"backup_tag":[[:space:]]*"\([^"]*\)".*/\1/p')
        print_status $GREEN "✅ Using latest backup: $backup_tag"
    fi

    # Extract digests from metadata
    local digests=$(extract_digests_from_metadata "$metadata_json" "$apps")

    # Parse digests based on app count
    cms_digest=$(echo "$digests" | sed -n '1p')
    www_digest=$(echo "$digests" | sed -n '2p')
    waf_digest=$(echo "$digests" | sed -n '3p')

    if [ -z "$cms_digest" ] || [ -z "$www_digest" ] || [ -z "$waf_digest" ]; then
        print_status $RED "❌ Error: Could not extract container digests from metadata"
        echo "Metadata may be incomplete or corrupted."
        return 3
    fi

    # Determine environment
    local env="${DEPLOY_ENV:-$(cf target | grep 'space:' | awk '{print $2}')}"
    if [ -z "$env" ]; then
        print_status $RED "❌ Error: Could not determine environment"
        echo "Set DEPLOY_ENV or use 'cf target -s <space>'"
        return 3
    fi

    # Extract CCI build from digest using common function with motd fallback
    local cci_build=$(extract_build_from_digest "$cms_digest")

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
            return 2
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
            all)
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
    local use_json=false

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
            --json)
                use_json=true
                shift
                ;;
            *)
                print_status $RED "❌ Unknown option: $1"
                echo "Usage: validate [--only=app1,app2] [--commit=sha] [--skip-http] [--json]"
                return 2
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

    if [ "$use_json" = false ]; then
        print_status $BLUE "🔍 Validating deployment..."
        echo "Expected commit: ${expected_commit:0:8}"
        echo "Apps to validate: $apps_to_validate"
        echo ""
    fi

    local overall_success=true
    local json_app_results=""
    local app_count=0
    local IFS=','

    for app in $apps_to_validate; do
        [ -z "$app" ] && continue
        app_count=$((app_count + 1))

        if [ "$use_json" = false ]; then
            print_status $BLUE "📦 Validating app: $app"
            echo "=========================================="
        fi

        # Initialize app validation vars
        local app_accessible=true
        local app_state=""
        local instances_running=0
        local instances_total="0/0"
        local deployed_digest=""
        local services_status="ok"
        local health_status="ok"
        local http_status=""
        local http_url=""
        local state_ok=false
        local instances_ok=false
        local services_ok=false
        local health_ok=false
        local http_ok=false

        # Check if app exists and is running
        local app_info
        app_info=$(cf app "$app" 2>&1)

        if [ $? -ne 0 ]; then
            app_accessible=false
            overall_success=false
            if [ "$use_json" = false ]; then
                print_status $RED "❌ App '$app' not found or not accessible"
                echo ""
            fi

            # Add to JSON results
            if [ "$use_json" = true ]; then
                if [ $app_count -gt 1 ]; then
                    json_app_results="${json_app_results},"
                fi
                json_app_results="${json_app_results}\"$app\":{\"accessible\":false,\"validation_passed\":false}"
            fi
            continue
        fi

        # Check app state
        app_state=$(echo "$app_info" | grep "^requested state:" | awk '{print $3}')

        if [ "$app_state" != "started" ]; then
            state_ok=false
            overall_success=false
            if [ "$use_json" = false ]; then
                print_status $RED "  ❌ App state: $app_state"
            fi
        else
            state_ok=true
            if [ "$use_json" = false ]; then
                print_status $GREEN "  ✅ App state: started"
            fi
        fi

        # Check instances
        instances_total=$(echo "$app_info" | grep "^instances:" | awk '{print $2}')

        if echo "$app_info" | grep -q "^\#[0-9].*running"; then
            instances_running=$(echo "$app_info" | grep "^\#[0-9].*running" | wc -l | tr -d ' ')
            instances_ok=true
            if [ "$use_json" = false ]; then
                print_status $GREEN "  ✅ Instances: $instances_running running ($instances_total)"
            fi
        else
            instances_running=0
            instances_ok=false
            overall_success=false
            if [ "$use_json" = false ]; then
                print_status $RED "  ❌ Instances: none running ($instances_total)"
            fi
        fi

        # Check deployed Docker digest
        deployed_digest=$(echo "$app_info" | grep "^docker image:" | awk '{print $3}')

        if [ -n "$deployed_digest" ]; then
            local short_digest="${deployed_digest##*sha256:}"
            short_digest="${short_digest:0:12}"
            if [ "$use_json" = false ]; then
                print_status $GREEN "  ✅ Digest: sha256:${short_digest}..."
            fi
        else
            deployed_digest="unknown"
            if [ "$use_json" = false ]; then
                print_status $YELLOW "  ⚠️  Digest: unknown"
            fi
        fi

        # Get services bound to this app from cf services output
        local bound_services
        bound_services=$(cf services 2>/dev/null | grep -w "$app" | awk '{print $1}' | tr '\n' ',' | sed 's/,$//')

        if [ -n "$bound_services" ]; then
            # Get expected services from manifest based on app name
            local expected_services=""
            case "$app" in
                cms)
                    expected_services="database,secrets,secauthsecrets,storage"
                    ;;
                www|waf)
                    expected_services="secrets,storage"
                    ;;
            esac

            if [ -n "$expected_services" ]; then
                local missing_services=""
                local saved_ifs="$IFS"
                IFS=','
                for expected in $expected_services; do
                    if ! echo "$bound_services" | grep -q "$expected"; then
                        missing_services="${missing_services}${expected}, "
                    fi
                done
                IFS="$saved_ifs"

                if [ -z "$missing_services" ]; then
                    services_ok=true
                    services_status="ok"
                    local service_count=$(echo "$bound_services" | tr ',' '\n' | wc -l | tr -d ' ')
                    if [ "$use_json" = false ]; then
                        print_status $GREEN "  ✅ Service bindings: all required bound ($service_count total)"
                    fi
                else
                    services_ok=false
                    services_status="missing: ${missing_services%, }"
                    overall_success=false
                    if [ "$use_json" = false ]; then
                        print_status $RED "  ❌ Service bindings: missing ${missing_services%, }"
                    fi
                fi
            else
                services_ok=true
                services_status="ok"
                local service_count=$(echo "$bound_services" | tr ',' '\n' | wc -l | tr -d ' ')
                if [ "$use_json" = false ]; then
                    print_status $GREEN "  ✅ Service bindings: $service_count bound"
                fi
            fi
        else
            services_status="none"
            if [ "$use_json" = false ]; then
                print_status $YELLOW "  ⚠️  Service bindings: none (may be expected)"
            fi
        fi

        # Check for recent crashes/restarts
        local recent_events
        recent_events=$(cf events "$app" 2>/dev/null | grep -E "crash|restart" | head -3)

        local crash_count=0
        if [ -n "$recent_events" ]; then
            crash_count=$(echo "$recent_events" | wc -l | tr -d ' ')
            if [ "$use_json" = false ]; then
                print_status $YELLOW "  ⚠️  Stability: $crash_count recent crash/restart events"
                echo "$recent_events" | sed 's/^/      /'
            fi
        else
            if [ "$use_json" = false ]; then
                print_status $GREEN "  ✅ Stability: no recent crashes or restarts"
            fi
        fi

        # Different apps have different services
        local health_cmd=""
        local expected_health_services=""
        case "$app" in
            cms)
                health_cmd="s6-svstat /var/run/s6/services/nginx 2>&1 && s6-svstat /var/run/s6/services/php 2>&1"
                expected_health_services="nginx, php"
                ;;
            www|waf)
                health_cmd="s6-svstat /var/run/s6/services/nginx 2>&1"
                expected_health_services="nginx"
                ;;
            *)
                health_cmd="s6-svstat /var/run/s6/services/nginx 2>&1"
                expected_health_services="nginx"
                ;;
        esac

        local health_check
        health_check=$(cf ssh "$app" -c "$health_cmd" 2>/dev/null | grep -c "^up")

        local expected_count=$(echo "$expected_health_services" | tr ',' '\n' | wc -l | tr -d ' ')

        if [ "$health_check" -ge "$expected_count" ]; then
            health_ok=true
            health_status="ok"
            if [ "$use_json" = false ]; then
                print_status $GREEN "  ✅ Container health: $expected_health_services running"
            fi
        elif [ "$health_check" -gt 0 ]; then
            health_ok=false
            health_status="partial: $health_check/$expected_count services running"
            overall_success=false
            if [ "$use_json" = false ]; then
                print_status $YELLOW "  ⚠️  Container health: only $health_check/$expected_count services running"
            fi
        else
            health_ok=false
            health_status="down"
            overall_success=false
            if [ "$use_json" = false ]; then
                print_status $RED "  ❌ Container health: services not responding"
            fi
        fi

        # HTTP endpoint check (if not skipped)
        if [ "$skip_http" = false ]; then
            # Get all routes and filter out .apps.internal (CF-internal routes not publicly accessible)
            http_url=$(cf app "$app" | grep "^routes:" | sed 's/^routes:\s*//' | tr ',' '\n' | grep -v '\.apps\.internal' | head -1 | xargs)

            if [ -n "$http_url" ]; then
                http_status=$(curl -s -o /dev/null -w "%{http_code}" -L "https://$http_url" --max-time 10 2>/dev/null)

                if [ "$http_status" = "200" ]; then
                    http_ok=true
                    if [ "$use_json" = false ]; then
                        print_status $GREEN "  ✅ HTTP endpoint: $http_status OK (https://$http_url)"
                    fi
                elif [ "$http_status" = "000" ]; then
                    http_ok=false
                    if [ "$use_json" = false ]; then
                        print_status $YELLOW "  ⚠️  HTTP endpoint: $http_status network/firewall (https://$http_url)"
                    fi
                elif [ -n "$http_status" ]; then
                    http_ok=false
                    if [ "$use_json" = false ]; then
                        print_status $YELLOW "  ⚠️  HTTP endpoint: $http_status (https://$http_url)"
                    fi
                else
                    http_ok=false
                    overall_success=false
                    if [ "$use_json" = false ]; then
                        print_status $RED "  ❌ HTTP endpoint: no response (https://$http_url)"
                    fi
                fi
            else
                http_status="unknown"
                http_url="unknown"
                if [ "$use_json" = false ]; then
                    print_status $YELLOW "  ⚠️  HTTP endpoint: could not determine URL"
                fi
            fi
        else
            http_status="skipped"
            http_url="skipped"
        fi

        if [ "$use_json" = false ]; then
            echo ""
        fi

        # Determine if this app passed validation
        local app_passed=true
        if [ "$app_accessible" = false ] || [ "$state_ok" = false ] || [ "$instances_ok" = false ] || [ "$services_ok" = false ] || [ "$health_ok" = false ]; then
            app_passed=false
        fi
        if [ "$skip_http" = false ] && [ "$http_ok" = false ] && [ "$http_status" != "unknown" ]; then
            app_passed=false
        fi

        # Build JSON for this app
        if [ "$use_json" = true ]; then
            if [ $app_count -gt 1 ]; then
                json_app_results="${json_app_results},"
            fi
            json_app_results="${json_app_results}\"$app\":{\"accessible\":true,\"state\":\"$app_state\",\"state_ok\":$state_ok,\"instances_running\":$instances_running,\"instances_total\":\"$instances_total\",\"instances_ok\":$instances_ok,\"digest\":\"$deployed_digest\",\"services_status\":\"$services_status\",\"services_ok\":$services_ok,\"crash_count\":$crash_count,\"health_status\":\"$health_status\",\"health_ok\":$health_ok"
            if [ "$skip_http" = false ]; then
                json_app_results="${json_app_results},\"http_status\":\"$http_status\",\"http_url\":\"$http_url\",\"http_ok\":$http_ok"
            fi
            json_app_results="${json_app_results},\"validation_passed\":$app_passed}"
        fi
    done

    # Check if pre-deploy backups were created (if context is set)
    local backup_check_results=""
    local backup_check_failed=false

    if [ -n "$DEPLOY_ROLLBACK_STATIC_TAG" ] || [ -n "$DEPLOY_ROLLBACK_PUBLIC_TAG" ] || [ -n "$DEPLOY_ROLLBACK_DB_TAG" ]; then
        if [ "$use_json" = false ]; then
            print_status $BLUE "📦 Checking pre-deploy backups..."
            echo "----------------------------------------"
        fi

        local static_exists=false
        local public_exists=false
        local db_exists=false

        if [ -n "$DEPLOY_ROLLBACK_STATIC_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info static "$DEPLOY_ROLLBACK_STATIC_TAG" >/dev/null 2>&1; then
                static_exists=true
                if [ "$use_json" = false ]; then
                    print_status $GREEN "✅ Static backup exists: $DEPLOY_ROLLBACK_STATIC_TAG"
                fi
            else
                backup_check_failed=true
                if [ "$use_json" = false ]; then
                    print_status $RED "❌ Static backup not found: $DEPLOY_ROLLBACK_STATIC_TAG"
                fi
            fi
            backup_check_results="${backup_check_results}\"static\":{\"tag\":\"$DEPLOY_ROLLBACK_STATIC_TAG\",\"exists\":$static_exists},"
        fi

        if [ -n "$DEPLOY_ROLLBACK_PUBLIC_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info public "$DEPLOY_ROLLBACK_PUBLIC_TAG" >/dev/null 2>&1; then
                public_exists=true
                if [ "$use_json" = false ]; then
                    print_status $GREEN "✅ Public backup exists: $DEPLOY_ROLLBACK_PUBLIC_TAG"
                fi
            else
                backup_check_failed=true
                if [ "$use_json" = false ]; then
                    print_status $RED "❌ Public backup not found: $DEPLOY_ROLLBACK_PUBLIC_TAG"
                fi
            fi
            backup_check_results="${backup_check_results}\"public\":{\"tag\":\"$DEPLOY_ROLLBACK_PUBLIC_TAG\",\"exists\":$public_exists},"
        fi

        if [ -n "$DEPLOY_ROLLBACK_DB_TAG" ]; then
            if "$SCRIPT_DIR/manager.sh" info db "$DEPLOY_ROLLBACK_DB_TAG" >/dev/null 2>&1; then
                db_exists=true
                if [ "$use_json" = false ]; then
                    print_status $GREEN "✅ Database backup exists: $DEPLOY_ROLLBACK_DB_TAG"
                fi
            else
                backup_check_failed=true
                if [ "$use_json" = false ]; then
                    print_status $RED "❌ Database backup not found: $DEPLOY_ROLLBACK_DB_TAG"
                fi
            fi
            backup_check_results="${backup_check_results}\"database\":{\"tag\":\"$DEPLOY_ROLLBACK_DB_TAG\",\"exists\":$db_exists}"
        else
            # Remove trailing comma if db wasn't checked
            backup_check_results=$(echo "$backup_check_results" | sed 's/,$//')
        fi

        if [ "$backup_check_failed" = true ]; then
            overall_success=false
        fi

        if [ "$use_json" = false ]; then
            echo ""
        fi
    fi

    # Output results
    if [ "$use_json" = true ]; then
        local json_data="{"
        json_data="${json_data}\"expected_commit\":\"${expected_commit:0:8}\","
        json_data="${json_data}\"apps_validated\":\"$apps_to_validate\","
        json_data="${json_data}\"validation_passed\":$overall_success,"
        json_data="${json_data}\"apps\":{$json_app_results}"
        if [ -n "$backup_check_results" ]; then
            json_data="${json_data},\"pre_deploy_backups\":{$backup_check_results}"
        fi
        json_data="${json_data}}"

        format_json "$json_data"
    else
        # Final summary for table output
        if [ "$overall_success" = true ]; then
            print_status $GREEN "✅ Deployment validation PASSED"
        else
            print_status $RED "❌ Deployment validation FAILED"
        fi
    fi

    if [ "$overall_success" = true ]; then
        return 0
    else
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
        show_context "$@"
        ;;
    "clear-context")
        clear_context "$@"
        ;;
    "contexts")
        # Handle contexts subcommands
        subcommand="$1"
        shift || true

        case "$subcommand" in
            "list"|"")
                list_contexts "$@"
                ;;
            *)
                print_status $RED "❌ Unknown contexts subcommand: $subcommand"
                echo "Usage: deploy.sh contexts list [limit] [--json]"
                exit 1
                ;;
        esac
        ;;
    "last-backup")
        last_backup "$@"
        ;;
    "status")
        show_status "$@"
        ;;
    "motd")
        show_motd
        ;;
    "ccb")
        show_changes "$@"
        ;;
    "digests")
        # Handle digests subcommands
        subcommand="$1"

        # Handle help flags at this level
        if [ "$subcommand" = "-h" ] || [ "$subcommand" = "--help" ]; then
            # Show general digests help
            echo "Container Digest Commands"
            echo ""
            echo "Usage: deploy.sh digests <subcommand> [options]"
            echo ""
            echo "Subcommands:"
            echo "  current [space]               Show what's CURRENTLY RUNNING"
            echo "                                Use: Verify deployment, check live state"
            echo ""
            echo "  build [env]                   Show what was BUILT in latest CircleCI build"
            echo "                                Use: Get digests to deploy (defaults to current space)"
            echo ""
            echo "  history [env] [days] [limit]  Show deployment history"
            echo "                                Flags: --backups-only, --git-only, --json"
            echo ""
            echo "  validate <tag>                Verify backup metadata completeness"
            echo "                                Flags: --json"
            echo ""
            echo "  compare <tag1> <tag2>         Compare two deployment states"
            echo "                                Flags: --json"
            echo ""
            echo "Examples:"
            echo "  deploy.sh digests current"
            echo "  deploy.sh digests build prod"
            echo "  deploy.sh digests history prod 7"
            echo "  deploy.sh digests validate USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
            echo "  deploy.sh digests compare tag1 tag2"
            echo ""
            echo "Get detailed help:"
            echo "  deploy.sh digests current -h"
            echo "  deploy.sh digests build -h"
            echo "  deploy.sh digests validate -h"
            echo "  deploy.sh digests compare -h"
            exit 0
        fi

        shift

        # Handle subcommand-specific help
        if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
            case "$subcommand" in
                current)
                    echo "Show Currently Running Container Digests"
                    echo ""
                    echo "Usage: deploy.sh digests current [space] [--json]"
                    echo ""
                    echo "Description:"
                    echo "  Shows what container digests are CURRENTLY RUNNING in the environment."
                    echo "  Source: Cron bucket file updated every 5 minutes by automated capture."
                    echo ""
                    echo "When to use this:"
                    echo "  • Verify a deployment worked (check if new digest is running)"
                    echo "  • See what's actually deployed right now"
                    echo "  • Check what backup metadata would contain"
                    echo "  • Compare running state across environments"
                    echo ""
                    echo "Arguments:"
                    echo "  space - Optional space name (dr, stage, prod). Defaults to current space."
                    echo "          If different from current, will switch spaces temporarily."
                    echo "  --json - Output in JSON format"
                    echo ""
                    echo "Examples:"
                    echo "  deploy.sh digests current        # Show what's running in current space"
                    echo "  deploy.sh digests current stage  # Show what's running in stage"
                    echo "  deploy.sh digests current --json # Show current digests in JSON"
                    exit 0
                    ;;
                build)
                    echo "Show CircleCI Build Information"
                    echo ""
                    echo "Usage: deploy.sh digests build [env] [--json]"
                    echo ""
                    echo "Description:"
                    echo "  Shows container digests from the latest CircleCI BUILD for an environment."
                    echo "  Source: Annotated git tags created by CircleCI pipeline."
                    echo "  Shows: CMS, WAF, WWW containers from that build (not cron/analytics)."
                    echo ""
                    echo "When to use this:"
                    echo "  • Get container digests to deploy a specific CircleCI build"
                    echo "  • See what was built in the latest pipeline run"
                    echo "  • Find the build number and digests for deployment commands"
                    echo ""
                    echo "Arguments:"
                    echo "  env    - Environment (dev, stage, prod, dr). Defaults to current space."
                    echo "  --json - Output in JSON format"
                    echo ""
                    echo "Examples:"
                    echo "  deploy.sh digests build          # Show latest build for current space"
                    echo "  deploy.sh digests build prod     # Show latest build for prod"
                    echo "  deploy.sh digests build --json   # Show build info in JSON"
                    echo "  # Then use the digests shown to deploy:"
                    echo "  deploy.sh push cms 12034 @sha256:abc..."
                    exit 0
                    ;;
                history)
                    echo "Show Deployment History with Container Digests"
                    echo ""
                    echo "Usage: deploy.sh digests history [env] [days] [limit] [flags]"
                    echo ""
                    echo "Description:"
                    echo "  Shows comprehensive deployment history including:"
                    echo "  • Currently deployed containers (from Cloud Foundry)"
                    echo "  • Previous deployment (from CF history)"
                    echo "  • Recent deployments (from git tags)"
                    echo "  • Backup history with digests (from S3 metadata)"
                    echo ""
                    echo "Arguments:"
                    echo "  env   - Environment (dev, stage, prod, dr, all). Defaults to current space."
                    echo "  days  - Show backups from last N days (default: 7)"
                    echo "  limit - Limit git tag results (default: 10)"
                    echo ""
                    echo "Flags:"
                    echo "  --backups-only       Show only backup history (skip CF and git)"
                    echo "  --git-only           Show only git tag history (skip CF and backups)"
                    echo "  --show-all-history   Show all git tags (don't filter by 1 year)"
                    echo "  --json               Output in JSON format"
                    echo ""
                    echo "Examples:"
                    echo "  deploy.sh digests history                    # Current space, last 7 days"
                    echo "  deploy.sh digests history prod               # Prod env, last 7 days"
                    echo "  deploy.sh digests history prod 14            # Prod env, last 14 days"
                    echo "  deploy.sh digests history prod 7 20          # Prod env, 7 days, limit 20"
                    echo "  deploy.sh digests history --git-only         # Only show git deployments"
                    echo "  deploy.sh digests history --backups-only     # Only show backups"
                    echo "  deploy.sh digests history --show-all-history # Show all deployments"
                    exit 0
                    ;;
                validate)
                    echo "Validate Backup Metadata Completeness"
                    echo ""
                    echo "Usage: deploy.sh digests validate <tag> [--json]"
                    echo ""
                    echo "Description:"
                    echo "  Validates that backup metadata exists and contains all required fields."
                    echo "  Checks for: backup_tag, timestamp, environment, container digests."
                    echo "  Verifies digest format and flags any missing or invalid data."
                    echo ""
                    echo "When to use this:"
                    echo "  • Before attempting a rollback operation"
                    echo "  • To verify backup integrity"
                    echo "  • To troubleshoot deployment metadata issues"
                    echo "  • In CI/CD pipelines to validate backup creation"
                    echo ""
                    echo "Arguments:"
                    echo "  tag    - Backup tag to validate"
                    echo "  --json - Output in JSON format"
                    echo ""
                    echo "Examples:"
                    echo "  deploy.sh digests validate USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
                    echo "  deploy.sh digests validate \$(deploy.sh last-backup) --json"
                    echo ""
                    echo "Exit codes:"
                    echo "  0 - Metadata is valid and complete"
                    echo "  1 - Metadata is missing or invalid"
                    echo "  2 - Invalid arguments"
                    exit 0
                    ;;
                compare)
                    echo "Compare Two Deployment States"
                    echo ""
                    echo "Usage: deploy.sh digests compare <tag1> <tag2> [--json]"
                    echo ""
                    echo "Description:"
                    echo "  Compares container digests between two backup metadata files."
                    echo "  Shows which containers changed between the two deployments."
                    echo "  Useful for understanding what changed between releases."
                    echo ""
                    echo "When to use this:"
                    echo "  • Before/after deployment comparison"
                    echo "  • Verify what changed in a rollback"
                    echo "  • Audit deployment differences"
                    echo "  • Understand deployment progression"
                    echo ""
                    echo "Arguments:"
                    echo "  tag1   - First backup tag to compare"
                    echo "  tag2   - Second backup tag to compare"
                    echo "  --json - Output in JSON format"
                    echo ""
                    echo "Examples:"
                    echo "  deploy.sh digests compare tag-before tag-after"
                    echo "  deploy.sh digests compare \$(deploy.sh last-backup prod) \$(deploy.sh last-backup stage)"
                    echo "  deploy.sh digests compare tag1 tag2 --json | jq '.containers.cms.changed'"
                    exit 0
                    ;;
            esac
        fi

        case "$subcommand" in
            current)
                show_current_digests_wrapper "$@"
                ;;
            build)
                show_build_digests "$@"
                ;;
            validate)
                validate_digest_metadata "$@"
                ;;
            compare)
                compare_digest_metadata "$@"
                ;;
            history|*)
                # Default to history for backward compatibility or when no subcommand
                if [ "$subcommand" = "history" ]; then
                    list_digests "$@"
                else
                    # If subcommand looks like an env name or flag, treat as history
                    list_digests "$subcommand" "$@"
                fi
                ;;
        esac
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
        tome_log "$@"
        ;;
    "state")
        # state <action> <type> [max_wait_mins] - Manage Drupal state
        action="$1"
        state_type="${2:-both}"
        max_wait="${3:-25}"

        if [ -z "$action" ]; then
            print_status $RED "❌ Error: action required (enable|disable)"
            echo "Usage: deploy.sh state <action> <type> [max_wait_mins]"
            exit 1
        fi

        cf ssh cms -c "source /etc/profile && cd /var/www && . scripts/common.sh && state_command '$action' '$state_type' '$max_wait'"
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
