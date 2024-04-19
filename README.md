# USAgov 2021

A revamped USA.gov site using Drupal 9 and Cloud Foundry

<a name="readme-top"></a>

## Table of contents

- [USAgov 2021](#usagov-2021)
  - [Table of contents](#table-of-contents)
  - [Prerequisites](#prerequisites)
  - [Setting Up the Project](#setting-up-the-project)
    - [Initialization](#initialization)
    - [Database Setup](#database-setup)
    - [Media files Setup](#media-files-setup)
    - [Access the Drupal Portal](#access-the-drupal-portal)
    - [Automated tests Setup (cypress)](#automated-tests-setup-cypress))
  - [How to re-run the website](#how-to-re-run-the-website)
    - [Using the terminal](#using-the-terminal)
    - [Using Docker Desktop](#using-docker-desktop)
  - [Theme Lint Guidelines](#theme-lint-guidelines)
  - [Project Restart/Reset](#project-restartreset)
    - [Docker Cleanup](#docker-cleanup)
  - [Update Database](#update-database)
  - [Starting on a new ticket](#starting-on-a-new-ticket)
  - [Continuing Work](#continuing-work)
  - [Tickets and Branching](#tickets-and-branching)
  - [Single Item Config Export](#single-item-config-export)
  - [USAgovTheme](#usagovtheme)
  - [Export Database](#export-database)
  - [Export Config](#export-config)
  - [Import Config](#import-config)
  - [Build and Deploy procedure](#build-and-deploy-procedure)
- [Troubleshooting](#troubleshooting)
  - [If cms password is not accepted:](#if-cms-password-is-not-accepted)
  - [More info on Cloud Foundry \& Cloud.gov](#more-info-on-cloud-foundry--cloudgov)


## Prerequisites

Before starting the project setup install/download the following:

**Note: To download these tools, you must have administrator rights. If you do not have them and you're using a GSA computer, you can request admin rights [here](https://gsa.servicenowservices.com/sp/?id=sc_cat_item&sys_id=d75222fa1b9c641cb1f620eae54bcb20).**


* [Homebrew](https://docs.brew.sh/Installation)
* [Git](https://formulae.brew.sh/formula/git#default)
* [Php](https://formulae.brew.sh/formula/php#default)
* [Composer](https://formulae.brew.sh/formula/composer#default)
* [Docket Desktop](https://docs.docker.com/desktop/install/mac-install/)
* [Drupal](https://www.digitalocean.com/community/tutorials/how-to-develop-a-drupal-9-website-on-your-local-machine-using-docker-and-ddev#step-1-mdash-installing-ddev)
* [jq](https://formulae.brew.sh/formula/jq#default)
* [npm](https://radixweb.com/blog/installing-npm-and-nodejs-on-windows-and-mac)
* An integrated development environment (IDE) such as [Visual Studio Code](https://code.visualstudio.com/download)

[back to top](#usagov-2021)

## Setting Up the Project

### Initialization


Follow these steps when you first start working on the project, or anytime you want to reset your local development environment:


**Note: Please wait until each command finishes before running the next. Expect long wait times. We recommend keeping your laptop (if you're using one) plugged in during this setup.**


1. Open a terminal and go to the project folder.


2. Run the these commands in the terminal:


    ```
    bin/init
    docker compose up
    ```


    Wait until messages stop scrolling by; the final message will probably be a message from node saying "Starting 'watch-sass' ..."


3. Open your browser and go to `localhost` (no port number needed). Initially, this will show an empty Drupal site.
**Note:** You should see logging messages in the terminal as the sote loads. This may take a minute.


[back to top](#usagov-2021)


### Database Setup


Once you finish the previous section, follow these steps to set up your USAgov database:


1. Download the latest SQL database available from [our Google Drive](https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing).


2. Unzip the file and move the .sql file directly into the **root** of your USAgov project folder.


3. Open a new terminal and go to your USAgov project folder.


4. Run one of the following commands to populate the database from the SQL file you downloaded:

    If you sql file is titled `usagov.sql`, run this command:
    ```
    bin/db-update
    ```

    If the file is not titled usagov.sql, run this command:
    ```
    bin/db-update usagov_other.sql
    ```

    **Note:** Expect a message saying there's no need to update the mariadb database.

5. Reload the `localhost` page in your browser. You should now see the beta.usa.gov home page.


[back to top](#usagov-2021)


### Media files Setup


Once you finish the previous section, you may not see the images on the site, please follow these steps to set up the media files:


1. Download the latest ZIP file available from [our Google Drive](https://drive.google.com/drive/folders/1tI4k5qasEtmhxCBuznR3t0fe466milYk?usp=sharing).


2. Go to the **root** of your USAgov project folder in your IDE or preferred file manager system in your computer.


3. Locate the following folder: `s3/local/cms/public`.


4. Unzip the downloaded ZIP file and replace the current `s3/local/cms/public` folder with the one you just unzipped.
5. Reload the `localhost` page in your browser. You should now see the images on the site.


[back to top](#usagov-2021)


### Access the Drupal Portal
To access the Drupal Portal to make any additional configurations, you will need to follow a few more steps.


1. Generate a new URL to access your administrator account.


    ```
    bin/drush uli
    ```


2. The ***unique*** URL will be in some form of


    `http://default/user/reset/1/123456789/ai6u4-iY1LgZFUjwVW2uXjh5jblqgsfUHGFS_U/login`


    Replace the the `default` portion with `localhost`. It should now be in the form:
    `http://localhost/user/reset/1/123456789/ai6u4-iY1LgZFUjwVW2uXjh5jblqgsfUHGFS_U/login`

    **Note:** This is a ONE-TIME login.

3. Adjust your credentials accordingly, so that you have a valid username/password combination to use for logging in to the Drupal portal going forward. You will need a valid username/password combination to provide to the Cypress tests, as well as for logging in to the Drupal portal directly.

    Navigate to [Admin -> People](http://localhost/admin/people), find your user account, and edit it to add a password.

    Log out as root, and log in with your own user account.

**Note:** You will need to repeat these steps any time you re-load the database from a backup. 


### Automated tests setup (cypress)

We use [Cypress](cypress.io). Note that we use only the Cypress App, _not_ Cypress Cloud!

* Tests run in the `cypress` Docker container.
* The tests themselves are in the `automated_tests/e2e-cypress` directory.
* Twig debugging will break some of the tests! Turn it off before running tests to get the most accurate results. 


## Minimal setup for headless tests

1. Supply Drupal *credentials* for the automated tests: Edit the file `env.local.cypress`. Supply a valid Drupal user name and password for `cypressCmsUser` and `cypressCmsPass`. 

2. Run `docker compose up` to (re-)create the cypress container with the new environment variables.

3. Run `bin/cypress-ssh` to open a shell in the cypress container

   You can run `npx cypress run --spec cypress/e2e` to run the entire test suite, or specify a smaller subset like `cypress/e2e/functional`.

   The **Report** will be written to automated_tests/e2e-cypress/cypress/reports/html/index.html and you can open it in your web browser by navigating to that file and opening it. Cypress will report that it wrote the tests to /app/e2e-cypress/cypress/reports/html/index.html, which is the location of that file in the volume mounted to the cypress docker container.  

Note: The first time you run bin/init, it will create the `env.local.cypress` file by copying `env.default.cypress`. The default `cypressBaseUrl` in that file should be correct for running tests against your local dev site.

## Setup for running Cypress interactively

You will need an X11 server on your computer, so that cypress can open a web browser in an X window you can interact with.

### On MacOS, install XQuartz

This setup is based on information from these guides:
* https://www.cypress.io/blog/2019/05/02/run-cypress-with-a-single-docker-command#Interactive-mode
* https://sourabhbajaj.com/blog/2017/02/07/gui-applications-docker-mac/

This assumes you're using homebrew.

1. Check whether [XQuartz](xquartz.org) is already installed:

   ```
   brew info xquartz
   ```

   If XQuartz is already installed, continue with step 3. If you wind up changing any XQuartz settings, you should restart XQuartz, but you don't need to reboot.

2. If XQuartz is not installed, install it:

   ```
   brew install xquartz
   ```

   XQuartz will start up immediately.

3. From the XQuartz `Settings` menu, select `Security` and check (enable) `Allow connections from network clients`.

   The "network client" you're enabling this for is the virtual machine running in your cypress container.

4. Reboot your computer. (You need to reboot once after installing XQuartz. Thereafter, when you change your XQuartz settings you need to restart XQuartz, but not reboot.) 

Proceed to [Allow cypress to open an X window](#allow-cypress-to-open-an-x-window)


### On Windows ... TODO



### Allow cypress to open an X window

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

3. Run `docker compose up` to (re-)create the cypress container with the new environment variable. Alternatively, you can set the DISPLAY variable in the shell you get by running `bin/cypress-ssh`.


[back to top](#usagov-2021)


## How to re-run the website
There are two ways in which you can do this:


### Using the terminal
1. Open Docker Desktop.


2. Open your USAgov project in your IDE.


3. Open a new terminal in your IDE.


4. Make sure you are located in the usagov-2021 folder.


5. Type the following command in your terminal:


    ```
    docker compose up
    ```


### Using Docker Desktop
1. Open Docker Desktop.


2. Click on the section `Containers` located on the left panel.


3. Find the container called `usagov-2021`.


4. Click the play icon located in the column `Actions`.


[back to top](#usagov-2021)


## Theme Lint Guidelines
If you make any changes to the `scss` or `js` files, make sure to check for linting errors nd resolve them before submitting a pull request.


`bin/npm run lint`


[back to top](#usagov-2021)


## Project Restart/Reset
Sometimes, Docker problems arise after an upgrade and a more complete restart is needed. After closing down and destroying the existing containers, networks, and volumes the procedure is the same as the full project setup.


### Docker Cleanup


```
docker compose down
docker system prune
```


Refer to `Full Project Setup` section above to continue the setup.


[back to top](#usagov-2021)


## Update Database
Safe development database dumps are kept in Google Drive. You can download and import a SQL database from https://drive.google.com/drive/folders/1zVDr7dxzIa3tPsdxCb0FOXNvIFz96dNx?usp=sharing.


Copy down the database you want by checking the date in the filename. For example: usagov_01_14_2022.sql.zip.
Unzip the file. It should be renamed to just usagov.sql. Place that uncompressed .sql file into the root of your repo. Then call the bin/db-update script. This could take over 10 mßinutes, so be patient. No messages are good. It will return you to the command prompt when it is done.


1. Download and Unzip the respective zip file
2. Move `usagov.sql` to the root of your project directory
3. Run `bin/db-update` (or `bin/db-update usagov_other.sql` if the file is not titled `usagov.sql`)

[back to top](#usagov-2021)

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


[back to top](#usagov-2021)


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


[back to top](#usagov-2021)


## Tickets and Branching
A branch name must be named after its associated Jira ticket. This is required for some parts of the automation to work. A Branch name must at minumum be USAGOV-###. You may optionally append a short lowercase dash-separated description to make things easier for humans to read.


ex: USAGOV-123-short-ticket-name


If a ticket name is too long, you may shorten or even exclude the title, only the USAGOV-### prefix is required.


We are using a git script to automatically add the current branch name to all commits in an effort to make all commit messages effortlessly reflect the task being worked on. This helps with automation.


```
cp .git.commit-msg .git/hooks/commit-msg
```


[back to top](#usagov-2021)


## Single Item Config Export
If you have lots of junk or temporary config changes in your current database you may opt to only pick out the individual configs you know are needed. You can see the full list of available changes on the main Config Synchronize screen (/admin/config/development/configuration). Once you determine which config changes will be needed you can go to the Export > Single Item (/admin/config/development/configuration/single/export). There you can see and export just that one item.




[back to top](#usagov-2021)


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




[back to top](#usagov-2021)


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




[back to top](#usagov-2021)


## Export Config


1. View differences
   * Configuration > Development > Configuration Synchronization
   * `/admin/config/development/configuration`
2. Export
   * via Command Line
      a. `bin/drush cex`
   * via Export Full Archive
      a. Export > Full Archive
      b. Move the desired configs into `/config/sync`
   * via Export Single Item
      a. Export > Single Item
      b. Find the config you want to sync
      c. Create/Edit the file in `/config/sync` with the filename shown below the config textbox
      d. Paste the config text into the file
      e. Repeat for each desired config
3. Commit config changes to git




[back to top](#usagov-2021)


## Import Config


`bin/drush cim`




[back to top](#usagov-2021)


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




[back to top](#usagov-2021)


# Troubleshooting


## If cms password is not accepted:
* run `bin/drush uli`
* copy the path of the url onto localhost in your browser's URL bar
* follow the prompts to reset the password


`Dockerfile-node` runs the gulp start command.




[back to top](#usagov-2021)


## More info on this project

[Overview of selected documentation in this repo](README-overview.md)

## More info on Cloud Foundry & Cloud.gov


This repository was loosely based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.

