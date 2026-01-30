# Container Digest Synchronization

## Overview

The digest synchronization system automatically captures container digests for all running applications and makes them available to backup processes. This enables backup metadata to include complete deployment state without requiring CF CLI access from the CMS container.

## Architecture

```
┌──────────────┐          ┌─────────────┐          ┌──────────────┐
│  Cron App    │          │     S3      │          │   CMS App    │
│              │          │             │          │              │
│ - CF CLI     │──write──>│  .current_  │<──read───│ - Backups    │
│ - Service    │          │  digests_   │          │ - Metadata   │
│   Account    │          │  {env}.json │          │              │
└──────────────┘          └─────────────┘          └──────────────┘
```

## Components

### 1. Digest Update Script (`scripts/cron/update-container-digests.sh`)

**Purpose**: Runs periodically in the cron app to capture current container digests

**Process**:
1. Authenticates to CF API using service account credentials
2. Auto-discovers all running applications in the current space
3. Queries each app's container digest via `cf app <name>`
4. Builds JSON structure with timestamp, environment, and digest map
5. Uploads to S3: `deployment-metadata/.current_digests_{env}.json`

**Requirements**:
- CF CLI installed (`/usr/bin/cf`)
- Service account credentials in `VCAP_SERVICES` (`cloud-gov-service-account`)
- `PROXYROUTE` environment variable for CF API access
- AWS CLI installed
- S3 credentials in `VCAP_SERVICES`

**Configuration**:
```bash
# Default: Uses cron's own S3 bucket (cron-state-storage)
./update-container-digests.sh

# With environment override:
./update-container-digests.sh staging

# With target bucket (requires bucket bound to cron):
./update-container-digests.sh dev cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5

# Via environment variable:
TARGET_BUCKET=cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5 ./update-container-digests.sh
```

### 2. Metadata Capture Enhancement (`scripts/common.sh`)

**Purpose**: Reads container digests when creating backup metadata

**Process** (3-tier fallback):
1. Check environment variables: `$BACKUP_CMS_DIGEST`, `$BACKUP_WWW_DIGEST`, `$BACKUP_WAF_DIGEST`
2. Read from S3 file: `deployment-metadata/.current_digests_{env}.json`
3. Query CF CLI directly (requires CF CLI access)

**Modified Function**: `capture_deployment_metadata()` (lines 305-323)

### 3. Output Format

**File**: `s3://{bucket}/deployment-metadata/.current_digests_{env}.json`

```json
{
  "timestamp": "2026-01-30T20:26:22Z",
  "environment": "dev",
  "containers": {
    "cms": "sha256:ae3468cfab24154b26e72524f1398ee6eb9ef6849d42f229c307fcd6f6a756a6",
    "www": "sha256:dd8e246b1a81e2a9791a53ddc5f9dcd898967741cd70ac8c923a4edd96ba76af",
    "waf": "sha256:4408009d1b64d206bb0417c2c4f6d0569bd9cf268a9e055cf13794b540adab6a",
    "cron": "sha256:323f1736808bea9706c1a705f17df25cb46c9b7b85fb92a07c93f8298f95b175",
    "api-proxy": "sha256:...",
    "AnalyticsReporter": "sha256:...",
    "log-shipper-dev": "sha256:..."
  }
}
```

## Deployment

### Container Build

The digest update script is automatically included in the cron container build:

**Files**:
- Source script: `scripts/cron/update-container-digests.sh`
- Copied to container: `/etc/periodic/5min/update-container-digests.sh`

**Schedule**: Runs every 5 minutes (on instance 0 only)

The Dockerfile includes:
```dockerfile
COPY scripts/cron/update-container-digests.sh /etc/periodic/5min/update-container-digests.sh
```

The script checks `CF_INSTANCE_INDEX` to ensure only instance 0 executes it.

### Current Setup (Separate Buckets)

**Status**: ✅ Functional but limited

- Cron writes to: `cg-75b68067-b2c3-48bc-b92d-16487f5d8938` (cron-state-storage)
- CMS reads from: `cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5` (storage)
- **Limitation**: Buckets are separate, so CMS can't read cron's digest file

**Use case**: Testing and development

### Production Setup (Shared Bucket)

**Status**: ⏳ Requires service binding

**Steps to enable**:

1. **Bind CMS storage bucket to cron app**:
```bash
cf bind-service cron storage
cf restage cron
```

2. **Verify binding**:
```bash
cf env cron | grep -A 20 '"storage"'
```

You should see the CMS bucket in VCAP_SERVICES:
```json
{
  "name": "storage",
  "credentials": {
    "bucket": "cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5",
    ...
  }
}
```

3. **Update cron job to use target bucket**:

**Option A**: Environment variable (recommended)
```bash
cf set-env cron TARGET_BUCKET cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5
cf restage cron
```

**Option B**: Pass as argument to script (requires custom deployment)
```bash
# Modify the periodic job wrapper to pass bucket argument
/opt/cron/update-container-digests.sh dev cg-33ba2c88-f377-4249-8b26-0a9d24caf3f5
```

4. **Verify automatic execution**:

The digest update script is **automatically deployed and scheduled** as part of the cron container build. It runs every 5 minutes via the periodic job system on instance 0.

**Check execution**:
```bash
# Check if the script exists
cf ssh cron -c "ls -la /etc/periodic/5min/update-container-digests.sh"

# Check CF logs for output
cf logs cron --recent | grep digest
```

5. **Test backup metadata** includes all containers after the digest file is updated

### 1. Test Digest Capture

```bash
# SSH to cron container
cf ssh cron

# Run the script manually
/path/to/update-container-digests.sh

# Verify output shows all apps and digests
```

Expected output:
```
Updating container digests for environment: dev
Authenticated to CF API
Discovering running apps in space: dev
Found 7 apps
Capturing digests:
  AnalyticsReporter: sha256:731ecb...
  cms: sha256:ae3468...
  cron: sha256:323f17...
  waf: sha256:440800...
  www: sha256:dd8e24...
Building JSON file...
Uploading to S3...
SUCCESS: Uploaded to s3://.../deployment-metadata/.current_digests_dev.json
```

### 2. Test S3 Upload

```bash
# SSH to cron and check S3
cf ssh cron

# Source the profile to get aws functions
source /root/.profile

# List deployment metadata
aws_ls s3://{bucket}/deployment-metadata/

# Download and inspect the digest file
aws_cp s3://{bucket}/deployment-metadata/.current_digests_dev.json - | jq .
```

### 3. Test CMS Read

```bash
# SSH to CMS
cf ssh cms

# Source common functions
cd /var/www/web
. scripts/common.sh

# Set up S3 vars
setup_s3_vars

# Try to read the digest file
aws s3 cp s3://$S3_BUCKET/deployment-metadata/.current_digests_dev.json - | jq .
```

If this works, the metadata capture during backups will automatically include all container digests.

### 4. Test Backup Metadata

```bash
# Trigger a backup
./scripts/devops/deploy.sh backup test-ticket db test

# Check the backup metadata
cf ssh cms -c "cd /var/www && . scripts/common.sh && init_backup_system && setup_s3_vars && aws s3 cp s3://\$BUCKET_NAME/deployment-metadata/test-ticket-dev-{container}-{date}--test-0.json - | jq .containers"
```

Expected: Metadata should contain cms, www, waf, and other app digests.

## Troubleshooting

### Error: "PROXYROUTE environment variable not set"

**Cause**: PROXYROUTE is not available in the current environment

**Solution**: Check that PROXYROUTE is set:
```bash
cf env cron | grep PROXYROUTE
```

### Error: "Failed to connect to proxy URL"

**Cause**: AWS CLI is trying to use the proxy for S3 connections

**Solution**: The script already unsets proxy vars before AWS calls. If this persists, check that the unset commands are executed:
```bash
# In script:
unset https_proxy http_proxy
aws s3 cp ...
```

### Error: "Could not find credentials for bucket in VCAP_SERVICES"

**Cause**: Target bucket is not bound to the cron app

**Solution**: 
```bash
# Bind the CMS storage service to cron
cf bind-service cron storage
cf restage cron
```

### Backup metadata missing www/waf digests

**Possible causes**:
1. Digest file doesn't exist in S3 → Run update-container-digests.sh
2. Digest file is in wrong bucket → Verify CMS can access the bucket
3. JSON parsing failed → Check file format with `aws s3 cp ... - | jq .`
4. Apps are not running → Check `cf apps` shows apps as "started"

### Apps not discovered

**Cause**: `cf apps | grep "started"` not returning expected apps

**Debug**:
```bash
cf ssh cron
export https_proxy=$PROXYROUTE
cf auth {service-account}
cf target -o gsa-tts-usagov -s dev
cf apps
```

Expected: All running apps should show with "started" status.

## Benefits

1. **Automatic Discovery**: New apps are automatically included without configuration changes
2. **No CMS Modifications**: CMS container doesn't need CF CLI or additional credentials
3. **Consistent State**: All backups capture the complete deployment state
4. **Simplified Rollback**: Backup metadata contains all information needed for rollback
5. **Extensible**: Easily supports additional apps/containers as system grows

## Future Enhancements

1. **Simplified Rollback**: Use backup tags to restore exact deployment state:
   ```bash
   # Zero-argument rollback using most recent backup
   ./deploy.sh rollback

   # Rollback to specific backup tag
   ./deploy.sh rollback USAGOV-1234-dev-cms-2026-01-30--db-0
   ```

2. **Digest History**: Track digest changes over time for debugging deployments

3. **Failure Alerting**: Notify if digest capture fails or becomes stale

4. **Multi-Environment**: Support for staging/production with environment-specific configs
