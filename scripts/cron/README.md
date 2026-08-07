# Cron Scripts

This directory contains scripts that are deployed to the cron container for periodic execution.

## Scripts

### update-container-digests.sh

**Purpose**: Automatically captures container digests for all running applications in the current Cloud Foundry space and uploads them to S3.

**Schedule**: Runs every 5 minutes via Alpine's periodic cron system

**Deployment**: 
- Copied to `/etc/periodic/5min/update-container-digests.sh` in the cron container
- Runs automatically on instance 0 only
- See `.docker/Dockerfile-cron` for build configuration

**Configuration**:
- Default: Uses cron's own S3 bucket (`cron-state-storage`)
- Production: Set `TARGET_BUCKET` env var to CMS bucket after binding storage service

**Output**: 
- S3 file: `deployment-metadata/.current_digests_{env}.json`
- Output visible in `cf logs cron`

**Requirements**:
- CF CLI (installed in container)
- Service account credentials (from VCAP_SERVICES)
- PROXYROUTE environment variable
- AWS CLI (installed in container)
- S3 credentials (from VCAP_SERVICES)

**Documentation**: See `docs/ContainerDigestSync.md` for complete setup guide

### Other Scripts

- `rebuild.sh` - Rebuild scripts for cron infrastructure
- `create-cron-buckets.sh` - Create required S3 buckets
- `delete-cron-buckets.sh` - Clean up S3 buckets
- `task-lock-*` - Task locking utilities for singleton job execution

## Deployment

Scripts in this directory are deployed to the cron container during the Docker build process. See `.docker/Dockerfile-cron` for the build configuration.

To deploy changes:
```bash
# Rebuild the cron container
bin/cloudgov/container-build-cron

# Push the new container
bin/cloudgov/container-push-cron

# Deploy to Cloud Foundry
bin/cloudgov/deploy-cron <space> <tag>
```

## Testing

To test scripts manually in the cron container:
```bash
# SSH to cron container
cf ssh cron

# Run a script manually
/opt/cron/update-container-digests.sh

# Check logs
tail -f /tmp/digest-update.log
```
