# USAgov Benefit Finder module

* USAgov Benefit Finder app folder
  * USAgov bears block module
* USAgov bears content module
* USAgov bears API module

```
bin/drush pm:enable usagov_bears_block
bin/drush pm:enable usagov_bears_content
bin/drush pm:enable usagov_bears_api
bin/drush pm:enable usagov_bears
```

These enable the USAgov bears modules.

## USAgov bears block module

This module provides custom block "usagov bears block" with div id="usagov-bears-app" for React app.

## USAgov bears content module

config/optional folder include configuration of content type, taxonomy, paragraph...

```
bin/drush config:import \
  --partial \
  --source=modules/custom/usagov_bears/modules/usagov_bears_content/config/optional
```
This imports the configuration of content type, taxonomy, paragraph, custom entity.


config folder includes content type, taxonomy, paragraph, custom entity configuration.

```
bin/drush config:import \
  --partial \
  --source=modules/custom/usagov_bears/modules/usagov_bears_content/config
```
This imports the configuration of content type, taxonomy, paragraph, custom entity.

path: /bears/import-life-event

This imports Life Event content.

## USAgov bears API module

path: /bears/api/life-event/{name}

This outputs JSON data of given life event.

For example,

/bears/api/life-event/death of a loved one

/bears/api/life-event/retirement

/bears/api/life-event/disability

## Local Functional Testing

#### Set up local development site

Make sure that local development site setup and run at http://localhost

The functional testing uses the existing database of local development site.

#### Set up testing environment (Install testing software and set PHPUnit configuration)

```
$ bash scripts/local/setup-benefit-finder-test
```

#### Change to local development site directory

```
cd usagov-2021
```

#### Uninstall USAGov Login Customizations module

```
bin/drush pm:uninstall usagov_login
```

#### The system is ready for functional testing

#### The following is a functional testing example.

Start SSH session
```
bin/ssh
cd /var/www
```

Use following command to test Benefit Finder system
```
/var/www # ./vendor/bin/phpunit \
web/modules/custom/usagov_benefit_finder/tests/src/Functional/BenefitFinderTest.php \
--group usagov_benefit_finder \
--filter testAll
```

The test displays result.
```
PHPUnit 9.6.17 by Sebastian Bergmann and contributors.

.                                                                   1 / 1 (100%)

Time: 00:02.558, Memory: 30.00 MB

OK (1 test, 9 assertions)
```
