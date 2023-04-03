## Spaces

Cloud.gov calls application environments Spaces. Each developer should have access to four spaces: a personal Space and one Space each for the Dev, Stage, and Prod enviroents.

## Dashboard

To view running resources in your available spaces go to this url.

https://dashboard.fr.cloud.gov/

## Command line

All interaction with CloudGov happens with the CloudFoundry Client (cf) via the command line.
To be able to run commands targeted toward a CloudGov Space you must be logged in. Run this command and follow the instructions:

```
bin/cloudgov/login
```

Most actions a developer would want to perform will be built into a script placed in the /bin directory of the repo. Actions targeted towards the local environment will live in the /bin dir, actions targeted towards the hosted CloudGov environments will live in the /bin/cloudgov directory.

This command can be used to see which space you are currently targeting:

```
bin/cloudgov/space
```

This command can be user to switch between spaces:

```
bin/cloudgov/space personal
bin/cloudgov/space dev
bin/cloudgov/space stage
bin/cloudgov/space prod
bin/cloudgov/space shared-egress
```

All subsequent commands will be targeted towards the choosen space

### Build/Deploy

The process of deploying this application to a hosted environment is composed of three steps:
1. Building the Container representing a release of the application (bin/cloudgov/container-build)
2. Pushing the container to a Docker Hub location that can be accessed by CloudGov (bin/cloudgov/container-push)
3. Deploying the CloudGov App based on the Container (bin/cloudgov/deploy)

### Downsynching

Periodically, we want to overwrite the stage and dev systems with data from prod. To do this, we need to copy and update both the database and the public files. This example assumes you're updating stage. 

Make backups of the data from stage, just in case. Note that db-pull will overwrite usagov.sql on the local filesystem, as well as generating a zipped database copy:  
  
```
cf target -s stage
bin/cloudgov/db-pull
mkdir /tmp/files-from-stage
bin/cloudgov/s3-files-pull-to-tmp /tmp/files-from-stage
```

Download the prod database and files. Either get them from the shared Drive where we save them after a prod deploy, or do this:

```
cf target -s prod
bin/cloudgov/db-pull
mkdir /tmp/files-from-prod
bin/cloudgov/s3-files-pull-to-tmp /tmp/files-from-prod
```

Target stage and upload the files to S3:

```
cf target -s stage
bin/cloudgov/s3-files-push-from-tmp /tmp/files-from-prod
```

Still targeting stage, upload the database dump to the cms instance. (I use gzip to compress the file before uploading it; the cms app won't have the right :

```
cf target -s stage
bin/cloudgov/scp-to cms usagov-prod_[datestring].sql.zip /tmp
cf ssh cms
 (on cms)
 cd /tmp
 unzip bin/cloudgov/scp-to cms usagov-prod_[datestring].sql.zip /tmp
 (should extract usagov.sql)
 . /etc/profile
 drush sql-cli < ./usagov.sql

 rm usagov.sql usagov*.sql.zip
```

Log in to cms-stage.usa.gov and check things out -- particularly the media library. 

