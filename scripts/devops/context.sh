#!/bin/sh
# context.sh - Deployment context management.
#
# Source this file to set deployment context for your shell:
#
#   . scripts/devops/context.sh set prod USAGOV-1234
#   . scripts/devops/context.sh show
#   . scripts/devops/context.sh clear
#
# Or source with no arguments to load the functions for repeated use:
#
#   . scripts/devops/context.sh
#   set_context prod USAGOV-1234
#   ...
#   clear_context
#
# On dash, source with no arguments first, then call the functions directly -
# dash does not forward arguments through `.`/`source` the way bash and zsh do.

# Locate scripts/common.sh by walking up from the current directory (same
# convention as init_backup_system()).
_context_find_common_sh() {
    local dir
    dir="$(pwd)"
    while [ "$dir" != "/" ]; do
        if [ -f "$dir/scripts/common.sh" ]; then
            echo "$dir/scripts/common.sh"
            return 0
        fi
        dir=$(dirname "$dir")
    done
    return 1
}

_context_common_sh=$(_context_find_common_sh)
if [ -z "$_context_common_sh" ]; then
    echo "❌ Could not locate scripts/common.sh - run this from inside the project directory." >&2
else
    . "$_context_common_sh"
fi
unset _context_common_sh

# Detect whether this file was sourced, to decide whether to print a
# reminder to source it. Reliable in bash and zsh; dash always gets the
# reminder.
_context_looks_sourced=false
case "$ZSH_EVAL_CONTEXT" in
    *:file) _context_looks_sourced=true ;;
esac
if [ -n "$BASH_VERSION" ]; then
    if [ "${BASH_SOURCE:-}" != "${0:-}" ]; then
        _context_looks_sourced=true
    else
        _context_looks_sourced=false
    fi
fi

_context_source_hint() {
    if [ "$_context_looks_sourced" != "true" ]; then
        echo ""
        print_status $YELLOW "💡 Tip: source this script for these variables to persist in your shell:"
        echo "   . scripts/devops/context.sh $*"
    fi
}

# Set deployment context for this shell session.
# Args:
#   $1: env - environment name (e.g. dev, stage, prod, dr)
#   $2: ticket - JIRA ticket number (e.g. USAGOV-1234)
#   $3: pre_suffix - optional pre-deployment backup suffix (default: pre-deploy)
#   $4: post_suffix - optional post-deployment backup suffix (default: post-deploy)
#   --from-tag=TAG - extract env/ticket from a backup tag instead
# Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX,
#       DEPLOY_ROLLBACK_STATIC_TAG, DEPLOY_ROLLBACK_PUBLIC_TAG, DEPLOY_ROLLBACK_DB_TAG
set_context() {
    local env=""
    local ticket=""
    local pre_suffix="pre-deploy"
    local post_suffix="post-deploy"
    local from_tag=""

    while [ $# -gt 0 ]; do
        case "$1" in
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
                echo "Usage: . scripts/devops/context.sh set <env> <ticket> [pre-suffix] [post-suffix] [--from-tag=TAG]"
                return 2
                ;;
            *)
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
                    echo "Usage: . scripts/devops/context.sh set <env> <ticket> [pre-suffix] [post-suffix] [--from-tag=TAG]"
                    return 2
                fi
                shift
                ;;
        esac
    done

    if [ -n "$from_tag" ]; then
        print_status $BLUE "🔍 Parsing backup tag: $from_tag"

        local parsed
        parsed=$(parse_backup_tag "$from_tag")

        if [ $? -ne 0 ] || [ -z "$parsed" ]; then
            print_status $RED "❌ Error: Could not parse backup tag: $from_tag"
            return 2
        fi

        ticket=$(echo "$parsed" | cut -d'|' -f1)
        env=$(echo "$parsed" | cut -d'|' -f2)

        print_status $GREEN "✅ Extracted: ticket=$ticket, env=$env"
        echo ""
    fi

    if [ -z "$env" ] || [ -z "$ticket" ]; then
        print_status $RED "❌ Error: Environment and ticket required"
        echo "Usage: . scripts/devops/context.sh set <env> <ticket> [pre-suffix] [post-suffix] [--from-tag=TAG]"
        echo ""
        echo "Examples:"
        echo "  . scripts/devops/context.sh set prod USAGOV-1234"
        echo "  . scripts/devops/context.sh set --from-tag=USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
        return 2
    fi

    # These are exported directly into your shell below - restrict each to
    # characters that can never be shell metacharacters.
    if ! validate_backup_tag "$env"; then
        return 2
    fi
    if ! validate_backup_tag "$ticket"; then
        return 2
    fi
    if ! validate_backup_tag "$pre_suffix"; then
        return 2
    fi
    if ! validate_backup_tag "$post_suffix"; then
        return 2
    fi

    print_status $BLUE "🔍 Capturing most recent backup tags for rollback..."

    # Query S3 to get the most recent valid backup tag for each type
    local backup_tags
    backup_tags=$(cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars && \
        echo 'STATIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_STATIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'PUBLIC:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_PUBLIC_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep 'PRE' | sort -r | head -1 | awk '{print \$2}' | tr -d '/' && \
        echo 'DB:' && aws s3 ls s3://\$BUCKET_NAME/\$AUTO_DB_BACKUP_PATH/ \$S3_EXTRA_PARAMS | grep '\.sql\.gz$' | sort -r | head -1 | awk '{print \$4}' | sed 's/\.sql\.gz$//'" 2>/dev/null)
    if [ $? -ne 0 ] || [ -z "$backup_tags" ]; then
        print_status $YELLOW "⚠️  Could not capture rollback tags from the cms container - DEPLOY_ROLLBACK_* will be empty"
        diagnose_cf_ssh_failure cms
    fi

    local static_tag=$(echo "$backup_tags" | grep -A1 "^STATIC:" | tail -1)
    local public_tag=$(echo "$backup_tags" | grep -A1 "^PUBLIC:" | tail -1)
    local db_tag=$(echo "$backup_tags" | grep -A1 "^DB:" | tail -1)

    export DEPLOY_ENV="$env"
    export DEPLOY_TICKET="$ticket"
    export DEPLOY_PRE_SUFFIX="$pre_suffix"
    export DEPLOY_POST_SUFFIX="$post_suffix"
    export DEPLOY_ROLLBACK_STATIC_TAG="$static_tag"
    export DEPLOY_ROLLBACK_PUBLIC_TAG="$public_tag"
    export DEPLOY_ROLLBACK_DB_TAG="$db_tag"

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
    _context_source_hint "set $env $ticket"
}

# Show current deployment context.
# Safe Operation: Read-only query, no resources modified
show_context() {
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
        echo "Run: . scripts/devops/context.sh set <env> <ticket>"
    fi
    echo ""
}

# Clear deployment context for this shell session.
clear_context() {
    if [ $# -gt 0 ]; then
        print_status $RED "❌ Unknown option: $1"
        echo "Usage: . scripts/devops/context.sh clear"
        return 2
    fi

    if [ -n "$DEPLOY_ENV" ] || [ -n "$DEPLOY_TICKET" ]; then
        print_status $YELLOW "🗑️  Clearing deployment context:"
        echo "  Current ENV: ${DEPLOY_ENV:-(not set)}"
        echo "  Current TICKET: ${DEPLOY_TICKET:-(not set)}"
        echo ""
    fi

    unset DEPLOY_ENV
    unset DEPLOY_TICKET
    unset DEPLOY_PRE_SUFFIX
    unset DEPLOY_POST_SUFFIX
    unset DEPLOY_ROLLBACK_STATIC_TAG
    unset DEPLOY_ROLLBACK_PUBLIC_TAG
    unset DEPLOY_ROLLBACK_DB_TAG

    print_status $GREEN "✅ Deployment context cleared"
    _context_source_hint "clear"
}

# Helper: Save context to history file for the 'contexts list' command
save_context_to_history() {
    local env="$1"
    local ticket="$2"
    local pre_suffix="${3:-pre-deploy}"
    local post_suffix="${4:-post-deploy}"

    local contexts_file="${HOME}/.deploy-contexts"
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")

    touch "$contexts_file"
    echo "${timestamp}|${env}|${ticket}|${pre_suffix}|${post_suffix}" >> "$contexts_file"

    # Keep only last 100 entries
    if [ -f "$contexts_file" ]; then
        local temp_file="${contexts_file}.tmp"
        tail -n 100 "$contexts_file" > "$temp_file"
        mv "$temp_file" "$contexts_file"
    fi
}

# Helper: Parse a backup tag to extract environment and ticket
# Tag format: {ticket}-{env}-{container}-{date}--{suffix}-{sequence}
# Example: USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0
parse_backup_tag() {
    local tag="$1"
    local base_part=$(echo "$tag" | sed 's/--.*$//')
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
        return 1
    fi

    echo "${ticket}|${env}"
    return 0
}

_context_usage() {
    echo "Deployment Context Management"
    echo ""
    echo "This must be sourced, not executed - otherwise the DEPLOY_* variables"
    echo "it sets only apply to a throwaway child process."
    echo ""
    echo "Usage:"
    echo "  . scripts/devops/context.sh set <env> <ticket> [pre-suffix] [post-suffix] [--from-tag=TAG]"
    echo "  . scripts/devops/context.sh show [--json]"
    echo "  . scripts/devops/context.sh clear"
    echo ""
    echo "Sets: DEPLOY_ENV, DEPLOY_TICKET, DEPLOY_PRE_SUFFIX, DEPLOY_POST_SUFFIX,"
    echo "      DEPLOY_ROLLBACK_STATIC_TAG, DEPLOY_ROLLBACK_PUBLIC_TAG, DEPLOY_ROLLBACK_DB_TAG"
    echo ""
    echo "Examples:"
    echo "  . scripts/devops/context.sh set prod USAGOV-1234"
    echo "  . scripts/devops/context.sh set stage USAGOV-5678 pre-deploy post-deploy"
    echo "  . scripts/devops/context.sh set --from-tag=USAGOV-1234-prod-12345-2025-12-22--pre-deploy-0"
    echo "  . scripts/devops/context.sh show"
    echo "  . scripts/devops/context.sh clear"
    echo ""
    echo "You can also source with no arguments to just load the functions for"
    echo "repeated use in the same shell session:"
    echo "  . scripts/devops/context.sh"
    echo "  set_context prod USAGOV-1234"
    echo "  ..."
    echo "  clear_context"
    echo ""
    echo "Note: on plain POSIX 'dash' (unlike bash/zsh), '.' does not forward"
    echo "arguments into the sourced file, so the one-liner form above silently"
    echo "does nothing. On dash, always use the no-argument form instead."
    echo ""
}

# Dispatch entry point for set/show/clear.
_context_main() {
    case "$1" in
        set)
            shift
            set_context "$@"
            ;;
        show)
            shift
            show_context "$@"
            ;;
        clear)
            shift
            clear_context "$@"
            ;;
        -h|--help|help)
            _context_usage
            ;;
        *)
            print_status $RED "❌ Unknown context command: $1"
            echo ""
            _context_usage
            return 2
            ;;
    esac
}

if [ $# -gt 0 ]; then
    _context_main "$@"
fi

unset -f _context_find_common_sh _context_main 2>/dev/null
