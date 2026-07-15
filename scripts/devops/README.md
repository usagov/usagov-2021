# DevOps Scripts

Local execution scripts for deployment and backup management. These scripts run on your local machine (or in CI/CD) and interact with Cloud Foundry environments remotely.

## Execution Context

**Important**: Scripts in this folder execute on your **local machine**. They interact with Cloud Foundry containers via `cf` CLI commands. This is in contrast to scripts in `../snapshot/` which execute inside the CF container via `cf ssh`.

## Scripts

### deploy.sh

Deployment orchestration and workflow automation.

**Purpose**: Simplifies complex deployment workflows by providing high-level commands that combine multiple operations.

**Key Features**:

- Set deployment context (environment, ticket, backup tags)
- Create pre/post deployment backups automatically
- Rollback to previous state (static, public, db)
- Show deployment status and recent activity
- Compare changes between branches/commits

**Common Usage**:

```bash
# Set deployment context
./scripts/devops/deploy.sh set-context prod USAGOV-1234

# Show current context
./scripts/devops/deploy.sh show-context

# Check last backup times
./scripts/devops/deploy.sh last-backup

# Create pre-deployment backup
./scripts/devops/deploy.sh pre-deploy

# Create post-deployment backup
./scripts/devops/deploy.sh post-deploy

# Rollback to pre-deployment state
./scripts/devops/deploy.sh rollback
```

**Environment Variables**:

- `DEPLOY_ENV` - Target environment (prod, stage, etc.)
- `DEPLOY_TICKET` - Ticket number for tracking
- `DEPLOY_PRE_SUFFIX` - Pre-deployment backup suffix
- `DEPLOY_POST_SUFFIX` - Post-deployment backup suffix
- `DEPLOY_ROLLBACK_*_TAG` - Backup tags for rollback

### local-manager.sh

Local wrapper for backup operations on Cloud Foundry.

**Purpose**: Control remote backup operations from your local machine without SSH'ing into the CF container.

**Key Features**:

- List backups stored in S3
- Create backups (static site, public files, database)
- Restore backups
- Clean old backups
- Download backups to local machine

**Common Usage**:

```bash
# List all backups from last 30 days
./scripts/devops/local-manager.sh list

# List only database backups from last 7 days
./scripts/devops/local-manager.sh list db 7

# Create backup with custom tag
./scripts/devops/local-manager.sh backup all PRE USAGOV-1234

# Restore a specific backup
./scripts/devops/local-manager.sh restore PRE-USAGOV-1234-2024-12-22-143022

# Clean old backups (older than 30 days)
./scripts/devops/local-manager.sh clean all 30 -y

# Download backup to local machine
./scripts/devops/local-manager.sh download PRE-USAGOV-1234-2024-12-22-143022
```

**Backup Types**:

- `all` - All backup types (default)
- `static` - Static site files
- `public` - Public files (uploads, etc.)
- `db` - Database backups

## How It Works

These scripts communicate with Cloud Foundry in two ways:

1. **CF CLI Commands**: Used for querying status, managing apps, etc.

   ```bash
   cf target
   cf apps
   cf env cms
   ```

2. **Remote Execution**: Execute commands inside CF containers via `cf ssh`

   ```bash
   cf ssh cms -c 'cd /var/www && ./scripts/snapshot/manager.sh backup'
   ```

## Relationship to snapshot/ Folder

- **devops/** (this folder): Scripts that run **locally** on your machine
- **snapshot/**: Scripts that run **remotely** inside CF containers

The devops scripts often invoke snapshot scripts remotely:

```bash
# This happens locally:
./scripts/devops/local-manager.sh list

# Which executes this remotely via cf ssh:
./scripts/snapshot/manager.sh list
```

## Dependencies

### Required

- Cloud Foundry CLI (`cf`) - Must be installed and authenticated
- AWS CLI - Configured for S3 access (handled automatically in CF)

### Optional

- `jq` - For JSON parsing (improves output formatting)

## Configuration

Scripts use the backup system configuration from:

- `../snapshot/backup-system.conf` - Backup paths, retention, S3 settings
- Environment variables - Can override config values

## Error Handling

All scripts include:

- Input validation
- Confirmation prompts for destructive operations (unless `-y` flag used)
- Detailed error messages
- Exit codes (0 = success, non-zero = error)

## Best Practices

### Deployment Workflow

1. Set context before starting:

   ```bash
   ./scripts/devops/deploy.sh set-context prod USAGOV-1234
   ```

2. Create pre-deployment backup:

   ```bash
   ./scripts/devops/deploy.sh pre-deploy
   ```

3. Perform deployment:

   ```bash
   # Your deployment commands...
   ```

4. Create post-deployment backup:

   ```bash
   ./scripts/devops/deploy.sh post-deploy
   ```

5. If issues arise, rollback:

   ```bash
   ./scripts/devops/deploy.sh rollback
   ```

### Backup Management

- Use descriptive prefixes: `PRE`, `POST`, `MANUAL`, etc.
- Add ticket numbers to suffixes for tracking
- Regularly clean old backups to manage S3 costs
- Test restores periodically to verify backup integrity

## Troubleshooting

**"cf: command not found"**

- Install Cloud Foundry CLI: <https://docs.cloudfoundry.org/cf-cli/install-go-cli.html>

**"Not logged in"**

- Run: `cf login` or use SSO: `cf login --sso`

**"Permission denied"**

- Ensure scripts are executable: `chmod +x scripts/devops/*.sh`

**"No space targeted"**

- Target your space: `cf target -o <org> -s <space>`

## See Also

- [../snapshot/README.md](../snapshot/README.md) - Remote execution scripts
- [../snapshot/backup-system.conf](../snapshot/backup-system.conf) - Configuration
- [../common.sh](../common.sh) - Shared utility functions
