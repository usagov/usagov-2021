
# Backup and Restore Site and DB snapshots

## 1. Snapshot backup

### Preparation for backup and restore

        Backup tag should be in the format of

        BACKUP_TAG=${BRANCH}.${SPACE}.${CCI_CONTAINERTAG}.${SUFFIX}

        For example:
        USAGOV-784-defacement-recovery.dev.4250.process_test_001

### Static site backup
        export echo=echo
        $echo bin/snapshot-backups/site-snapshot-create $BACKUP_TAG
        $echo bin/snapshot-backups/site-snapshot-download $BACKUP_TAG
        $echo bin/snapshot-backups/site-snapshot-list

### DB backup
        export echo=echo
        $echo bin/snapshot-backups/db-dump-download $BACKUP_TAG
        $echo bin/snapshot-backups/db-dump-push-to-snapshot $BACKUP_TAG
        $echo bin/snapshot-backups/db-list

## 2. Snapshot backup using helper (creates tag from Branch, Space, Suffix. Then runs command)

### See *bin/deploy/includes*, specifically the functions *assertSpace, assertBranch, spaceCCIContainerTag* and *createAssertedBackupTag* for details of how the *stw* (snapshoot tool wrapper) script assembles the backup tag, and asserts that the currect branch and space match the arguments provided to *stw*

### Static site backup
        export echo=echo
        $echo bin/snapshot-backups/stw $SPACE $BRANCH $SUFFIX site-snapshot-create
        $echo bin/snapshot-backups/stw $SPACE $BRANCH $SUFFIX site-snapshot-download
        $echo bin/snapshot-backups/site-snapshot-list

### DB backup
        export echo=echo
        $echo bin/snapshot-backups/stw $BACKUP_TAG db-dump-download
        $echo bin/snapshot-backups/stw $BACKUP_TAG db-dump-push-to-snapshot
        $echo bin/snapshot-backups/db-list
## 3. Snapshot restore

## 4. Snapshot restore using helper
