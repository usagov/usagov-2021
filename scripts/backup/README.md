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
scripts/backup/manager.sh list    # List all current backups
scripts/backup/manager.sh backup  # Create a full backup (all types)
scripts/backup/manager.sh info    # Get system information
```

## Commands

### List Backups

```bash
# From project root
scripts/backup/manager.sh list                    # Show all backups
scripts/backup/manager.sh list static             # Show only static backups
scripts/backup/manager.sh list db,public          # Show database and public backups
scripts/backup/manager.sh list all 7              # Show all backups from last 7 days
```

### Create Backups

```bash
# From project root
scripts/backup/manager.sh backup                  # Create all backup types
scripts/backup/manager.sh backup db               # Database backup only
scripts/backup/manager.sh backup static           # Static site backup only
scripts/backup/manager.sh backup public           # Public files backup only
scripts/backup/manager.sh backup static,db        # Multiple specific types
```

### Clean Old Backups

```bash
# From project root
scripts/backup/manager.sh clean                   # Clean all types (30 day retention)
scripts/backup/manager.sh clean db 7              # Clean database backups older than 7 days
scripts/backup/manager.sh clean static 14         # Clean static backups older than 14 days
```

> **Note:** Clean operations require confirmation before deleting files

### Get Information

```bash
# From project root
scripts/backup/manager.sh info                    # Show system configuration and status
scripts/backup/manager.sh info db                 # Show database-specific information
scripts/backup/manager.sh info static backup-tag  # Show details for a specific backup
```

### Restore Backups

```bash
# From project root
scripts/backup/manager.sh restore backup-tag      # Interactive restore process
```

## File Structure

```text
scripts/backup/
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
scripts/backup/setup-cron.sh setup    # Set up daily database backups at 7 PM EST
scripts/backup/setup-cron.sh remove   # Remove scheduled backups
scripts/backup/setup-cron.sh status   # Check current cron status
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
scripts/backup/test.sh

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

1. Run `scripts/backup/manager.sh info` to check system status
2. Run `scripts/backup/test.sh` to identify specific issues
3. Check Cloud Foundry logs for detailed error messages
4. Review scripts/backup/backup-system.conf for configuration issues## Security

- Backups are encrypted in transit to S3
- Database backups are compressed to reduce storage costs
- Temporary files are cleaned up automatically
- Restore operations require manual confirmation
