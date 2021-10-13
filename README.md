# USAgov 2021

A revamped USA.gov site using Drupal 9 and Cloud Foundry.

# Initial Setup

Import SQL database


```
cp env.default env.local
docker compose build
bin/composer install
docker compose up
```

# Daily Setup

Pull the latest changes from main branch into feature branch
`git checkout feature-branch`
`git merge main`
```
docker compose up
```

# Trello Ticketing
Create a ticket
Get URL path from ticket
ex: 123-ticket-name
Prepend with usa-
ex: usa-123-ticket-name
(If the ticket name is too long, you may shorten it or remove it. Only the usa-123 is needed)

# USAgovTheme
The USAgov theme is a subtheme of the USWDS_base theme.

This theme adds `USWDS_CKEditor_Custom_Styles.scss` into the CKeditor frame.

## Export Database

`bin/drush sql:dump --resultflie=../backup.sql`

## Import Database
`bin/ssh`
`drush sql-cli < backup.sql`
## Export Config

1. View differences
    * Configuration > Development > Configuration Synchronization
    * `/admin/config/development/configuration`
2. Export 
    * `bin/drush cex`
    * Export > Full Archive
    * Export > Single Item
3. Commit config changes to git

## Import Config
`bin/drush cim`

# Troubleshooting
## If cms password is not accepted:
* run `bin/drush uli`
* copy the path of the url onto localhost in your browser's URL bar
* follow the prompts to reset the password

`Dockerfile-node` runs the gulp start command.


## More info on Cloud Foundry & Cloud.gov

This repository was loosely based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.
