
# Backup and Restore Site and DB snapshots

## 1. Snapshot backup using helper script *bin/cloudgov/snapshot-backups/stw*

### Setup prior to taking a snapshot backup

Create environment variables in your shell session for

1. The Jira build ticket id

1. The cloud.gov space to which deployment is taking place

1. A description of whether this snapshot is pre or post deployment

        export BRANCH=USAGOV-999
        export SPACE=prod
        export SUFFIX=pre-deploy

1. Proceed to *Static site backup* step

### Static site backup

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX site-snapshot-create
        $echo bin/snapshot-backups/site-snapshot-list ${dryrun}
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX site-snapshot-download

### DB backup

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX db-dump-download
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX db-dump-push-to-snapshot
        $echo bin/snapshot-backups/db-list ${dryrun}

### ***TL;DR for helper script***

*stw* creates a tag string from the branch, space, and suffix arguments, and then runs the command specificed by the last argument.

Note that *stw* will also grab the build string from /etc/motd on the target cms deployment and include it in the tag string.

The tag created by *stw* will look like:

        USAGOV-784.prod.4250.pre-deploy

### A note on the arguments for stw, listed in the examples below:

1. **$SPACE** - specify the space in which the snapshot will be taken/restored (MUST be the current space - this is on purpose)

1. **$BRANCH** - this is free-form, but should probably be the ticket ID for the branch being used to document the deployment being backed up.

1. **$SUFFIX** - this is free-form, but should probably be used to specify at which point in the deployment process the snapshot is taken (e.g. pre-deploy, post-deploy)

1. **command** this is the name of the script to be run by stw (without the path).  Possbible commands are:

* *site-snapshot-create*

* *site-snapshot-download*

* *db-dump-download*

* *db-dump-push-to-snapshot*

### Example

        bin/snapshot-backups/stw prod USAGOV-787 pre-deploy site-snapshot-create

See the file

        bin/deploy/includes

Specifically the functions *assertSpace,  spaceCCIContainerTag* and *createSpaceAssertedBackupTag* for details of how the *stw* (snapshoot tool wrapper) script assembles the backup tag, and asserts that the currect space matches the arguments provided to *stw*

## Snapshot restore using helper

### Static Site Restore

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX site-snapshot-deploy

### DB Restore

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/stw ${dryrun} $SPACE $BRANCH $SUFFIX db-dump-deploy
___

## 2. Snapshot backup - Manual Tag Creation

### Preparation for backup and restore

        Backup tag should be in the format of

        BACKUP_TAG=${BRANCH}.${SPACE}.${CCI_CONTAINERTAG}.${SUFFIX}

        For example:
        USAGOV-784-defacement-recovery.dev.4250.process_test_001

### Manually Tagged Static site backup

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/site-snapshot-create $BACKUP_TAG
        $echo bin/snapshot-backups/site-snapshot-download $BACKUP_TAG
        $echo bin/snapshot-backups/site-snapshot-list

### Manually Tagged DB backup

        echo=echo   Do NOT export echo. Ask me how I know this (tm).
        dryrun='--dryrun'
        $echo bin/snapshot-backups/db-dump-download $BACKUP_TAG
        $echo bin/snapshot-backups/db-dump-push-to-snapshot $BACKUP_TAG
        $echo bin/snapshot-backups/db-list

## 3. Snapshot restore

TBD
