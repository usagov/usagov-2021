# Backup System

A unified backup management system for USA.gov that handles static site backups, public file backups, and database backups through a single, easy-to-use interface.

## Overview

This backup system provides automated and manual backup capabilities for the USA.gov website, storing backups securely in AWS S3. It supports three types of backups:

- **Static Site Backups**: Generated static HTML files from Drupal Tome
- **Public File Backups**: Public media files and assets
- **Database Backups**: Full database exports with compression

## Quick Start

```bash
# From project root (recommended)
scripts/snapshot/manager.sh list    # List all current backups
scripts/snapshot/manager.sh backup  # Create a full backup (all types)
scripts/snapshot/manager.sh info    # Get system information
```

## Commands

### List Backups

```bash
# From project root
scripts/snapshot/manager.sh list                    # Show all backups
scripts/snapshot/manager.sh list static             # Show only static backups
scripts/snapshot/manager.sh list db,public          # Show database and public backups
scripts/snapshot/manager.sh list all 7              # Show all backups from last 7 days
```

### Create Backups

```bash
# Basic backups (from project root)
scripts/snapshot/manager.sh backup                  # Create all backup types (AUTO prefix)
scripts/snapshot/manager.sh backup db               # Database backup only
scripts/snapshot/manager.sh backup static           # Static site backup only
scripts/snapshot/manager.sh backup public           # Public files backup only
scripts/snapshot/manager.sh backup static,db        # Multiple specific types

# Manual backups with custom prefix and suffix
scripts/snapshot/manager.sh backup all USAGOV-123                # Custom prefix (ticket name)
scripts/snapshot/manager.sh backup all USAGOV-123 post-deploy    # Custom prefix and suffix
scripts/snapshot/manager.sh backup db USAGOV-456 pre-update      # Database with custom tags
scripts/snapshot/manager.sh backup static RELEASE-v2.1 hotfix    # Static with release info

# From backup directory
./manager.sh backup                            # Create all backup types (AUTO prefix)
./manager.sh backup all USAGOV-789             # Custom prefix
./manager.sh backup all USAGOV-789 emergency   # Custom prefix and suffix
```

### Clean Old Backups

```bash
# From project root
scripts/snapshot/manager.sh clean                   # Clean all types (30 day retention)
scripts/snapshot/manager.sh clean db 7              # Clean database backups older than 7 days
scripts/snapshot/manager.sh clean static 14         # Clean static backups older than 14 days
```

> **Note:** Clean operations require confirmation before deleting files

### Get Information

```bash
# From project root
scripts/snapshot/manager.sh info                    # Show system configuration and status
scripts/snapshot/manager.sh info db                 # Show database-specific information
scripts/snapshot/manager.sh info static backup-tag  # Show details for a specific backup
```

### Restore Backups

```bash
# From project root
scripts/snapshot/manager.sh restore backup-tag      # Interactive restore process
```

## Backup Tag Format

All backups use a standardized naming convention for traceability and organization:

**Format**: `PREFIX-environment-containertag-timestamp-suffix`

### Components

- **PREFIX**: Identifies the backup source
  - `AUTO`: Automated backups (cron, system triggers)
  - `MANUAL`: Manual backups without custom prefix
  - `USAGOV-123`: Ticket-based prefix for development work
  - `RELEASE-v2.1`: Release-based prefix for deployment tracking

- **environment**: Current deployment environment (`dev`, `staging`, `prod`)

- **containertag**: Container version for deployment traceability
  - Cloud.gov: Extracted from `/etc/motd` (e.g., `cf-abc123`)
  - Development: Git short hash (e.g., `git-def456`)

- **timestamp**: UTC timestamp in `YYYY_MM_DD_HH_MM_SS` format

- **suffix**: Optional descriptive text
  - `post-deploy`: After deployment operations
  - `pre-update`: Before system updates
  - `emergency`: Emergency backup scenarios
  - `hotfix`: Hotfix-related backups

### Examples

```text
AUTO-prod-cf-abc123-2024_03_15_14_30_00                    # Automated production backup
USAGOV-456-dev-git-def789-2024_03_15_15_45_30-pre-update   # Manual backup before update
RELEASE-v2.1-staging-cf-ghi012-2024_03_15_16_20_15-hotfix  # Release hotfix backup
MANUAL-prod-cf-jkl345-2024_03_15_17_10_45-emergency        # Emergency manual backup
```

This format ensures:

- Easy identification of backup source and purpose
- Deployment traceability through container tags
- Chronological sorting by timestamp
- Clear association with tickets and releases

## File Structure

```text
scripts/snapshot/
├── manager.sh              # Main backup management script
├── backup-system.conf      # Configuration file
├── setup-cron.sh           # Cron job management
├── test.sh                 # Test suite
└── README.md               # This file
```

## Configuration

The system is configured through `backup-system.conf`. Key settings include:

- **Backup Storage**: AWS S3 paths for each backup type
- **Retention Policies**: How long to keep backups (default: 30 days)
- **Enable/Disable Flags**: Control which backup types are active
- **Notification Settings**: Email alerts and logging preferences

## Automated Backups

Database backups can be scheduled automatically using cron:

```bash
# From project root
scripts/snapshot/setup-cron.sh setup    # Set up daily database backups at 7 PM EST
scripts/snapshot/setup-cron.sh remove   # Remove scheduled backups
scripts/snapshot/setup-cron.sh status   # Check current cron status
```

## Storage Structure

Backups are stored in S3 with the following structure:

```text
auto-backups/
├── web-backup/        # Static site backups
├── public_backup/     # Public file backups
└── database/          # Database backups
```## Testing

Run the comprehensive test suite to verify system functionality:

```bash
# From project root
scripts/snapshot/test.sh

# From backup directory
./test.sh
```

The test suite covers:

- Configuration validation
- Script permissions and execution
- Backup creation and listing
- Storage connectivity
- Integration with Drupal Tome

## Integration

The backup system integrates with:

- **Drupal Tome**: Automatically triggers backups after static site generation
- **Cloud Foundry**: Optimized for CF deployment environment
- **AWS S3**: Secure, scalable backup storage
- **System Cron**: Automated scheduling capabilities

## Troubleshooting

### Common Issues

- **Permission Errors**: Ensure scripts are executable (`chmod +x manager.sh`)
- **S3 Access**: Verify AWS credentials and bucket permissions
- **Space Issues**: Check available disk space before large backups
- **Network Issues**: Ensure CF environment has S3 connectivity

### Getting Help

1. Run `scripts/snapshot/manager.sh info` to check system status
2. Run `scripts/snapshot/test.sh` to identify specific issues
3. Check Cloud Foundry logs for detailed error messages
4. Review scripts/snapshot/backup-system.conf for configuration issues## Security

- Backups are encrypted in transit to S3
- Database backups are compressed to reduce storage costs
- Temporary files are cleaned up automatically
- Restore operations require manual confirmation
