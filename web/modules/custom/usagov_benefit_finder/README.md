# USAgov Benefit Finder module

* USAgov bears app folder
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
