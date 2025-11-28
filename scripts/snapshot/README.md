# USAGov Backup System

A complete backup management system for USA.gov that handles static site backups, public file backups, and database backups through a single, easy-to-use interface.

## Overview

This backup system provides automated and manual backup capabilities for the USA.gov website, storing backups securely in AWS S3. It supports three types of backups:

- **Static Site Backups**: Generated static HTML files from Drupal Tome
- **Public File Backups**: Public media files and assets
- **Database Backups**: Full database exports with compression

## Quick Start

### On Cloud Foundry

```bash
# SSH into Cloud Foundry first
cf ssh cms

# From /var/www directory
scripts/snapshot/manager.sh list                        # List all current backups
scripts/snapshot/manager.sh backup                      # Create a full backup (all types)
scripts/snapshot/manager.sh info                        # Get system information
```

### From Local Machine

```bash
# From project root - control CF backups remotely
scripts/snapshot/local-manager.sh list                              # List all backups
scripts/snapshot/local-manager.sh backup all USAGOV-123 pre-deploy  # Create backup on CF
scripts/snapshot/local-manager.sh download AUTO-prod-14850-2025-10-28 all ./backups/  # Download to local
scripts/snapshot/local-manager.sh cron status                       # Check automated backup schedule
scripts/snapshot/local-manager.sh test                              # Run test suite on CF
scripts/snapshot/local-manager.sh info                              # Get system info from CF
```

## Commands

All commands can be run either on Cloud Foundry (via `manager.sh`) or from your local machine (via `local-manager.sh`).

### List Backups

```bash
# On Cloud Foundry
scripts/snapshot/manager.sh list                    # Show all backups
scripts/snapshot/manager.sh list static             # Show only static backups
scripts/snapshot/manager.sh list db,public          # Show database and public backups
scripts/snapshot/manager.sh list all 7              # Show all backups from last 7 days

# From local machine
scripts/snapshot/local-manager.sh list              # Show all backups on CF
scripts/snapshot/local-manager.sh list db 7         # Show database backups from last 7 days
```

### Create Backups

```bash
# On Cloud Foundry - Basic backups
scripts/snapshot/manager.sh backup                  # Create all backup types (AUTO prefix)
scripts/snapshot/manager.sh backup db               # Database backup only
scripts/snapshot/manager.sh backup static           # Static site backup only
scripts/snapshot/manager.sh backup public           # Public files backup only
scripts/snapshot/manager.sh backup static,db        # Multiple specific types

# On Cloud Foundry - Manual backups with custom prefix and suffix
scripts/snapshot/manager.sh backup all USAGOV-123                # Custom prefix (ticket name)
scripts/snapshot/manager.sh backup all USAGOV-123 post-deploy    # Custom prefix and suffix
scripts/snapshot/manager.sh backup db USAGOV-456 pre-update      # Database with custom tags

# Skip Drupal state management (for automation or when Drupal state is already managed)
scripts/snapshot/manager.sh backup db AUTO "" --skip-state-management
scripts/snapshot/manager.sh backup db AUTO "" --ssm  # Shorthand

# From local machine - same commands, executed remotely
scripts/snapshot/local-manager.sh backup all USAGOV-123 pre-deploy    # Create backup on CF
scripts/snapshot/local-manager.sh backup db                           # Database backup only on CF
```

**Drupal State Management:**

By default, database backups automatically manage Drupal state to ensure consistency:

1. **Waits for Tome to stop** - Monitors for up to 25 minutes for tome-run.sh to finish
2. **Disables Tome** - Sets `usagov.tome_run_disabled` to prevent new builds during backup
3. **Enables maintenance mode** - Takes site offline for users during backup
4. **Takes backup** - Creates the database dump
5. **Restores state** - Disables maintenance mode first, then re-enables Tome

Use `--skip-state-management` (or `--ssm` for short) to bypass this (e.g., when called from cron where state is already managed).

### Clean Old Backups

```bash
# On Cloud Foundry
scripts/snapshot/manager.sh clean                   # Clean all types (30 day retention)
scripts/snapshot/manager.sh clean db 7              # Clean database backups older than 7 days
scripts/snapshot/manager.sh clean static 14         # Clean static backups older than 14 days
scripts/snapshot/manager.sh clean all 0             # ⚠️  DELETE ALL backups (requires confirmation)

# Non-interactive mode (for automation, skips confirmation)
scripts/snapshot/manager.sh clean static,public 7 --non-interactive
scripts/snapshot/manager.sh clean db 30 -y          # Short form

# From local machine
scripts/snapshot/local-manager.sh clean db 30       # Clean database backups on CF
scripts/snapshot/local-manager.sh clean all 7 -y    # Non-interactive cleanup on CF
```

> **Note:** Clean operations require confirmation before deleting files (unless using `--non-interactive` or `-y` flag)

### Get Information

```bash
# On Cloud Foundry
scripts/snapshot/manager.sh info                    # Show system configuration and status
scripts/snapshot/manager.sh info db                 # Show database-specific information
scripts/snapshot/manager.sh info all AUTO-prod-14850-2025-10-28  # Show details for specific backup

# From local machine
scripts/snapshot/local-manager.sh info                          # Show system configuration from CF
scripts/snapshot/local-manager.sh info db AUTO-prod-14850-2025-10-28  # Show specific backup details
```

### Restore Backups

```bash
# On Cloud Foundry
scripts/snapshot/manager.sh restore AUTO-prod-14850-2025-10-28              # Interactive restore
scripts/snapshot/manager.sh restore AUTO-prod-14850-2025-10-28 --only=db    # Restore database only
scripts/snapshot/manager.sh restore AUTO-prod-14850-2025-10-28 --only=db --ssm  # Skip state management

# From local machine (restores on CF, not locally)
scripts/snapshot/local-manager.sh restore AUTO-prod-14850-2025-10-28        # Restore on CF (interactive)
scripts/snapshot/local-manager.sh restore AUTO-prod-14850-2025-10-28 --only=static,db  # Selective restore
```

**Note:** Database restores automatically manage Drupal state (wait for tome, disable it, enable maintenance mode) before importing, then restore state after completion. Use `--skip-state-management` (or `--ssm`) to bypass this.

### Download Backups

Download backups from Cloud Foundry to your local machine or to the CF container filesystem.

#### From Local Machine (Recommended)

```bash
# Download to local machine (streams through SSH - no CF disk usage)
scripts/snapshot/local-manager.sh download AUTO-prod-14850-2025-10-28              # All types to current dir
scripts/snapshot/local-manager.sh download AUTO-prod-14850-2025-10-28 db           # Database only
scripts/snapshot/local-manager.sh download AUTO-prod-14850-2025-10-28 all ./backups/  # All to ./backups/
scripts/snapshot/local-manager.sh download AUTO-prod-14850-2025-10-28 db,static ./backups/  # Multiple types
```

#### Direct on Cloud Foundry

```bash
# Download to CF container (uses container disk)
scripts/snapshot/manager.sh download AUTO-prod-14850-2025-10-28                    # All types to current dir
scripts/snapshot/manager.sh download AUTO-prod-14850-2025-10-28 db                 # Database only
scripts/snapshot/manager.sh download AUTO-prod-14850-2025-10-28 db,static          # Multiple types
scripts/snapshot/manager.sh download AUTO-prod-14850-2025-10-28 all ./backups/     # All types to ./backups/

# Stream mode (pipe to stdout)
scripts/snapshot/manager.sh download AUTO-prod-14850-2025-10-28 db - --stream > backup.sql.gz
```

**Download Features:**

- **Local Manager**: Streams data through SSH - no temporary storage on CF container
- **Manager (on CF)**: Downloads directly to container filesystem
- **Stream Mode**: Outputs to stdout for piping (CF only)
- **Comma-separated types**: Download multiple types in one command (any order)
- **Progress indicators**: Shows download status and file sizes
- **Automatic cleanup**: Failed downloads are automatically removed

**Downloaded File Formats:**

- **Database backups**: `.sql.gz` files (compressed SQL dumps)
- **Static backups**: `.tar.gz` archives (directory structure preserved)
- **Public backups**: `.tar.gz` archives (directory structure preserved)

## Backup Tag Format

All backups use a standardized naming convention for traceability and organization:

**Format**: `PREFIX-space-containertag-timestamp-suffix`

### Components

- **PREFIX**: Identifies the backup source, entirely arbitrary, some examples uses:
  - `AUTO`: Automated backups (cron, system triggers)
  - `MANUAL`: Manual backups without custom prefix
  - `USAGOV-123`: Ticket-based prefix for deployments

- **space**: Cloud Foundry space name (`dev`, `staging`, `prod`) or `local` for development

- **containertag**: Container version for deployment traceability
  - Cloud.gov: Numeric container tag from `/etc/motd` (e.g., `14850`)
  - Development: Git short hash (e.g., `git-a1b2c3`)

- **timestamp**: Date in `YYYY-MM-DD` format (e.g., `2025-11-24`)

- **suffix**: Optional descriptive text, entirely arbitrary, some example uses:
  - `-pre-deploy`: Before deployment operations
  - `-post-deploy`: After deployment operations
  - `-hotfix`: Hotfix-related backups

### Examples

```text
AUTO-prod-14850-2025-10-28                        # Automated production backup
USAGOV-456-dev-git-a1b2c3-2025-10-28-pre-update   # Manual backup before update
RELEASE-v2.1-staging-14851-2025-10-28-hotfix      # Release hotfix backup
MANUAL-prod-14852-2025-10-28-emergency            # Emergency manual backup
```

This format ensures:

- Easy identification of backup source and purpose
- Deployment traceability through container tags
- Human-readable date format for quick identification
- Chronological sorting by date
- Clear association with tickets and releases

## File Structure

```text
scripts/snapshot/
├── manager.sh                  # Main backup management script (runs on CF)
├── local-manager.sh            # Local wrapper for remote CF control
├── common.sh                   # Shared utilities and functions
├── backup-system.conf          # Configuration file
├── setup-cron.sh               # Cron job management
├── test.sh                     # Test suite
└── README.md                   # This file
```

### Script Purposes

- **manager.sh**: Core backup operations - runs on Cloud Foundry containers
- **local-manager.sh**: Remote control wrapper - runs on local machine, executes all commands via `cf ssh`
- **common.sh**: Shared functions, configuration loading, and utilities
- **setup-cron.sh**: Automated backup scheduling - runs on CF, controlled via local-manager.sh or directly
- **test.sh**: Comprehensive test suite for validation

## Configuration

The system is configured through `backup-system.conf`. Key settings include:

- **Backup Storage**: AWS S3 paths for each backup type
- **Retention Policies**: How long to keep backups (default: 30 days)
- **Enable/Disable Flags**: Control which backup types are active
- **Notification Settings**: Email alerts and logging preferences

## Automated Backups

Database backups can be scheduled automatically using cron:

```bash
# From local machine (recommended)
scripts/snapshot/local-manager.sh cron setup    # Set up daily database backups (uses DB_BACKUP_TIME env var)
scripts/snapshot/local-manager.sh cron remove   # Remove scheduled backups
scripts/snapshot/local-manager.sh cron status   # Check current cron status
scripts/snapshot/local-manager.sh cron test     # Test the cron backup command

# On Cloud Foundry (direct)
scripts/snapshot/setup-cron.sh setup    # Set up daily database backups
scripts/snapshot/setup-cron.sh remove   # Remove scheduled backups
scripts/snapshot/setup-cron.sh status   # Check current cron status
scripts/snapshot/setup-cron.sh test     # Test the cron backup command
```

**Automated Features:**

- Runs daily at configured time (set via DB_BACKUP_TIME environment variable in EST, default: 19:00)
- Automatically converts EST to UTC for cron scheduling
- Creates database backups with AUTO prefix
- Automatically cleans old backups based on retention policy
- Logs all operations for monitoring
- Independent of static site generation backups

## Storage Structure

Backups are stored in S3 with the following structure:

```text
auto-backups/
├── web-backup/        # Static site backups
│   └── [backup-tag]/  # Each backup in its own directory
├── public_backup/     # Public file backups
│   └── [backup-tag]/  # Each backup in its own directory
└── database/          # Database backups
    └── [backup-tag]-database.sql.gz  # Compressed SQL dumps
```

**Download Storage:**

- **Database backups**: Single `.sql.gz` file per backup
- **Static backups**: Directory structure archived as `.tar.gz` on download
- **Public backups**: Directory structure archived as `.tar.gz` on download

**Storage Locations:**

- **S3**: Primary storage for all backups (persistent, scalable)
- **Local Machine**: Downloads via `local-manager.sh` save to local filesystem
- **CF Container**: Direct downloads via `manager.sh` use temporary container storage
  - Automatically cleaned up after operations
  - Stream mode never uses container disk## Testing

Run the comprehensive test suite to verify system functionality:

```bash
# From local machine (recommended)
scripts/snapshot/local-manager.sh test

# On Cloud Foundry (direct)
cd /var/www
scripts/snapshot/test.sh

# From snapshot directory on CF
cd scripts/snapshot
./test.sh
```

The test suite covers:

- Configuration validation
- Script permissions and execution
- Backup creation and listing
- Storage connectivity
- Integration with Drupal Tome
- Command argument parsing

## Integration

The backup system integrates with:

- **Drupal Tome**: Automatically triggers backups after static site generation
- **Cloud Foundry**: Optimized for CF deployment environment
- **AWS S3**: Secure, scalable backup storage
- **System Cron**: Automated scheduling capabilities

## Troubleshooting

### Common Issues

#### Permission Errors

- Ensure scripts are executable: `chmod +x scripts/snapshot/*.sh`
- Check file ownership in Cloud Foundry environment

#### S3 Access Issues

- Verify AWS credentials in Cloud Foundry environment
- Check bucket permissions and service bindings
- Ensure VCAP_SERVICES is properly configured

#### Space Issues

- Check available disk space before large backups
- Use `local-manager.sh download` to avoid CF disk usage
- Stream mode for direct piping without disk storage

#### Network Issues

- Ensure CF environment has S3 connectivity
- Check security group rules
- Verify DNS resolution for S3 endpoints

#### Local Manager Issues

- Ensure CF CLI is installed: `cf --version`
- Login to Cloud Foundry: `bin/cloudgov/login --sso`
- Check SSH access: `cf ssh cms`
- Verify you're not running on CF itself (script will prevent this)

#### Cron Issues

- Check DB_BACKUP_TIME environment variable is set (format: HH:MM in EST)
- Verify cron status: `scripts/snapshot/local-manager.sh cron status`
- Test cron command: `scripts/snapshot/local-manager.sh cron test`
- Check CF instance index (cron only runs on instance 0)

### Getting Help

1. **Check system status**:

   ```bash
   scripts/snapshot/manager.sh info              # On CF
   scripts/snapshot/local-manager.sh info        # From local
   ```

2. **Run test suite**:

   ```bash
   scripts/snapshot/local-manager.sh test        # From local
   scripts/snapshot/test.sh                      # On CF
   ```

3. **Check Cloud Foundry logs**:

   ```bash
   cf logs cms --recent                          # From local
   ```

4. **Review configuration**:

   ```bash
   cat scripts/snapshot/backup-system.conf       # Check settings
   ```

## Security

- Backups are encrypted in transit to S3
- Database backups are compressed to reduce storage costs
- Temporary files are cleaned up automatically
- Restore operations require manual confirmation
- Local manager prevents execution on CF containers
- SSH streaming for secure data transfer
