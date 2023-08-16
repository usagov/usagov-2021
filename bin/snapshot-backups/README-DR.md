# Backup and Restore Site and DB snapshots

## 1. All-in-one Snapshot backup script (preferred method)

A. Create environment variables in your shell session for

1. The Jira build ticket id

1. The cloud.gov space to which deployment is taking place

1. A string indicating the type of backup (e.g. pre or post deployment, emergency, interrim, etc.)

        BRANCH=USAGOV-999
        SPACE=prod
        SUFFIX=pre-deploy

B. Ensure you are in the Cloud Foundry space which you want to backup e.g:

        cf target -s $SPACE

C. Run the all-in-one backup script.  This will fail if the current CF space does not match the SPACE env var. This will wait for a currently-running tome job to complete before proceeding.  It will wait up to 25 muinutes.

        dryrun='--dryrun'
        bin/snapshot-backups/local-snapshot-backup ${dryrun} $SPACE $BRANCH $SUFFIX

D. Make a note of the snapshot tag string emitted by the previous script, and set and environment variable for it.  The string will look like "USAGOV-999.prod.1234.pre-deploy" or similar.  It is made up of the TICKET, SPACE and SUFFIX environment variables, and a number - representing the CircleCI build number for the deployment being backed up:

      SNAPTAG=USAGOV-999.prod.1234.pre-deploy

E. Run the all-in-one snapshot download script (downloads the snapshot zips to the current directory)

        dryrun='--dryrun'
        bin/snapshot-backups/local-snapshot-backup ${dryrun} $SPACE $SNAPTAG

F. Copy the downloaded snapshot zips to the appropriate Google Drive folders:

- Database snapshot zip file (e.g. USAGOV-999.prod.1234.pre-deploy.sql.gz)

    usa.gov/USAgov Databases : https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx

- Public files snapshot zip file (e.g. USAGOV-999.prod.1234.pre-deploy.public.zip)

    usagov/StaticSiteBackups : https://drive.google.com/drive/folders/1EFJX3fGe4tyfYtK7T9jTqQ3GVw6Ugk0c

- Static site snapshot zip file (e.g. USAGOV-999.prod.1234.pre-deploy.zip)

    usagov/CMSPublicFilesBackups : https://drive.google.com/drive/folders/1tI4k5qasEtmhxCBuznR3t0fe466milYk

## 1. All-in-one Snapshot restore script (preferred method)
