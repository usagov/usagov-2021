
# Backup and Restore Site and DB snapshots

## Preparation for backup and restore

        export SPACE=stage

        cf target -s $SPACE

        cf ssh cms -c "cat /etc/motd" > $SPACE.motd

        export CONTAINER_TAG=$(grep containertag $SPACE.motd | sed 's/containertag\:\s*//' | sed 's/^\s*//')

        export BACKUP_TAG=USAGOV-787.${CONTAINER_TAG}_pre-deploy

        PREFIX=$BACKUP_TAG.$SPACE

        ### just display commands, comment when comfortable
        export echo=echo

## Static Site Backup

        $echo bin/cloudgov/static-site-snapshot $BACKUP_TAG
        $echo bin/cloudgov/static-site-pull $BACKUP_TAG
        $echo bin/cloudgov/static-site-list

## DB Backup

        $echo bin/cloudgov/db-pull $BACKUP_TAG
        $echo bin/cloudgov/db-push $BACKUP_TAG
        $echo bin/cloudgov/db-list

## Random commands, just for now


