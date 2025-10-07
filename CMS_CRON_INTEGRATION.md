# CMS Container Cron Integration - Technical Summary

## Question
> "There is already a cron present in the cms container. Does this integrate with it?"

## Answer: Yes, it fully integrates with the existing CMS container cron system.

## How the Integration Works

### Existing CMS Container Cron System

The CMS container already has a complete cron infrastructure:

1. **Alpine cron daemon** running via s6-overlay supervision
2. **Cron entry**: `/etc/crontabs/root` contains `* * * * * run-parts /etc/periodic/1min`
3. **Existing job**: `/etc/periodic/1min/generate-static-site` runs Tome static site generation
4. **Instance constraint**: Only `CF_INSTANCE_INDEX=0` executes cron jobs
5. **Working directory**: All jobs run from `/var/www`

### Database Backup Integration

The updated `setup-db-backup-cron.sh` script automatically detects the CMS container environment and integrates seamlessly:

#### Detection Logic
```bash
# Detects CMS container by checking for existing static site cron job
if [ -f "/etc/periodic/1min/generate-static-site" ]; then
    # CMS container integration
else
    # Standard cron setup for other environments  
fi
```

#### Integration Implementation
Instead of creating a separate crontab entry, it creates:

**File**: `/etc/periodic/1min/database-backup`
```bash
#!/bin/sh
# Integrates with existing CMS container cron system
# Checks time every minute, only runs backup at configured time

# Respects CF instance constraint
if [ "${CF_INSTANCE_INDEX:-''}" != "0" ]; then
    exit 0
fi

# Time-based execution (default: 7pm EST)
CURRENT_HOUR=$(date +%H)
CURRENT_MINUTE=$(date +%M)

if [ "$CURRENT_HOUR" -eq "19" ] && [ "$CURRENT_MINUTE" -eq "00" ]; then
    cd /var/www
    /var/www/scripts/db-backup-daily.sh >> /tmp/tome-log/db-backup-cron.log 2>&1
fi
```

## Benefits of This Integration

### 1. **No Duplicate Cron Systems**
- Uses the existing Alpine cron daemon
- No additional crontab entries needed
- Consistent with existing infrastructure

### 2. **Proper Instance Handling**
- Respects `CF_INSTANCE_INDEX=0` constraint
- Only the primary instance runs database backups
- Matches existing static site cron behavior

### 3. **Unified Management**
- Database backups run in the same container as static site generation
- Same working directory (`/var/www`)
- Same logging destination (`/tmp/tome-log/`)
- Same execution environment and credentials

### 4. **Consistent Monitoring**
- Health checks already monitor the cron service
- Same supervision system (s6-overlay)
- Logs accessible through same mechanisms

## Current Cron Jobs in CMS Container

After integration, the CMS container runs:

1. **Every minute**: `/etc/periodic/1min/generate-static-site`
   - Runs Tome static site generation via `tome-run.sh`
   - Triggers static site & public file backups (via `tome-sync.sh`)

2. **Daily at 7pm EST**: `/etc/periodic/1min/database-backup`
   - Checks time every minute
   - Executes database backup only at configured time
   - Independent of Tome runs

## Separation of Concerns

| Backup Type | Trigger | Frequency | Integration Point |
|-------------|---------|-----------|------------------|
| Static Site | Tome runs | As needed | `tome-sync.sh` |
| Public Files | Tome runs | When changed | `tome-sync.sh` |
| Database | Time-based | Daily 7pm EST | `/etc/periodic/1min/database-backup` |

## Verification Commands

### Check Cron Integration Status
```bash
# In CMS container:
ls -la /etc/periodic/1min/
# Should show: generate-static-site, database-backup

# Check cron service status
s6-svstat /var/run/s6/services/cron

# View cron logs
tail -f /tmp/tome-log/db-backup-cron.log
```

### Monitor Both Backup Types
```bash
# Static site backups (triggered by Tome)
./scripts/tome-backup-manager.sh list

# Database backups (triggered by cron)
./scripts/tome-backup-manager.sh list-db
```

## Deployment Impact

**Zero additional infrastructure needed:**
- No new containers
- No new cron daemons
- No new supervision services
- No changes to existing health checks

**Automatic setup in CMS container:**
```bash
# Run once in CMS container
./scripts/setup-db-backup-cron.sh
# Creates /etc/periodic/1min/database-backup
# Integrates with existing cron system
```

## Timeline Coordination

The integration ensures proper coordination between different backup types:

- **Static site generation**: Runs when content changes (via existing cron → tome-run.sh → tome-sync.sh)
- **Database backups**: Run independently at 7pm EST daily
- **No conflicts**: Database backups don't interfere with Tome operations
- **Unified logging**: All backup operations log to `/tmp/tome-log/`

## Summary

✅ **Full Integration**: Database backups integrate completely with the existing CMS container cron system

✅ **No Additional Overhead**: Uses existing Alpine cron daemon and s6-overlay supervision

✅ **Consistent Behavior**: Follows same patterns as existing static site cron job

✅ **Proper Constraints**: Respects CF_INSTANCE_INDEX=0 limitation  

✅ **Unified Management**: Single backup manager interface for all backup types

The database backup system doesn't create a separate cron infrastructure—it extends the existing one that already handles Tome static site generation. This approach ensures consistency, reduces complexity, and maintains the same operational patterns already established in the CMS container.