CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Features
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

With the USWDS library (https://designsystem.digital.gov/) becoming
a requirement for government websites thought it would be useful to
have some integration with the ckeditor. The primary goal is to make
it easy for a user to utilize and inject USWDS classes and components
directly into the ckeditor without opening up the source

* For a full description of this module, visit the project page:
See https://www.drupal.org/project/uswds_ckeditor_integration

* To submit bug reports and feature suggestions, or track changes:
See https://www.drupal.org/project/issues/uswds_ckeditor_integration


FEATURES
--------

* Tables: The user can add tables just as they normally would but
in the properties tab there are options to add uswd table features.
Some classes are auto added.
See https://designsystem.digital.gov/components/table/

* Accordions: This module includes an accordion
button that has to be placed into the editor first. Then clicking on
it the user can add accordions with uswds classes auto added.
See https://designsystem.digital.gov/components/accordion/

* Grid Layout: This module includes a grid template button that has to be placed
into the ckeditor. See https://designsystem.digital.gov/utilities/layout-grid/


* Summary Box: Using ckeditor templates inject the markup for USWDS Summary Box into the ckeditor.
See https://designsystem.digital.gov/components/summary-box/


REQUIREMENTS
------------

This module requires:

* PHP > 7.2
* USWDS Library (Recommend following steps for
see https://www.drupal.org/project/uswds_base)
* For Ckeditor Templates to work you will need to include the templates
plugin. See Drupal page for how to install.


INSTALLATION
------------

Install as you would normally install a contributed Drupal module. Visit:
https://www.drupal.org/docs/extending-drupal/installing-drupal-modules
for further information.


CONFIGURATION
-------------

Each component requires specific configuration

* Table
  1. Go to the text profile you wish to include USWDS table.
  2. Under "CKEditor plugin settings" make sure "Override table plugin with USWDS"
  is checked. By default, it will be.
  3. If you want to use USWDS table stacked click "USWDS Stacked Table Attributes"
  filter

* Accordion
  1. Go to the text profile you wish to include USWDS accordions.
  2. Move the Accordion button into the toolbar.

* Grid
  1. Go to the text profile you wish to include USWDS grid templates.
  2. Move the grid button into the toolbar.

* Summary Box
  1. Go to the text profile you wish to include ckeditor templates
  2. Move the templates button into the toolbar.

MAINTAINERS
-----------

Current maintainers:
* Stephen Mustgrave (smustgrave) (https://www.drupal.org/u/smustgrave)
