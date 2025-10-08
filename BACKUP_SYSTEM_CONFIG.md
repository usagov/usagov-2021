# Backup System Configuration Guide

The backup system configuration has been reorganized for better clarity and maintainability.

## Configuration File Location

`scripts/auto-backup-system.conf`

The configuration is now logically organized into sections:

### General Backup Settings
- `BACKUP_RETENTION_DAYS` - Days to retain backups (applies to static/public)
- `BACKUP_PREFIX` - Naming prefix for static/public backups
- `BACKUP_S3_EXTRA_PARAMS` - Additional S3 parameters

### Static Site Backup Settings
- `ENABLE_STATIC_AUTO_BACKUPS` - Enable/disable static site backups
- `ENABLE_STATIC_AUTO_CLEANUP` - Enable/disable static site cleanup

### Public Files Backup Settings
- `ENABLE_PUBLIC_AUTO_BACKUPS` - Enable/disable public files backups
- `ENABLE_PUBLIC_AUTO_CLEANUP` - Enable/disable public files cleanup
- `ENABLE_SMART_PUBLIC_BACKUP` - Enable smart change detection

### Database Backup Settings
- `ENABLE_DB_BACKUPS` - Enable/disable database backups
- `ENABLE_DB_AUTO_CLEANUP` - Enable/disable database cleanup
- `DB_BACKUP_TIME` - Daily backup time (EST)
- `DB_BACKUP_RETENTION_DAYS` - Days to retain database backups
- `DB_BACKUP_PREFIX` - Database backup naming prefix

## Benefits of New Structure

1. **Logical grouping** - Related settings are grouped together
2. **Clear sections** - Easy to find and modify specific backup types
3. **Better comments** - Each section has clear explanations
4. **Granular control** - Independent enable/disable for each backup type
5. **Future-ready** - Easy to add new backup types as separate sections

## Migration Notes

All scripts have been updated to use the new filename. No manual migration is needed.