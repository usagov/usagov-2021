# Backup System

A unified backup management system for USA.gov that handles static site backups, public file backups, and database backups through a single, easy-to-use interface.

## Overview

This backup system provides automated and manual backup capabilities for the USA.gov website, storing backups securely in AWS S3. It supports three types of backups:

- **Static Site Backups**: Generated static HTML files from Drupal Tome
- **Public File Backups**: Public media files and assets
- **Database Backups**: Full database exports with compression

## Quick Start

```bash
# Navigate to the backup directory
cd scripts/backup

# List all current backups
./manager.sh list

# Create a full backup (all types)
./manager.sh backup

# Get system information
./manager.sh info
```

## Commands

### List Backups

```bash
./manager.sh list                    # Show all backups
./manager.sh list static             # Show only static backups
./manager.sh list db,public          # Show database and public backups
./manager.sh list all 7              # Show all backups from last 7 days
```

### Create Backups

```bash
./manager.sh backup                  # Create all backup types
./manager.sh backup db               # Database backup only
./manager.sh backup static           # Static site backup only
./manager.sh backup public           # Public files backup only
./manager.sh backup static,db        # Multiple specific types
```

### Clean Old Backups

```bash
./manager.sh clean                   # Clean all types (30 day retention)
./manager.sh clean db 7              # Clean database backups older than 7 days
./manager.sh clean static 14         # Clean static backups older than 14 days
```

> **Note:** Clean operations require confirmation before deleting files

### Get Information

```bash
./manager.sh info                    # Show system configuration and status
./manager.sh info db                 # Show database-specific information
./manager.sh info static backup-tag  # Show details for a specific backup
```

### Restore Backups

```bash
./manager.sh restore backup-tag      # Interactive restore process
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
# Set up daily database backups at 7 PM EST
./setup-cron.sh install

# Remove scheduled backups
./setup-cron.sh remove

# Check current cron status
./setup-cron.sh status
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

1. Run `./manager.sh info` to check system status
2. Run `./test.sh` to identify specific issues
3. Check Cloud Foundry logs for detailed error messages
4. Review backup-system.conf for configuration issues

## Security

- Backups are encrypted in transit to S3
- Database backups are compressed to reduce storage costs
- Temporary files are cleaned up automatically
- Restore operations require manual confirmation
