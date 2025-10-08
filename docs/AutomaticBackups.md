# Tome Automatic Backup System

This document describes the automatic backup functionality that has been integrated into the Tome static site generation process.

## Overview

The backup system automatically creates snapshots of both the static site and public files after every successful Tome sync operation. This provides a safety net for quick recovery in case of issues with the generated content.

## How It Works

### Integration with Tome Sync

The backup functionality is seamlessly integrated into `scripts/tome-sync.sh`. After a successful sync to S3, the system:

1. **Creates two types of backups:**
   - **Static Site Backup**: Complete snapshot of the generated static site (`s3://bucket/web/` → `s3://bucket/web-backup/TAG/`)
   - **Public Files Backup**: Complete snapshot of uploaded media and files (`s3://bucket/cms/public/` → `s3://bucket/public_backup/TAG/`)

2. **Smart backup optimization:**
   - **Public files backups** use change detection to avoid unnecessary duplicates
   - Compares file listings and sizes between current public files and the latest backup
   - Skips backup creation if no changes are detected since the last automatic backup
   - Static site backups are always created (since content changes with each Tome run)

3. **Uses intelligent naming:**
   - Format: `{PREFIX}-{ENVIRONMENT}-{TIMESTAMP}`
   - Example: `AUTO-prod-2024_03_15_14_30_00`
   - Environment is automatically detected (dev, stage, prod, etc.)

4. **Automatic cleanup:**
   - Removes backups older than the configured retention period (default: 7 days)
   - Prevents storage from growing indefinitely
   - Configurable retention period

### Configuration

Backup behavior is controlled by `scripts/auto-backup-system.conf`:

```bash
# Number of days to retain automatic backups (default: 7)
BACKUP_RETENTION_DAYS=7

# Enable/disable automatic backups (true/false)
ENABLE_STATIC_AUTO_BACKUPS=true
ENABLE_PUBLIC_AUTO_BACKUPS=true

# Enable/disable automatic cleanup of old backups (true/false)  
ENABLE_STATIC_AUTO_CLEANUP=true
ENABLE_PUBLIC_AUTO_CLEANUP=true

# Backup naming prefix (default: AUTO)
BACKUP_PREFIX=AUTO

# Additional S3 copy parameters for backups (optional)
BACKUP_S3_EXTRA_PARAMS=""

# Enable/disable smart public files backup (skips backup if files unchanged)
ENABLE_SMART_PUBLIC_BACKUP=true
```

## Backup Management

### Using the Backup Manager Script

The `scripts/tome-backup-manager.sh` script provides utilities for managing backups:

```bash
# List all automatic backups
./scripts/tome-backup-manager.sh list

# List backups older than 14 days  
./scripts/tome-backup-manager.sh list-old 14

# Clean up backups older than 30 days
./scripts/tome-backup-manager.sh clean 30

# Get information about a specific backup
./scripts/tome-backup-manager.sh info AUTO-prod-2024_03_15_14_30_00

# Restore from a specific backup (WARNING: Destructive!)
./scripts/tome-backup-manager.sh restore AUTO-prod-2024_03_15_14_30_00
```

### Manual Backup Operations

You can also use AWS CLI directly:

```bash
# List static site backups
aws s3 ls s3://bucket/web-backup/

# List public files backups  
aws s3 ls s3://bucket/public_backup/

# Download a backup locally
aws s3 sync s3://bucket/web-backup/AUTO-prod-2024_03_15_14_30_00/ ./local-backup/
```

## Safety Features

### Backup Timing
- Backups only occur **after successful sync operations**
- Failed syncs do not trigger backups
- Backup failures don't prevent the main sync process

### Error Handling
- Backup failures are logged but don't stop the Tome process
- Each backup operation is independently verified
- Cleanup operations are protected against date calculation errors

### Logging
- All backup operations are logged in the same Tome log files
- Backup status is clearly indicated in the logs
- S3 upload logs are preserved for troubleshooting

## Smart Backup Behavior

### Public Files Change Detection

The system includes intelligent change detection for public files backups:

- **How it works**: Compares file listings (names and sizes) between current public files and the most recent automatic backup
- **When it skips**: If the checksums match exactly, indicating no files have been added, removed, or modified
- **When it creates**: If no previous backup exists, or if any changes are detected
- **Configurable**: Can be disabled by setting `ENABLE_SMART_PUBLIC_BACKUP=false`

### Why Static Site Backups Are Always Created

Static site backups are created on every successful Tome run because:
- The static site content changes with every Tome generation (timestamps, cache busters, etc.)
- Even identical source content may produce different static files
- Static site generation is the primary purpose of the Tome process

### Backup Frequency Patterns

In typical usage, you might see:
- **Static site backups**: Created with every successful Tome run
- **Public files backups**: Created less frequently, only when uploads/media changes occur
- **Asymmetric backup counts**: More static site backups than public files backups (this is normal)

## Storage Structure

```text
s3://bucket/
├── web/                    # Current static site
├── cms/public/             # Current public files
├── web-backup/             # Static site backups
│   ├── AUTO-dev-2024_03_15_14_30_00/
│   ├── AUTO-dev-2024_03_15_12_15_30/  # More frequent
│   ├── AUTO-dev-2024_03_15_10_00_00/
│   └── ...
├── public_backup/          # Public files backups  
│   ├── AUTO-dev-2024_03_15_14_30_00/  # Less frequent
│   ├── AUTO-dev-2024_03_12_09_15_00/  # (only when files change)
│   └── ...
└── tome-log/              # Logs (including backup logs)
```

## Monitoring and Alerts

### Log Monitoring
Monitor Tome logs for backup-related messages:
- `"Creating automatic backups after successful sync..."`
- `"Static site backup completed successfully."`
- `"Public files backup completed successfully."`
- `"Warning: Static site backup failed."`
- `"Warning: Public files backup failed."`

### Storage Monitoring
- Monitor S3 storage usage in backup folders
- Set up CloudWatch alarms for unexpected storage growth
- Regular audits of backup retention policies

## Troubleshooting

### Common Issues

**Backups not being created:**
- Check that `ENABLE_STATIC_AUTO_BACKUPS=true` and `ENABLE_PUBLIC_AUTO_BACKUPS=true` in `auto-backup-system.conf`
- Verify S3 permissions allow copying between buckets
- Check Tome logs for error messages

**Cleanup not working:**
- Verify `ENABLE_STATIC_AUTO_CLEANUP=true` and `ENABLE_PUBLIC_AUTO_CLEANUP=true` in configuration
- **Date command compatibility:**
- Verify cleanup settings are properly configured
- Check date command compatibility (Linux vs macOS vs minimal containers)
- In environments with limited date commands, cleanup will be disabled automatically
- Ensure AWS CLI has delete permissions
- Ensure AWS CLI has delete permissions

**Storage growing too fast:**
- Reduce `BACKUP_RETENTION_DAYS` in configuration
- Check if cleanup is running properly
- Verify backup naming patterns match cleanup logic

### Manual Recovery Steps

If automatic cleanup fails and storage grows too large:

```bash
# List all backups to identify old ones
aws s3 ls s3://bucket/web-backup/ --recursive

# Manually remove specific backup
aws s3 rm s3://bucket/web-backup/OLD-BACKUP-TAG/ --recursive

# Emergency cleanup of all backups older than date
aws s3 ls s3://bucket/web-backup/ | grep "2024_01_" | awk '{print $2}' | xargs -I {} aws s3 rm s3://bucket/web-backup/{} --recursive
```

## Integration with Existing Backup System

This automatic backup system complements the existing manual snapshot backup system:

- **Manual Snapshots**: Used for planned deployments, testing, disaster recovery
- **Automatic Backups**: Used for rapid recovery from content generation issues
- **Different Storage Locations**: Automatic backups use `/web-backup/` and `/public_backup/`
- **Different Naming**: Manual snapshots use deployment tags, automatic use timestamps

Both systems can operate simultaneously without conflicts.

## Performance Impact

### Backup Performance
- Backup time is proportional to site size
- Uses S3 server-side copy operations (fast)
- No local disk space requirements
- Minimal impact on Tome generation process

### Storage Costs
- Additional S3 storage costs for backups
- Costs scale with retention period and site size
- Consider using S3 storage classes for cost optimization
- Add `--storage-class STANDARD_IA` to `BACKUP_S3_EXTRA_PARAMS` for older backups

## Security Considerations

- Backups inherit the same security settings as source content
- Public backups remain public (static site content)
- Private backups remain private (CMS uploads)
- IAM permissions required for cross-folder copying within bucket
- Backup restoration requires appropriate permissions

## Future Enhancements

Potential improvements to consider:
- Integration with CloudWatch for automated monitoring
- Slack/email notifications on backup failures
- Differential backups to reduce storage costs
- Integration with AWS Backup service
- Cross-region backup replication for disaster recovery