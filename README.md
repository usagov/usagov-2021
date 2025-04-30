# USAgov 2021

A revamped USA.gov site using Drupal 10 and Cloud Foundry

<a name="readme-top"></a>

## Table of contents

- [USAgov 2021](#usagov-2021)
  - [Table of contents](#table-of-contents)
  - [Prerequisites](#prerequisites)
  - [Setting Up the Project](#setting-up-the-project)
    - [Initialization](#initialization)
    - [Database Setup](#database-setup)
    - [Media Files Setup](#media-files-setup)
    - [Access the Drupal Portal](#access-the-drupal-portal)
    - [Automated Tests Setup (Cypress)](#automated-tests-setup-cypress)
  - [How to Re-run the Website](#how-to-re-run-the-website)
    - [Using the Terminal](#using-the-terminal)
    - [Using Docker Desktop](#using-docker-desktop)
  - [Theme Lint Guidelines](#theme-lint-guidelines)
  - [Project Restart/Reset](#project-restartreset)
    - [Docker Cleanup](#docker-cleanup)
  - [Update Database](#update-database)
  - [Starting on a New Ticket](#starting-on-a-new-ticket)
  - [Continuing Work](#continuing-work)
  - [Tickets and Branching](#tickets-and-branching)
  - [Single Item Config Export](#single-item-config-export)
  - [USA.gov Theme](#usagovtheme)
  - [Export Database](#export-database)
  - [Export Config](#export-config)
  - [Import Config](#import-config)
  - [Build and Deploy Procedure](#build-and-deploy-procedure)
- [Troubleshooting](#troubleshooting)
  - [If CMS Password is Not Accepted:](#if-cms-password-is-not-accepted)
  - [More Info on Cloud Foundry \& Cloud.gov](#more-info-on-cloud-foundry--cloudgov)

## Prerequisites

Before starting the project setup, install/download the following:

**Note:** To download these tools, you must have administrator rights. If you do not have them and you're using a GSA computer, you can request admin rights [here](https://gsa.servicenowservices.com/sp/?id=sc_cat_item&sys_id=d75222fa1b9c641cb1f620eae54bcb20).

* [Homebrew](https://docs.brew.sh/Installation)
* [Git](https://formulae.brew.sh/formula/git#default)
* [Php](https://formulae.brew.sh/formula/php#default)
* [Composer](https://formulae.brew.sh/formula/composer#default)
* [Docker Desktop](https://docs.docker.com/desktop/install/mac-install/)
* [jq](https://formulae.brew.sh/formula/jq#default)
* [npm](https://radixweb.com/blog/installing-npm-and-nodejs-on-windows-and-mac)
* An integrated development environment (IDE) such as [Visual Studio Code](https://code.visualstudio.com/download)

[back to top](#usagov-2021)

## Setting Up the Project

### Initialization
Follow these steps when you first start working on the project, or anytime you want to reset your local development environment:

**Note:** Please wait until each command finishes before running the next. Expect long wait times. Keep your laptop plugged in during this setup.

1. Open a terminal and go to the project folder.
2. Run these commands in the terminal:
    ```
    bin/init
    docker compose up
    ```
    Wait until messages stop scrolling by; the final message will probably be a message from node saying "Starting 'watch-sass' ..."
3. Open your browser and go to [http://localhost](http://localhost) (no port number needed). Initially, this will show an empty Drupal site.

**Note:** You should see logging messages in the terminal as the site loads. This may take a minute.
[back to top](#usagov-2021)

### Database setup
Once you finish the previous section, follow these steps to set up your USAgov database:

1. Download the latest SQL database available from [our Google Drive](https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing).
2. Unzip the file and move the .sql file directly into the **root** of your USAgov project folder.
3. Open a new terminal and go to your USAgov project folder.
4. Run one of the following commands to populate the database from the SQL file you downloaded:
    If your SQL file is titled `usagov.sql`, run this command:
    ```
    bin/db-update
    ```

    If the file is not titled usagov.sql, run this command:
    ```
    bin/db-update usagov_other.sql
    ```

    **Note:** Expect a message saying there's no need to update the MariaDB database.
5. Reload `localhost` in your browser. You should now see the home page.

[back to top](#usagov-2021)

### Media files setup
Once you finish the previous section, you may not see the images on the site. Please follow these steps to set up the media files:

1. Download the latest ZIP file available from [our Google Drive](https://drive.google.com/drive/folders/1tI4k5qasEtmhxCBuznR3t0fe466milYk?usp=sharing).
2. Go to the **root** of your USAgov project folder in your IDE or preferred file manager system in your computer.
3. Locate the following folder: `s3/local/cms/public`.
4. Unzip the downloaded ZIP file and place the files inside the `s3/local/cms/public` directory.
5. Reload the `localhost` page in your browser. You should now see the images on the site.

[back to top](#usagov-2021)


### Access the Drupal portal
There are two ways access the Drupal Admin Portal.

## If you already have an account
1. Update your password
  ```
  bin/drush upw my_name somePassword
  ```
2. After updating your password, you can sign in normally

## If you don't already have an account
1. Generate a one time, unique url to access your administrator account.
    ```
    bin/drush uli
    ```
2. Replace `default` with `localhost`. It should now be in the form:

    `http://localhost/user/reset/1/123456789/ai6u4-iY1LgZFUjwVW2uXjh5jblqgsfUHGFS_U/login`

3. Adjust your credentials accordingly, so that you have a valid username/password combination to use for logging into
the Drupal portal going forward. You will need a valid username/password combination to provide to the Cypress tests,
as well as for logging in to the Drupal portal directly.

4. Navigate to [Admin -> People](http://localhost/admin/people), find your user account, and edit it to add a password.

5. Log out as root, and log in with your own user account.

**Note:** You will need to repeat these steps any time you re-load the database from a backup.

### Automated tests setup (Cypress)
We use [Cypress](http://www.cypress.io). Note that we use only the Cypress App, _not_ Cypress Cloud!

* Tests run in the `cypress` Docker container.
* The tests themselves are in the `automated_tests/e2e-cypress` directory.
* Twig debugging will break some of the tests! Disable it before running tests to get the most accurate results.

## Minimal setup for headless tests
1. Provide Drupal credentials for the automated tests by editing the `env.local.cypress` file. Enter a valid Drupal username and password values for the `cypressCmsUser` and `cypressCmsPass`.
2. Run `docker compose build cypress` to rebuild the cypress container with the new environment variables.
3. Run `bin/cypress-ssh` to open a shell in the cypress container

   You can run `npx cypress run --spec cypress/e2e` to run the entire test suite, or specify a smaller subset like `cypress/e2e/functional`.

   The **Report** will be written to `automated_tests/e2e-cypress/cypress/reports/html/index.html` and you can open it
in your web browser by navigating to that file. Cypress will report that it wrote the tests to
`/app/e2e-cypress/cypress/reports/html/index.html`, which is the location of that file in the volume mounted to the cypress docker container.

**Note:** The first time you run `bin/init`, it will create the `env.local.cypress` file by copying `env.default.cypress`.
The default `cypressBaseUrl` in that file should be correct for running tests against your local dev site.

## Setup for running Cypress interactively
You will need an X11 server on your computer, so that Cypress can open a web browser in an X window you can interact with.

### On MacOS, install XQuartz
This setup is based on information from these guides:
* [How to Run Cypress in Docker With a Single Command](https://www.cypress.io/blog/2019/05/02/run-cypress-with-a-single-docker-command#Interactive-mode)
* [Running GUI applications using Docker for Mac](https://sourabhbajaj.com/blog/2017/02/07/gui-applications-docker-mac/)

This assumes you're using homebrew.

1. Check whether [XQuartz](https://www.xquartz.org/) is already installed:
   ```
   brew info xquartz
   ```
   If XQuartz is already installed, continue with step 3. Restart XQuartz if you make any changes to the settings, but rebooting is unnecessary.

2. If XQuartz is not installed, install it:
   ```
   brew install xquartz
   ```
   XQuartz will start up immediately.

3. From the XQuartz `Settings` menu, select `Security` and check (enable) `Allow connections from network clients`.

   The "network client" you're enabling this for is the virtual machine running in your cypress container.

4. Reboot your computer. (You need to reboot once after installing XQuartz. Thereafter, when you change your XQuartz settings you need to restart XQuartz, but not reboot.)

Proceed to [Allow Cypress to Open an X Window](#allow-cypress-to-open-an-x-window)

### On Windows ... TODO

### Allow Cypress to open an X window
You'll need your local IP address. You're looking for the IP address of your machine on your local network, so it will probably start with `10.` or `192.168.`. This command will probably work:
   ```
   LOCAL_IP=$(ifconfig en0 | grep inet | awk '$1=="inet" {print $2}')
   echo $LOCAL_IP
   ```

On a Mac, you can also find your IP Address in your Network Settings, or by holding down the `Option` key and clicking on the Wifi icon in your menu bar.

Note that this address will change if you change networks, or if you disconnect and reconnect to the network

1. Allow connections from your IP address to open X windows:
   ```
   xhost $LOCAL_IP
   ```
   You should see a message like `10.0.0.200 being added to access control list`. (If you see `no DISPLAY is set`, that might mean you didn't reboot after installing XQuartz.)
2. Edit `env.local.cypress`. The `DISPLAY` variable should be set to your local IP address with `:0` after it, for example, `DISPLAY=10.0.0.200:0`
3. Run `docker compose up` to rebuild the cypress container with the new environment variable. Alternatively, you can set the DISPLAY variable in the shell you get by running `bin/cypress-ssh`.

[back to top](#usagov-2021)

## How to re-run the website
There are two ways in which you can do this:

### 1. Using the terminal
1. Open Docker Desktop.
2. Open your USAgov project in your IDE.
3. Open a new terminal in your IDE.
4. Make sure you are located in the `/usagov-2021` directory.
5. Type the following command in your terminal:
    ```
    docker compose up
    ```

### 2. Using Docker Desktop
1. Open Docker Desktop.
2. Click on the section `Containers` located on the left panel.
3. Find the container called `usagov-2021`.
4. Click the play icon located in the column `Actions`.

[back to top](#usagov-2021)

## Theme lint guidelines
If you make any changes to the `scss` or `js` files, make sure to check for linting errors and resolve them before
submitting a pull request.

`bin/npm run lint`

[back to top](#usagov-2021)

## Checking PHP code style and syntax errors
PHPCodesniffer and the parallel linting tools should be installed automatically on a local environment via `composer install`. PHPCodeSniffer is used to ensure new code follows Drupal's coding standard. The parallel linter will check for PHP syntax errors. If they detect any errors, they must be fixed before a PR of changes can be accepted.

The following composer scripts are aliases for running these tools.

* Check for code style errors across all project files. Must have zero errors:
`./bin/composer phpcs-errors`
* Check for code style errors and warnings across all project file
`./bin/composer phpcs-strict`
* Check for code style errors in current branch. Must have zero errors:
  `./bin/composer phpcs-changes`
* Check for code style errors and warnings in current branch.
  `./bin/composer phpcs-changes-strict`
* Check for PHP lint errors
  `./bin/composer php-lint`
* Check for PHP 8.3 compatibility
  `./bin/composer php-compatibility`

## Checking code with PHPStan

[PHPStan](https://phpstan.org/) is available to statically analyze custom theme and module code for correctness.

It's defined as a dev dependency in `composer.json` and will be installed automatically when you run the build scripts or `bin/composer` install.


The following composer scripts are aliases for running PHPStan

* Check for errors at the level configured in `phpstan.neon`:

  `./bin/composer phpstan`

   (optionally, supply a file or directory name starting with `web/`)


## Project restart/reset
Sometimes, Docker problems arise after an upgrade and a more complete restart is needed. After closing down and
destroying the existing containers, networks, and volumes the procedure is the same as the full project setup.

### Docker cleanup
```
docker compose down
docker system prune --filter "label=com.docker.compose.project=usagov-2021"
```

Refer to [Full Project Setup](#setting-up-the-project) section above to continue the setup.

[back to top](#usagov-2021)

## Update database
Safe development database dumps are kept in Google Drive. You can download and import a SQL database from [our Google Drive](https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing).

Copy down the database you want by checking the date in the filename. For example: usagov_01_14_2022.sql.zip.
Unzip the file. Rename it to usagov.sql. Place the uncompressed .sql file into the root of your repo. Then call the
`bin/db-update` script. This could take over 10 minutes, so be patient. No messages are good. It will return you to the
command prompt when it is done.

1. Download and Unzip the respective zip file
2. Move `usagov.sql` to the root of your project directory
3. Run `bin/db-update` (or `bin/db-update usagov_other.sql` if the file is not titled `usagov.sql`)

[back to top](#usagov-2021)

## Starting on a new ticket
When starting new work you may have to reset your database to a good starting point and make sure the current Drupal
config is reflected in the site.
```
# Switch to stable starting point
git checkout dev
git pull

# Reset db
bin/db-update
bin/drupal-update
docker compose up

# Start new work
git checkout -b USAGOV-###-new-feature-branch
```

[back to top](#usagov-2021)

## Continuing work
If you are returning to work on an existing feature branch you will need to make sure to update it with the latest changes from a fresh dev branch. It is also good practice to update any branch you are working on frequently.
```
# while working on your feature branch
git remote update
git pull origin dev
bin/drush cim
```

[back to top](#usagov-2021)

## Tickets and branching
Branch names must follow the format of their associated Jira ticket to ensure proper functionality of automation processes.
At a minimum, a branch name should be in the format **USAGOV-###**.
Optionally, you can append a short, lowercase, dash-separated description to improve readability for humans.

ex: USAGOV-123-short-ticket-name

If a ticket name is too long, you may shorten or even exclude the title, only the USAGOV-### prefix is required.

As part of the `bin/init` process, we copy a Git hook script (`.git.commit-msg` to `.git/hooks/commit-msg`)
to automatically include the current branch name in all commit messages. This ensures that commit messages consistently
reflect the task being worked on, streamlining automation.

**Note:** Any changes to these files will be overwritten the next time ```bin/init``` is run.

[back to top](#usagov-2021)

## Single item config export
If you have lots of junk or temporary config changes in your current database you may opt to only pick out the individual configs you know are needed.
You can see the full list of available changes on the main [Config Synchronize page](http://localhost/admin/config/development/configuration).
Once you determine which config changes will be needed you can go to the [Export > Single Item](http://localhost/admin/config/development/configuration/single/export). There you can see and export just that one item.

[back to top](#usagov-2021)

## USAgovTheme
The USAgov theme is a subtheme of the USWDS_base theme.
This project's default start procedure (`docker compose up`) will start a container to automatically watch for changes and recompile the theme as needed.

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

[back to top](#usagov-2021)

## Export database
A helper script has been provided to perform exports.
```
bin/db-export
```

You may specify a filename if you don't want to overwrite the default file location with a new export.
```
bin/db-export other-backup.sql
```

This process asks drush to export the database for us since it does some cleanup work before running the export.

[back to top](#usagov-2021)

## Export config
1. View differences
   * [Configuration > Development > Configuration Synchronization](http://localhost/admin/config/development/configuration)
2. Export
   * via Command Line
      - `bin/drush cex`
   * via Export Full Archive
      - [Export > Full Archive](http://localhost/admin/config/development/configuration/full/export)
      - Move the desired configs into `/config/sync`
   * via Export Single Item
      - [Export > Single Item](http://localhost/admin/config/development/configuration/single/export)
      - Find the config you want to sync
      - Create/Edit the file in `/config/sync` with the filename shown below the config textbox
      - Paste the config text into the file
      - Repeat for each desired config
3. Commit config changes to git

[back to top](#usagov-2021)

## Import config

`bin/drush cim`

[back to top](#usagov-2021)

## Build and deploy procedure
Containers can be built and deployed to the dev environment or to the developer's sandbox space.
To do so, proper secrets must be entered into the env.local file as environmental variables.
This same procedure is used by CircleCI and is defined in `.circleci/config.yml`.
The deployment procedures scripted for CircleCI include additional security measures. Developers should not deploy
into the production environment using the example commands below.

```
# uses env.local
bin/cloudgov/container-build TAGNAME
bin/cloudgov/container-push TAGNAME
bin/cloudgov/login
bin/cloudgov/space dev
bin/cloudgov/deploy TAGNAME
```

[back to top](#usagov-2021)

# Troubleshooting
## If cms password is not accepted:
* follow instructions found in [Access the Drupal Portal](#access-the-drupal-portal)

[back to top](#usagov-2021)

## More info on this project
[Overview of selected documentation in this repo](README-overview.md)

## More info on Cloud Foundry & Cloud.gov
This repository was loosely based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.
