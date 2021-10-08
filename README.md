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

Daily Setup
```
docker compose up
```

# USAgovTheme
The USAgov theme is a subtheme of the USWDS_base theme.

This theme adds `USWDS_CKEditor_Custom_Styles.scss` into the CKeditor frame.

## Export Database


## If cms password is not accepted:
* run `bin/drush uli`
* copy the path of the url onto localhost in your browser's URL bar
* follow the prompts to reset the password

`Dockerfile-node` runs the gulp start command.


## More info on Cloud Foundry & Cloud.gov

This repository was loosly based off of Cloud.gov's [cf-ex-drupal8 repo](https://github.com/cloud-gov/cf-ex-drupal8). Their README may provide other useful info.