# USAgov 2021

A revamped USA.gov site using Drupal 9 and Cloud Foundry

## Initial Project Setup
At the start of the project and at any other time you wish to "reset" your local development environment you may run the init script to prep any necessary files and rebuild containers. Starting the containers will initially lead you to an empty Drupal site.

```
bin/init
docker compose up
```

## Full Project Setup
### Download SQL Database
Safe development database dumps are kept in Google Drive. You can download and import a SQL database from https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing. We recommend using the latest database available.

Unzip the file and insert directly into the **root** directory.

### Initialization
**Note: Please wait until each command finishes before running the next. Expect long wait times. We recommend keeping your laptop (if you're using one) plugged in during this setup.**

1. Open up your IDE/terminal and run the following commands.
```
bin/init
docker compose up
```

Wait until messages stop scrolling by; the final message will probably be a message from node saying "Starting 'watch-sass' ..."

2. Head to `localhost` (no port number needed) in your respective browser. Initially, this will show an empty Drupal site. 

Web logging from "cms" should appear in your terminal as the request is served. This can take a minute to get started.

3. Open another terminal, navigate to the root of your repo, and run this command to populate the database from the SQL file you downloaded:

```
bin/db-update
```

(Expect a message saying there's no need to update the mariadb database.)

4. Reload the `localhost` page in your browser. It should now show a beta.usa.gov home page. 

## Access the Drupal Portal
If you would like to access the Drupal Portal to make any additional configurations, you will need to follow a few more steps.

1. Generate a new URL to access your administrator account.
    ```
    bin/drush uli
    ```

2. The ***unique*** URL will be in some form of

    `http://default/user/reset/1/123456789/ai6u4-iY1LgZFUjwVW2uXjh5jblqgsfUHGFS_U/login`

    Replace the the `default` portion with `localhost`. It should now be in the form:
    
    `http://localhost/user/reset/1/123456789/ai6u4-iY1LgZFUjwVW2uXjh5jblqgsfUHGFS_U/login`

3. Adjust your credentials accordingly.

    **Note: This is a ONE-TIME login. You'll automatically be logged in during future uses. However, if you ever reset your container, you will have to redo this process.**

## Theme Lint Guidelines
If you make any changes to the `scss` or `js` files, make sure to check for linting errors nd resolve them before submitting a pull request.

`bin/npm run lint`

## Project Restart/Reset
Sometimes, Docker problems arise after an upgrade and a more complete restart is needed. After closing down and destroying the existing containers, networks, and volumes the procedure is the same as the full project setup.

### Docker Cleanup

```
docker compose down
docker system prune
```

Refer to `Full Project Setup` section above to continue the setup.

## Update Database
Safe development database dumps are kept in Google Drive. You can download and import a SQL database from https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing.

Copy down the database you want by checking the date in the filename. For example: usagov_01_14_2022.sql.zip.
Unzip the file. It should be renamed to just usagov.sql. Place that uncompressed .sql file into the root of your repo. Then call the bin/db-update script. This could take over 10 minutes, so be patient. No messages are good. It will return you to the command prompt when it is done.

1. Download and Unzip the respective zip file
2. Move `usagov.sql` to the root of your project directory
3. Run `bin/db-update` (or `bin/db-update usagov_other.sql` if the file is not titled `usagov.sql`)

## Starting on a new ticket
When starting new work you may have to reset your database to a good starting point and make sure the current Drupal config is reflected in the site.

```
# Switch to stable starting point
git checkout dev
git fetch
git pull

# Reset db
bin/db-update
bin/drupal-update
docker compose up

# Start new work
git checkout -b USAGOV-###-new-feature-branch
```

## Continuing Work
If you are returning to work on an existing feature branch you will need to make sure to update it with the latest changes from a fresh dev branch. It is also good practice to update any branch you are working on frequently.

```
# switch out of feature branch and into dev branch
git checkout dev
git fetch
git pull

# switch back into feature branch
git checkout USAGOV-###-existing-feature-branch
git merge dev
docker compose up
```

## Tickets and Branching
A branch name must be named after its associated Jira ticket. This is required for some parts of the automation to work. A Branch name must at minumum be USAGOV-###. You may optionally append a short lowercase dash-separated description to make things easier for humans to read.

ex: USAGOV-123-short-ticket-name

If a ticket name is too long, you may shorten or even exclude the title, only the USAGOV-### prefix is required.

We are using a git script to automatically add the current branch name to all commits in an effort to make all commit messages effortlessly reflect the task being worked on. This helps with automation.

```
cp .git.commit-msg .git/hooks/commit-msg
```

## Single Item Config Export
If you have lots of junk or temporary config changes in your current database you may opt to only pick out the individual configs you know are needed. You can see the full list of available changes on the main Config Synchronize screen (/admin/config/development/configuration). Once you determine which config changes will be needed you can go to the Export > Single Item (/admin/config/development/configuration/single/export). There you can see and export just that one item.


## USAgovTheme
The USAgov theme is a subtheme of the USWDS_base theme.
This project's default start procedure (docker compose up) will start a container to automatically watch for changes and recompile the theme as needed.

The theme can be manually built at any time through gulp's build task. Any other gulp task can be triggered the same way.

```
# Rebuild theme
bin/npm run build
```

Any changes made to the node modules needed for building the theme will require a re-install of the node_modules before build.

```
# Reinstall node modules
bin/npm install
bin/npm run build
```


This theme adds `USWDS_CKEditor_Custom_Styles.scss` into the CKeditor frame.

## Export Database
A helper script has been provided to perform exports.

```
bin/db-export
```

You may specify a filename if you don't want to overwrite the default file location with a new export.

```
bin/db-export other-backup.sql
```

This process asks drush to export the database for us since it does some cleanup work before running the export.


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

## Build and Deploy procedure
Production ready containers can be built and deployed from a local environment. To do so, proper secrets must be entered into the env.local file as environmental variables. This same procedure is used by CircleCI and is defined in .circleci/config.yml


```
# uses env.local
bin/cloudgov/container-build TAGNAME
bin/cloudgov/container-push TAGNAME
bin/cloudgov/login
bin/cloudgov/space dev
bin/cloudgov/deploy TAGNAME
```

# Troubleshooting

## If cms password is not accepted:
* run `bin/drush uli`
* copy the path of the url onto localhost in your browser's URL bar
* follow the prompts to reset the password

`Dockerfile-node` runs the gulp start command.


## More info on Cloud Foundry & Cloud.gov

This repository was loosely based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.
