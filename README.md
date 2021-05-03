# cf-ex-drupal9

Drupal 9 example for Cloud Foundry


This repository demonstrates how to run a production-worthy Drupal 8 site in Cloud Foundry. Folks just getting started with [cloud.gov](https://cloud.gov) can use this as a path from “I have a cloud.gov account” to “I have a production-worthy Drupal site running on a FedRAMP-authorized CSP that I understand how to update, just waiting for me to customize it”.

The code examples target [cloud.gov](https://cloud.gov) but are easily adapted for any Cloud Foundry target that provides MySQL and object storage (_a la_ AWS's S3).

We'll also provide some guidance on what someone would need to do to reproduce this on their own codebase if they _don’t_ use this codebase as a starting point.

# Table of contents:
  * [Deploying Drupal to Cloud Foundry](#deploying-drupal-to-cloud-foundry)
    + [Install `cf`](#install--cf-)
    + [Clone a fresh copy of the repo](#clone-a-fresh-copy-of-the-repo)
    + [Log in and target the appropriate environment](#log-in-and-target-the-appropriate-environment)
    + [Send our new code to cloud.gov](#send-our-new-code-to-cloudgov)
  * [Notes on cloud.gov](#notes-on-cloudgov)
    + [Debugging](#debugging)
    + [Updating secrets](#updating-secrets)
  * [Developing and testing changes locally](#developing-and-testing-changes-locally)
    + [Bring up a local site instance to work with](#bring-up-a-local-site-instance-to-work-with)
    + [Making styling changes](#making-styling-changes)
    + [Helpful scripts](#helpful-scripts)
    + [Using the S3 storage from another environment locally](#using-the-s3-storage-from-another-environment-locally)
    + [Making configuration changes the DevOps way](#making-configuration-changes-the-devops-way)
    + [Making content changes the DevOps way](#making-content-changes-the-devops-way)
    + [Removing dependencies](#removing-dependencies)
    + [Upgrading dependencies (e.g. Drupal)](#upgrading-dependencies--eg-drupal-)
    + [Common errors](#common-errors)
      - [Edits to `web/sites/default/xxx` won't go away](#edits-to--web-sites-default-xxx--won-t-go-away)
    + [Start from scratch](#start-from-scratch)
  
## Deploying Drupal to Cloud Foundry

We prefer deploying code through a continuous integration system. This ensures
reproducibility and allows us to add additional safeguards. Regardless of
environment, however, our steps for deploying code are more or less the same:
1. Install the `cf` executable (this can be done once)
1. Clone a *fresh* copy of the repository (this must be done every time)
1. Log into cloud.gov and target the appropriate environment
1. Send our new code to cloud.gov for deployment

### Install `cf`

Follow the Cloud Foundry
[instructions](https://docs.cloudfoundry.org/cf-cli/install-go-cli.html) for
installing the `cf` executable. This command-line interface is our primary
mechanism for interacting with cloud.gov.

If performing a deployment manually (outside of CI), note that you'll only
need to install this executable once for use with all future deployments.

### Clone a fresh copy of the repo

In a continuous integration environment, we'll always check out a fresh copy
of the code base, but if deploying manually, it's import to make a new, clean
checkout of our repository to ensure we're not sending up additional files.
Notably, using `git status` to check for a clean environment is _not_ enough;
our `.gitignore` does not match the `.cfignore` so git's status output isn't a
guaranty that there are no additional files. If deploying manually, it makes
sense to create a new directory and perform the checkout within that
directory, to prevent conflicts with our local checkout.

```
git clone https://github.com/18F/cf-ex-drupal8.git
```

As we don't need the full repository history, we could instead use an
optimized version of that checkout:

```
git clone https://github.com/18F/cf-ex-drupal8.git --depth=1
```

We'll also want to **c**hange our **d**irectory to be inside the repository.

```
cd cf-ex-drupal8
```

### Log in and target the appropriate environment
Now we need to make sure we're logged into Cloud Foundry...

```
cf login -a api.fr.cloud.gov --sso
```

And check that we're pointing at the desired organization and space:

```
cf target
```

If you want to change the target organization and space, you can provide parameters to `target`:

```
cf target -o <ORGNAME> -s <SPACENAME>
```

### Send our new code to cloud.gov

We've included a simple script that deploys Drupal the first time.

```
./deploy-cloudgov.sh
```

The script creates the services you need (if they are not already created),
waits until the services are up, and then launches the app and tells you
what URL you should go to.

By default, it will use a cloud.gov shared mysql database.  If you wish
to do a deploy to a dedicated RDS mysql database, then you should run
`./deploy-cloudgov.sh prod`.

As a part of this process, some secrets are generated, like the initial
root password.  If you want, you can override this by saying:
`export ROOT_USER_PASS=yourReallyGr3atPassw0rd.` before running the
`deploy-cloudgov.sh` script.

## Notes on cloud.gov

Our preferred platform-as-a-service is [cloud.gov](https://cloud.gov/), due to
its
[FedRAMP-Authorization](https://cloud.gov/overview/security/fedramp-tracker/).
cloud.gov uses the open source [Cloud Foundry](https://www.cloudfoundry.org/)
platform, which is very similar to [Heroku](https://www.heroku.com/). See
cloud.gov's excellent [user docs](https://cloud.gov/docs/) to get acquainted
with the system.

### Debugging

We'll assume you're already logged into cloud.gov. From there,

```
cf apps
```
will give a broad overview of the current application instances. We expect two
"web" instances and one "cronish" worker in our environments, as described in
[our manifest file](manifest.yml).

```
cf app web
```
will give us more detail about the "web" instances, specifically CPU, disk,
and memory usage.

```
cf logs web
```
will let us attach to the emitted apache logs of our running "web" instances.
If we add the `--recent` flag, we'll instead get output from our *recent* log
history (and not see new logs as they come in). We can use these logs to debug
500 errors. Be sure to look at cloud.gov's [logging
docs](https://cloud.gov/docs/apps/logs/) (particularly, how to use Kibana) for
more control.

If necessary, we can also `ssh` into running instances. This should generally
be avoided, however, as all modifications will be lost on next deploy. See the
cloud.gov [docs on the topic](https://cloud.gov/docs/apps/using-ssh/) for more
detail -- be sure to read the step about setting up the ssh environment.

```
cf ssh web
```

While the database isn't generally accessible outside the app's network, we
can access it by setting up an SSH tunnel, as described in the
[cf-service-connect](https://github.com/18F/cf-service-connect#readme) plugin.
Note that the `web` and `cronish` instances don't have a `mysql` client (aside
from PHP's PDO); sshing into them likely won't help.

Of course, there are many more useful commands. Explore the cloud.gov [user
docs](https://cloud.gov/docs/) to learn about more.

### Updating secrets

As our secrets are stored in a cloud.gov "user-provided service", to add new
ones (or rotate existing secrets), we'll need to call the
`update-user-provided-service` command. It can't be updated incrementally,
however, so we'll need to set all of the secrets (including those that remain
the same) at once.

To grab the previous versions of these values, we can run

```
cf env web
```

and look in the results for the credentials of our "secrets" service (it'll be
part of the `VCAP_SERVICES` section). Then, we update our `secrets` service
like so:

```
cf update-user-provided-service secrets -p '{"SAMPLE_ACCOUNT":"Some Value", "SAMPLE_CLIENT":"Another value", ...}'
```

## Developing and testing changes locally
### Bring up a local site instance to work with
We'll use [Git](https://git-scm.com/) to pull down and manage our codebase.
There are [many](https://guides.github.com/introduction/git-handbook/)
[excellent](https://git-scm.com/book/en/v2/Getting-Started-Git-Basics)
[tutorials](http://git.huit.harvard.edu/guide/) for getting started with git,
so we'll defer to them here. We'll assume you have cloned our repository and
are now within it:

```
git clone https://github.com/18F/cf-ex-drupal8.git
cd cf-ex-drupal8
```

We use [Docker](https://www.docker.com/) to get a local environment running
quickly.
[Download](https://store.docker.com/search?type=edition&offering=community)
and install the runtime compatible with your system. Note that [Docker for
Windows](https://www.docker.com/docker-windows) requires Windows 10; use
[Docker Toolbox](https://docs.docker.com/toolbox/toolbox_install_windows/) on
older Windows environments. Docker will manage our PHP dependencies, get
Apache running, and generally allow us to run an instance of our application
locally. We'll be using the
[bash](https://www.gnu.org/software/bash/)-friendly scripts in `bin`, but they
wouldn't need to be modified substantially for Windows or other environments.

Our first step is to run

```
bin/composer install
```

This command will start by building a Docker image with the PHP modules we
need, unless the image already exists. It will then use
[Composer](https://getcomposer.org/) to install dependencies from our
`composer.lock` file. We can ignore the warning about running as root, as the
"root" in question is the root user _within_ the container. Should we need to
add dependencies in the future, we can use `bin/composer require` as described
in Composer's [docs](https://getcomposer.org/doc/03-cli.md#require).

Next, we can start our application:

```
docker-compose up
```

This will start up the database (MySQL) and then run our bootstrap script to
install Drupal. The initial installation and configuration import will take
several minutes, but we should see status updates in the terminal.

After we see a message about `apache2 -D FOREGROUND`, we're good to go.
Navigate to [http://localhost:8080/](http://localhost:8080) and log in as the
root user (username and password are both "root").

To stop the service, press `ctrl-c` in the terminal. The next time we start
it, we'll see a similar bootstrap process, but it should be significantly
faster.

As the service runs, we can directly modify the PHP files in our app and see
our changes in near-real time.

### Making styling changes

This codebase's theme is a subtheme of the [U.S. Web Design System](https://drupal.org/project/uswds) theme. Accordingly, its overrides are stored in `/web/themes/custom/your_uswds_subtheme`.

Our style changes are all within the context of the `your_uswds_subtheme` "theme", so we'll
start by getting there:

```
cd web/themes/custom/your_uswds_subtheme
```

If this is the first time we're editing a theme, we next need to install all
of the relevant node modules:

```
bin/npm install
```

Finally, we'll start our "watch" script:

```
bin/npm run build:watch
```

As long as that command is running, it'll watch every `.scss` file in the `sass/` folder for changes, compiling and saving CSS in the `assets/css/` folder every time you save a change to a `.scss` file.

Now, in a separate Terminal window and/or your favorite text editor, you can make changes to `web/themes/custom/your_uswds_subtheme/sass/uswds.scss` (or `_variables.scss`) and have your changes saved.

### Helpful scripts

Within the `bin` directory, there are a handful of helpful scripts to make
running `drupal`, `drush`, etc. within the context of our Dockerized app
easier. As noted above, they are written with bash in mind, but should be easy
to port to other environments.

### Using the S3 storage from another environment locally

Currently, even when running locally we need to simulate the S3
environment by adding its credentials to the `VCAP_SERVICES`
environment variable.

To find the values we're using in cloud.gov, use
```
cf env web
```

Then edit `docker-compose.yml` and insert
something similar to the following above "user-provided":

```json
"s3": [{
  "name": "storage",
  "credentials": {
   "access_key_id": "SECRET",
   "bucket": "SECRET",
   "region": "SECRET",
   "secret_access_key": "SECRET"
  }
}],
```

As with other edits to the local secrets, extra care should be taken when
exporting your config, lest those configuration files contain the true secret
values rather than dummy "SECRET" strings.

### Making configuration changes the DevOps way

Making configuration changes to the application comes in roughly eight small steps:
1. get the latest code
1. create a feature branch
1. make any dependency changes
1. edit the Drupal admin
1. export the configuration
1. commit the changes
1. push your branch to GitHub
1. create a pull request to be reviewed

To get the latest code, we can `fetch` it from GitHub.

```
git fetch origin
git checkout origin/master
```

Alternatively:

```
git checkout master
git pull origin master
```

We then create a "feature" branch, meaning a branch of development that's
focused on adding a single feature. We'll need to name the branch something
unique, likely related to the task we're working on (perhaps including an
issue number, for example).

```
git checkout -b 333-add-the-whatsit
```

If we are installing a new module or otherwise updating our dependencies, we
next use composer. For example:

```
bin/composer require drupal/some-new-module
```

See the "Removing dependencies" section below for notes on that topic; it's a
bit different than installation/updates.

If we're making admin changes (including enabling any newly installed
modules), we'll need to start our app locally.

```sh
docker-compose down # stop any running instance
docker-compose up # start a new one with our code
```

Then navigate to [http://localhost:8080](http://localhost:8080) and log in as
the root/root. Modify whatever settings desired, which will modify them in
your local database. We'll next need to export those configurations to the
file system:

```
bin/drupal config:export
```

We're almost done! We next need to review all of the changes and commit those
that are relevant. Your git tool will have a diff viewer, but if you're using
the command line, try

```
git add -p
```

to interactively select changes to stage for the commit. Once the changes are
staged, commit them, e.g. with

```
git commit -v
```

Be sure to add a descriptive commit message. Now we can send the changes to
GitHub:

```
git push origin 333-add-the-whatsit
```

And request a review in GitHub's interface.

### Making content changes the DevOps way

We'll also treat some pieces of content similar to configuration -- we want to
deploy it with the code base rather than add/modify it in individual
environments. The steps for this are very similar to the workflow for configuration:

1. get the latest code
1. create a feature branch
1. add/edit content in the Drupal admin
1. export the content
1. commit the changes
1. push your branch to GitHub
1. create a pull request to be reviewed

The first two steps are identical to the Config workflow, so we'll skip to the
third. Start the application:

```
docker-compose up
```

Then [log in](http://localhost:8080/user/login) as root (password: root).
Create or edit content (e.g. Aggregator feeds, pages, etc.) through the Drupal
admin.

Next, we'll export this content via Drush:

```sh
# Export all entities of a particular type
bin/drush default-content-deploy:export [type-of-entity e.g. aggregator_feed]
# Export individual entities
bin/drush default-content-deploy:export [type-of-entity] --entity-id=[ids e.g. 1,3,7]
```

Then, we'll review all of the changes and commit those that are relevant.
Notably, we're expecting new or modified files in `web/sites/default/content`.
After committing, we'll sent to GitHub and create a pull request as with
config changes.

### Removing dependencies

As we add modules to our site, they're rolled out via configuration
synchronization. This'll run the installation of new modules, including
setting up database tables. Unfortunately, removing modules isn't as simple as
deleting the PHP lib and deactivating the plugin. Modules and themes need to
be fully uninstalled, which will remove their content from the database and
perform other sorts of cleanup. Unfortunately, to do that, we need to have the
PHP lib around to run the cleanup.

Our solution is to have a step in our bootstrap script which uninstalls
modules/themes prior to configuration import. To do this, we'll need to keep
the PHP libs around so that the uninstallation hooks can be called. After
we're confident that the library is uninstalled in all our environments, we
can also remove it from the composer dependencies.

See the `module:uninstall` and `theme:uninstall` steps of the bootstrap script
to see how this is implemented.

### Upgrading dependencies (e.g. Drupal)

Updating dependencies through Composer is simple, though somewhat slow. First,
we should spin down our local install:

```
docker-compose down
```

Then, we run the
[`update`](https://getcomposer.org/doc/01-basic-usage.md#updating-dependencies-to-their-latest-versions)
command:

```
bin/composer update [name-of-package, e.g. drupal/core]
```

After crunching away a while, you should see (e.g. via `git status`) that the
`composer.lock` file has changed. Note that this command *doesn't* modify
`composer.json` -- it will only update the package in a way that's
[compatible](https://semver.org/). If you need to upgrade a major version
(i.e. a backward-incompatible release), use the
[`require`](https://getcomposer.org/doc/03-cli.md#require) command, e.g.

```
bin/composer require drupal/core:9.*
```

After installing the update, we should spin up our local instance

```
docker-compose up
```

and browse around [http://localhost:8080/](http://localhost:8080/) to make
sure nothing's obviously broken. We shouldn't expect to see anything amiss if
we've just `update`d, but need to be more careful around major version
changes.

We should then proceed with steps five through eight (exporting the config,
committing, sending to GitHub, etc.). Even though we haven't actively modified
any of the configurations, the updated libraries may have generated new ones
which would be good to capture.

### Common errors

#### Edits to `web/sites/default/xxx` won't go away
Drupal's installation changes the directory permissions for
`web/sites/default`, which can prevent git from modifying these files. As
we're working locally, those permissions restrictions aren't incredibly
important. We can revert them by granting ourselves "write" access again. In
unix environments, we can run

```
chmod u+w web/sites/default
```

### Start from scratch

As Docker is managing our environment, it's relatively easy to blow away our
database and start from scratch.

```
docker-compose down -v
```

Generally, `down` spins down the running environment but doesn't delete any
data. The `-v` flag, however, tells Docker to delete our data "volumes",
clearing away all the database files.

