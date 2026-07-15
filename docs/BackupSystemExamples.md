# Backup System - Command Examples and Output

This document shows examples of common backup system commands and their expected output.

## Table of Contents
- [Listing Backups](#listing-backups)
- [Creating Backups](#creating-backups)
- [Restoring Backups](#restoring-backups)
- [Cleaning Up Old Backups](#cleaning-up-old-backups)
- [Cron Setup](#cron-setup)
- [Backup Tag Format](#backup-tag-format)

---

## Listing Backups

**Command:**
```bash
scripts/snapshot/manager.sh list
```

**Output:**
```
Backups by Restore Tag

BACKUP TAG                       STATIC   PUBLIC   DATABASE RESTORE COMMAND
-------------------------------- -------- -------- -------- --------------------
AUTO-prod-14850-Oct-07-25        ✅      ❌      ❌      restore AUTO-prod-14850-Oct-07-25
AUTO-prod-14850-Oct-08-25        ✅      ✅      ❌      restore AUTO-prod-14850-Oct-08-25
AUTO-prod-14851-Oct-08-25        ✅      ❌      ❌      restore AUTO-prod-14851-Oct-08-25
AUTO-prod-14852-Oct-08-25        ✅      ✅      ✅      restore AUTO-prod-14852-Oct-08-25
AUTO-prod-14853-Oct-09-25        ✅      ✅      ❌      restore AUTO-prod-14853-Oct-09-25
MANUAL-prod-14854-Oct-09-25      ✅      ✅      ✅      restore MANUAL-prod-14854-Oct-09-25

✅ = Available    ❌ = Missing (smart fallback may apply)
```

**Notes:**
- Backups are listed in chronological order (oldest first, newest last)
- Each row shows which backup components are available for that tag
- Smart fallback will use the most recent available backup if an exact match isn't found

---

## Creating Backups

### Create All Backup Types

**Command:**
```bash
scripts/snapshot/manager.sh backup all
```

**Output:**
```
📦 Creating all backups...
Timestamp: Oct-28-25

📄 Creating static site backup...
✅ Static backup complete: AUTO-prod-14855-Oct-28-25

📁 Creating public files backup...
✅ Public backup complete: AUTO-prod-14855-Oct-28-25

💾 Creating database backup...
2025-10-28 15:30:45: 💾 Database backup: AUTO-prod-14855-Oct-28-25
2025-10-28 15:30:45: 🔄 Dumping database...
2025-10-28 15:30:55: 🗜️ Compressing...
2025-10-28 15:31:02: ☁️ Uploading...
2025-10-28 15:31:03: ✅ Database backup complete
✅ Database backup saved: AUTO-prod-14855-Oct-28-25

🎉 All backups completed successfully!
```

### Create Database Only

**Command:**
```bash
scripts/snapshot/manager.sh backup db
```

**Output:**
```
📦 Creating backup: db
Timestamp: Oct-28-25
💾 Backing up database...
2025-10-28 15:35:12: 💾 Database backup: AUTO-prod-14855-Oct-28-25
2025-10-28 15:35:12: 🔄 Dumping database...
 [success] Cache rebuild complete.
 [success] Database dump saved to /tmp/AUTO-prod-14855-Oct-28-25.sql
2025-10-28 15:35:22: 🗜️ Compressing...
2025-10-28 15:35:29: ☁️ Uploading...
2025-10-28 15:35:29: 📍 Target: s3://bucket-name/auto-backups/database/AUTO-prod-14855-Oct-28-25.sql.gz
2025-10-28 15:35:30: ✅ Database backup complete
✅ Database backup saved: AUTO-prod-14855-Oct-28-25
🎉 Done.
```

### Create Manual Backup with Custom Tag

**Command:**
```bash
scripts/snapshot/manager.sh backup all MANUAL before-upgrade
```

**Output:**
```
📦 Creating all backups...
Timestamp: Oct-28-25

📄 Creating static site backup...
✅ Static backup complete: MANUAL-prod-14855-Oct-28-25-before-upgrade

📁 Creating public files backup...
✅ Public backup complete: MANUAL-prod-14855-Oct-28-25-before-upgrade

💾 Creating database backup...
✅ Database backup saved: MANUAL-prod-14855-Oct-28-25-before-upgrade

🎉 All backups completed successfully!
```

---

## Restoring Backups

### Restore All Components

**Command:**
```bash
scripts/snapshot/manager.sh restore AUTO-prod-14852-Oct-08-25
```

**Output:**
```
🔍 Analyzing backup: AUTO-prod-14852-Oct-08-25

BACKUP ANALYSIS
===============
✅ Static site backup found
✅ Public files backup found
✅ Database backup found

RESTORE PLAN
============
This will restore the following:
  📄 Static Site:   s3://bucket/auto-backups/web-backup/AUTO-prod-14852-Oct-08-25/
  📁 Public Files:  s3://bucket/auto-backups/public_backup/AUTO-prod-14852-Oct-08-25/
  💾 Database:      s3://bucket/auto-backups/database/AUTO-prod-14852-Oct-08-25.sql.gz

⚠️  WARNING: This will OVERWRITE current data!
Continue with restore? (yes/no): yes

EXECUTING RESTORE
=================
📄 Restoring static site...
✅ Static site restored successfully

📁 Restoring public files...
✅ Public files restored successfully

💾 Restoring database...
  Downloading database backup...
  Decompressing...
  Importing to database...
✅ Database restored successfully

🎉 RESTORE COMPLETED SUCCESSFULLY!

All components have been restored from backup: AUTO-prod-14852-Oct-08-25
```

### Restore with Smart Fallback

**Command:**
```bash
scripts/snapshot/manager.sh restore AUTO-prod-14851-Oct-08-25
```

**Output:**
```
🔍 Analyzing backup: AUTO-prod-14851-Oct-08-25

BACKUP ANALYSIS
===============
✅ Static site backup found
✅ Public files backup found
❌ Database backup not found for this tag

SMART FALLBACK SEARCH
=====================
🔍 Searching for most recent database backup...
✅ Found fallback: AUTO-prod-14850-Oct-07-25.sql.gz

RESTORE PLAN
============
This will restore the following:
  📄 Static Site:   s3://bucket/auto-backups/web-backup/AUTO-prod-14851-Oct-08-25/
  📁 Public Files:  s3://bucket/auto-backups/public_backup/AUTO-prod-14851-Oct-08-25/
  💾 Database:      s3://bucket/auto-backups/database/AUTO-prod-14850-Oct-07-25.sql.gz (FALLBACK)

⚠️  WARNING: Database is from a different backup (older)
⚠️  WARNING: This will OVERWRITE current data!
Continue with restore? (yes/no): yes

EXECUTING RESTORE
=================
📄 Restoring static site...
✅ Static site restored successfully

📁 Restoring public files...
✅ Public files restored successfully

💾 Restoring database from fallback...
  Downloading database backup...
  Decompressing...
  Importing to database...
✅ Database restored successfully

🎉 RESTORE COMPLETED SUCCESSFULLY!

All components restored. Note: Database was from fallback backup AUTO-prod-14850-Oct-07-25
```

### Restore Single Component (Database Only)

**Command:**
```bash
scripts/snapshot/manager.sh restore AUTO-prod-14852-Oct-08-25 db
```

**Output:**
```
🔍 Analyzing backup: AUTO-prod-14852-Oct-08-25

BACKUP ANALYSIS
===============
✅ Database backup found

RESTORE PLAN
============
This will restore the following:
  💾 Database:      s3://bucket/auto-backups/database/AUTO-prod-14852-Oct-08-25.sql.gz

⚠️  WARNING: This will OVERWRITE current database!
Continue with restore? (yes/no): yes

EXECUTING RESTORE
=================
💾 Restoring database...
  Downloading database backup...
  Decompressing...
  Importing to database...
✅ Database restored successfully

🎉 RESTORE COMPLETED SUCCESSFULLY!

Database restored from backup: AUTO-prod-14852-Oct-08-25
```

---

## Cleaning Up Old Backups

### Clean Backups Older Than 7 Days

**Command:**
```bash
scripts/snapshot/manager.sh clean all 7
```

**Output:**
```
🧹 Cleaning backups older than 7 days...

📄 Cleaning static site backups...
🗑️ Removing: AUTO-prod-14840-Oct-01-25
🗑️ Removing: AUTO-prod-14841-Oct-02-25
✅ Removed 2 static site backups

📁 Cleaning public files backups...
🗑️ Removing: AUTO-prod-14840-Oct-01-25
✅ Removed 1 public files backup

💾 Cleaning database backups...
🗑️ Removing: AUTO-prod-14840-Oct-01-25.sql.gz
🗑️ Removing: AUTO-prod-14841-Oct-02-25.sql.gz
✅ Removed 2 database backups

🎉 Cleanup completed successfully!
```

### Clean Database Backups Only

**Command:**
```bash
scripts/snapshot/manager.sh clean db 30
```

**Output:**
```
🧹 Cleaning database backups older than 30 days...
🗑️ Removing: AUTO-prod-14750-Sep-08-25.sql.gz
🗑️ Removing: AUTO-prod-14751-Sep-09-25.sql.gz
✅ Removed 2 database backups
```

### Delete ALL Backups (Dangerous!)

**Command:**
```bash
scripts/snapshot/manager.sh clean all all
```

**Output:**
```
⚠️  WARNING: This will DELETE ALL BACKUPS!
⚠️  Type 'DELETE ALL' to confirm (or anything else to cancel): DELETE ALL

🧹 Removing ALL static site backups...
🗑️ Removing: AUTO-prod-14850-Oct-07-25
🗑️ Removing: AUTO-prod-14851-Oct-08-25
[... more deletions ...]
✅ All static site backups removed

🧹 Removing ALL public files backups...
✅ All public files backups removed

🧹 Removing ALL database backups...
✅ All database backups removed

🎉 All backups have been deleted!
```

---

## Cron Setup

### Setup Automatic Database Backups

**Command:**
```bash
scripts/snapshot/setup-cron.sh setup
```

**Output:**
```
Setting up database backup cron job...
⚠️ Time conversion: 19:00 Eastern → 0:00 UTC
📝 Note: This assumes EST (UTC-5). Adjust manually for EDT if needed.
✅ Cron job setup complete
```

### Check Cron Status

**Command:**
```bash
scripts/snapshot/setup-cron.sh status
```

**Output:**
```
Current backup cron jobs:
=========================
00 0 * * * cd /var/www && /var/www/scripts/snapshot/manager.sh backup db >/dev/null 2>&1

Configuration:
  Database backups enabled: true
  Backup time: 19:00 EST
```

### Test Cron Command

**Command:**
```bash
scripts/snapshot/setup-cron.sh test
```

**Output:**
```
Testing cron command execution...
This simulates the exact environment and command that cron will use

Found cron job:
  00 0 * * * cd /var/www && scripts/snapshot/manager.sh backup db >/dev/null 2>&1

Executing cron command in minimal environment...
Command: cd /var/www && scripts/snapshot/manager.sh backup db

📦 Creating backup: db
Timestamp: Oct-28-25
💾 Backing up database...
[... database backup output ...]
✅ Database backup saved: AUTO-prod-14855-Oct-28-25
🎉 Done.

✅ Cron command test successful!
The automated backup should work when cron triggers it.
```

### Remove Cron Job

**Command:**
```bash
scripts/snapshot/setup-cron.sh remove
```

**Output:**
```
Removing backup cron jobs...
✅ Backup cron jobs removed
```

---

## Backup Tag Format

All backup tags follow this format:

```
PREFIX-SPACE-CONTAINERTAG-DATE-SUFFIX
```

### Components:

- **PREFIX**: Backup type
  - `AUTO`: Automatic backups (from cron or Tome sync)
  - `MANUAL`: Manually created backups
  - `TEST`: Test backups (for development/testing)

- **SPACE**: Cloud Foundry space
  - `prod`: Production
  - `stage`: Staging
  - `dev`: Development
  - `dr`: Disaster Recovery

- **CONTAINERTAG**: Cloud Foundry container identifier
  - Example: `14850`, `14851`, `14852`
  - Unique identifier for the deployed container instance

- **DATE**: Timestamp in MMM-DD-YY format
  - Example: `Oct-08-25` (October 8, 2025)
  - Human-readable date format

- **SUFFIX**: (Optional) Custom identifier
  - Example: `before-upgrade`, `manual`, `emergency`
  - Added for manual backups or special cases

### Examples:

```
AUTO-prod-14852-Oct-08-25              # Automatic production backup
MANUAL-stage-14853-Oct-09-25           # Manual staging backup
TEST-dev-14850-Oct-07-25-test-suffix   # Test backup with custom suffix
AUTO-prod-14855-Oct-28-25-emergency    # Emergency manual backup
```

---

## Automatic Backup Triggers

### Daily Database Backup (Cron)
- **Schedule**: 00:00 UTC daily (7:00 PM EST)
- **Command**: `scripts/snapshot/manager.sh backup db`
- **Tag Format**: `AUTO-{space}-{container}-{date}`
- **Retention**: 30 days (configurable)

### Tome Sync Backups
- **Trigger**: After successful static site generation
- **Components**: Static site + Public files (if changed)
- **Tag Format**: `AUTO-{space}-{container}-{date}-{hash}`
- **Retention**: 7 days (configurable)

### Configuration
All automatic backup settings are in `scripts/snapshot/backup-system.conf`:

```bash
# Database backups
ENABLE_DB_BACKUPS=true
DB_BACKUP_TIME="19:00"
DB_BACKUP_RETENTION_DAYS=30

# Static/Public backups
ENABLE_STATIC_AUTO_BACKUPS=true
ENABLE_PUBLIC_AUTO_BACKUPS=true
BACKUP_RETENTION_DAYS=7
```

---

## Quick Reference

```bash
# List all backups
scripts/snapshot/manager.sh list

# Create manual backup
scripts/snapshot/manager.sh backup all MANUAL my-tag

# Restore from backup
scripts/snapshot/manager.sh restore AUTO-prod-14852-Oct-08-25

# Clean old backups
scripts/snapshot/manager.sh clean all 7

# Setup cron job
scripts/snapshot/setup-cron.sh setup

# Test cron job
scripts/snapshot/setup-cron.sh test
```
