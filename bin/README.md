# USAgov 2021 Control Scripts

Controls are divided into four parts: local control (base of the bin directory), cloudgov control (cloudgov directory), deployment control (deploy directory), and snapshot controls (snapshot-backups directory).

Largely, the scripts intended for developer use are in the root directory.  Scripts intended for automation are found in the named directories.

Additional scripts are found in the scripts directory which are used by the container at runtime.

_All_ scripts are intended to be run from the project's root directory.

## The difference between bin/bootstrap and bin/drupal-update

Bootstrap is establishing or refreshing the configuration files for php, nginx, and new relic on all cms containers, as well as running Drupal updatedb, cim, and cr on cms container instance 0.

bin/drupal-update (re-)installs the Drupal modules, builds the theme, and then runs bin/bootstrap.
