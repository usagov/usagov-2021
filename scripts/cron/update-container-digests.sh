#!/bin/sh
# Update container digests for all apps in current space
# This runs every 5 minutes via /etc/periodic/5min to capture deployment state
#
# Usage: ./update-container-digests.sh [environment] [target-bucket]
# Environment will be auto-detected if not provided
# Bucket defaults to cron-state-storage, but should be CMS storage in production
#
# To enable cross-app S3 access:
#   cf bind-service cron storage  # Bind CMS storage bucket to cron
#   cf restage cron
# Then set TARGET_BUCKET=cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5 (or pass as arg 2)

set -e

# Only run on first instance to avoid conflicts
if [ "${CF_INSTANCE_INDEX:-}" != "0" ]; then
  exit 0
fi

# Determine environment from VCAP_APPLICATION or argument
if [ -n "$1" ]; then
    ENVIRONMENT="$1"
else
    ENVIRONMENT=$(echo "$VCAP_APPLICATION" | grep -o '"space_name":"[^"]*"' | cut -d'"' -f4)
fi

if [ -z "$ENVIRONMENT" ]; then
    echo "ERROR: Could not determine environment"
    exit 1
fi

# Determine target bucket (allow override via arg or env var)
TARGET_BUCKET="${2:-$TARGET_BUCKET}"

echo "Updating container digests for environment: $ENVIRONMENT"

# Use PROXYROUTE environment variable directly
if [ -z "$PROXYROUTE" ]; then
    echo "ERROR: PROXYROUTE environment variable not set"
    exit 1
fi

# Extract service account credentials from VCAP_SERVICES using jq
SERVICE_ACCOUNT_USERNAME=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cron-service-account") | .credentials.username')
SERVICE_ACCOUNT_PASSWORD=$(echo "$VCAP_SERVICES" | jq -r '.["cloud-gov-service-account"][]? | select(.name == "cron-service-account") | .credentials.password')

if [ -z "$SERVICE_ACCOUNT_USERNAME" ] || [ -z "$SERVICE_ACCOUNT_PASSWORD" ]; then
    echo "ERROR: Could not extract service account credentials"
    exit 1
fi

# Set proxy for CF API calls
export https_proxy="$PROXYROUTE"

# Authenticate with CF
echo "Authenticating with Cloud Foundry..."
cf api https://api.fr.cloud.gov >/dev/null 2>&1
export CF_PASSWORD="$SERVICE_ACCOUNT_PASSWORD" ### pass via env, not cmd line!
cf auth "$SERVICE_ACCOUNT_USERNAME" >/dev/null 2>&1

# Get organization from VCAP_APPLICATION
ORG=$(echo "$VCAP_APPLICATION" | grep -o '"organization_name":"[^"]*"' | cut -d'"' -f4)
if [ -z "$ORG" ]; then
    echo "ERROR: Could not determine organization"
    exit 1
fi

# Target the org and space
cf target -o "$ORG" -s "$ENVIRONMENT" >/dev/null 2>&1

echo "Discovering running apps..."

# Every component of a release must be accounted for. Discovery used to be
# "whatever `cf apps` currently shows as started", which silently omitted an app
# that was mid-restart and, if nothing at all came back, exited 0 and left the
# previous capture in place — so a backup taken minutes later recorded stale
# digests as though they were current.
# Read the release contract from the same config the backup system uses, so
# RELEASE_COMPONENTS has one definition rather than a copy per producer
CONF="$(dirname "$0")/../snapshot/backup-system.conf"
if [ -f "$CONF" ]; then
    . "$CONF"
fi
REQUIRED_APPS="${RELEASE_COMPONENTS:-cms www waf}"

# Any other started app is recorded too, but never counts towards completeness
OTHER_APPS=$(cf apps 2>/dev/null | awk 'NR>1 && $2 == "started" { print $1 }')

CAPTURED_AT=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Built with jq, not string concatenation: the old form embedded literal "\n"
# sequences and was only valid JSON if the running shell's `echo` expanded them.
# Compact on purpose — readers deployed before this change scrape the single-line
# form with sed, and they keep working while new readers parse it as JSON.
CONTAINERS="{}"
MISSING=""

record_digest() {
    app="$1"
    digest=$(cf app "$app" 2>/dev/null | grep "docker image" | awk '{print $NF}')
    if [ -z "$digest" ]; then
        return 1
    fi
    CONTAINERS=$(printf '%s' "$CONTAINERS" | jq -c --arg a "$app" --arg d "$digest" '.[$a] = $d')
    return 0
}

for APP in $REQUIRED_APPS; do
    echo "  Querying $APP (required)..."
    if ! record_digest "$APP"; then
        echo "  WARNING: no digest for required app $APP"
        MISSING="$MISSING $APP"
    fi
done

for APP in $OTHER_APPS; do
    case " $REQUIRED_APPS " in
        *" $APP "*) continue ;;
    esac
    echo "  Querying $APP..."
    record_digest "$APP" || echo "  (no digest for $APP)"
done

COMPLETE=true
if [ -n "$MISSING" ]; then
    COMPLETE=false
fi

MISSING_JSON=$(printf '%s' "$MISSING" | tr ' ' '\n' | grep -v '^$' | jq -R . | jq -sc .)

# `timestamp` is kept alongside `captured_at` for readers that only know the old
# field name. Key order matters to those readers as well: nothing nested may
# appear before `containers`.
TEMP_FILE="/tmp/container-digests-${ENVIRONMENT}.json"
jq -nc \
    --argjson metadata_version 1 \
    --arg captured_at "$CAPTURED_AT" \
    --arg environment "$ENVIRONMENT" \
    --argjson containers "$CONTAINERS" \
    --argjson complete "$COMPLETE" \
    --argjson missing "$MISSING_JSON" \
    '{
        metadata_version: $metadata_version,
        timestamp: $captured_at,
        captured_at: $captured_at,
        environment: $environment,
        containers: $containers,
        complete: $complete,
        missing: $missing
    }' > "$TEMP_FILE"

# An incomplete capture is still written. A fresh capture that admits what it is
# missing is safer than a stale one that looks complete: the backup metadata
# records `complete: false` and the validator refuses to roll back to it.
if [ "$COMPLETE" != true ]; then
    echo "WARNING: capture is incomplete, missing:$MISSING"
fi

echo "Container digests captured:"
cat "$TEMP_FILE"

# Upload to S3 using CMS app's S3 bucket (shared storage)
# Get S3 credentials from VCAP_SERVICES (assuming cron has access to storage service)
echo ""
echo "Uploading to S3..."

# Try to upload using aws CLI if available
if command -v aws >/dev/null 2>&1; then
    # Use target bucket if specified, otherwise default to cron-state-storage
    if [ -n "$TARGET_BUCKET" ]; then
        BUCKET="$TARGET_BUCKET"
        echo "Using target bucket: $BUCKET"

        # Extract credentials for the target bucket from VCAP_SERVICES using jq
        # This requires the bucket to be bound to the cron app
        ACCESS_KEY=$(echo "$VCAP_SERVICES" | jq -r ".s3[]? | select(.credentials.bucket == \"${BUCKET}\") | .credentials.access_key_id")
        SECRET_KEY=$(echo "$VCAP_SERVICES" | jq -r ".s3[]? | select(.credentials.bucket == \"${BUCKET}\") | .credentials.secret_access_key")
        REGION=$(echo "$VCAP_SERVICES" | jq -r ".s3[]? | select(.credentials.bucket == \"${BUCKET}\") | .credentials.region")

        if [ -z "$ACCESS_KEY" ] || [ "$ACCESS_KEY" = "null" ]; then
            echo "ERROR: Could not find credentials for bucket $BUCKET in VCAP_SERVICES"
            echo "You may need to bind the storage service to cron: cf bind-service cron storage"
            exit 1
        fi
    else
        # Default to cron-state-storage
        BUCKET=$(echo "$VCAP_SERVICES" | jq -r '.s3[]? | select(.name == "cron-state-storage") | .credentials.bucket')
        ACCESS_KEY=$(echo "$VCAP_SERVICES" | jq -r '.s3[]? | select(.name == "cron-state-storage") | .credentials.access_key_id')
        SECRET_KEY=$(echo "$VCAP_SERVICES" | jq -r '.s3[]? | select(.name == "cron-state-storage") | .credentials.secret_access_key')
        REGION=$(echo "$VCAP_SERVICES" | jq -r '.s3[]? | select(.name == "cron-state-storage") | .credentials.region')
        echo "Using default cron bucket: $BUCKET"
    fi

    if [ -n "$BUCKET" ]; then
        export AWS_ACCESS_KEY_ID="$ACCESS_KEY"
        export AWS_SECRET_ACCESS_KEY="$SECRET_KEY"
        export AWS_DEFAULT_REGION="$REGION"

        # Unset proxy for S3 upload (S3 endpoints are directly accessible)
        unset https_proxy
        unset http_proxy

        # Upload to S3
        aws s3 cp "$TEMP_FILE" "s3://${BUCKET}/deployment-metadata/.current_digests_${ENVIRONMENT}.json" 2>&1

        if [ $? -eq 0 ]; then
            echo "✅ Successfully uploaded container digests to S3"
        else
            echo "❌ Failed to upload to S3"
            exit 1
        fi
    else
        echo "WARNING: Could not find S3 bucket in VCAP_SERVICES"
        echo "File saved locally at: $TEMP_FILE"
    fi
else
    echo "WARNING: AWS CLI not available"
    echo "File saved locally at: $TEMP_FILE"
fi

# Clean up
rm -f "$TEMP_FILE"

# Exit non-zero on an incomplete capture so the cron log shows it. The capture
# itself was still written and uploaded: consumers need the truth about what was
# running, not the previous capture left in place to be read as current.
if [ "$COMPLETE" != true ]; then
    echo "❌ Container digest update finished with an incomplete capture, missing:$MISSING"
    exit 1
fi

echo "Container digest update complete"
