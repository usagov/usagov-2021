# USAgov 2021

A revamped USA.gov site using Drupal 9 and Cloud Foundry.

# Initial Setup

```
# input any secrets you need into env.local
cp env.default env.local
# build all the containers
docker compose build
# make sure a local version of vendor is created on your local fs
bin/composer install --ignore-platform-reqs --no-interaction --no-progress --optimize-autoloader
# make sure a local version of node_modules is created on your local fs
bin/npm install
# start up the app
docker compose up
```

# Update Database

Import SQL database from Google Drive. https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing

Copy down the database you want by checking the date in the filename. For example: usagov_01_14_2022.sql.zip
Place that compressed sql file into the root of your repo. Unzip the file. it should now be named just usagov.sql. Then call the db update script. This will take over 10 minutes, so be patient. No messages are good. It will return you to the command prompt when it is done.

```
bin/db-update
```

By default the script expects a usagov.sql file to exist. If you have mulitple files to choose from just pass in the specific name of the sql file as a parameter.

```
bin/db-update usagov_other.sql
```

# New Work
Start with the latest changes from main branch and create a new feature branch
```
git checkout main
git fetch
git pull
git checkout -b new-feature-branch
docker compose up
```

# Continuing Work

Pull the latest changes from main branch into your active feature branch
```
git checkout main
git fetch
git pull
git checkout my-feature-branch
git merge main
docker compose up
```

## Ticket
Create a ticket

## Branching
Get URL path from ticket
ex: 123-ticket-name
Prepend with usa-
ex: usa-123-ticket-name
This will be the branch name

(If the ticket name is too long, you may shorten it or remove it. Only the usa-123 is needed)

We are using a script in `.git/hooks/commit-msg` to automatically add the current branch name to all commits, to make commit messages effortlessly reflect the task being worked on.

## Single Item Config Export
* Visit
On synchronize screen, determine which configs will be used. Then go to the Export > Single Item. Find the item. Create


# USAgovTheme
The USAgov theme is a subtheme of the USWDS_base theme.

This theme adds `USWDS_CKEditor_Custom_Styles.scss` into the CKeditor frame.

## Export Database

`bin/drush sql:dump --resultflie=../backup.sql`

## Import Database

Copy file into the root directory
`docker ps` to get database container ID
`docker cp usagov.sql [MYSQL CONTAINER ID]:/home/`
`docker exec -it [MYSQL CONTAINER ID] /bin/bash`
`mysql -u root -p`
* `drop database drupal;`
* `create database drupal;`
* `exit`

`mysql -u root -p drupal < [FILENAME]`
Clear Cache


## Import Database
`bin/ssh`
`drush sql-cli < backup.sql`
## Export Config

1. View differences
    * Configuration > Development > Configuration Synchronization
    * `/admin/config/development/configuration`
2. Export
    * via Command Line
        1. `bin/drush cex`
    * via Export Full Archive
        1. Export > Full Archive
        2. Move the desired configs into `/config/sync`
    * via Export Single Item
        1. Export > Single Item
        2. Find the config you want to sync
        3. Create/Edit the file in `/config/sync` with the filename shown below the config textbox
        4. Paste the config text into the file
        5. Repeat for each desired config
3. Commit config changes to git

## Import Config
`bin/drush cim`

## Builds
```
bin/gulp build
bin/composer install --ignore-platform-reqs --no-interaction --no-progress --optimize-autoloader
bin/cloudgov/login
bin/cloudgov/container-build main
bin/cloudgov/container-push main
bin/cloudgov/space
bin/cloudgov/deploy main
```

# Troubleshooting
## If cms password is not accepted:
* run `bin/drush uli`
* copy the path of the url onto localhost in your browser's URL bar
* follow the prompts to reset the password

`Dockerfile-node` runs the gulp start command.



## More info on Cloud Foundry & Cloud.gov

This repository was loosely based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.
