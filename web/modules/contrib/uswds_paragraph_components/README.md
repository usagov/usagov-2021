# USWDS Paragraph Components

CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers


INTRODUCTION
------------

This suite of [Paragraphs](https://www.drupal.org/project/paragraphs) bundles
works within the [USWDS](https://designsystem.digital.gov/) framework.

* For a full description of the module, visit the project page:
  https://drupal.org/project/uswds_paragraph_components
  or
  https://www.drupal.org/docs/8/modules/uswds-paragraph-components

* To submit bug reports and feature suggestions, or to track changes:
  https://drupal.org/project/issues/uswds_paragraph_components

**Bundle Types:**

* [USWDS Accordion](https://designsystem.digital.gov/components/accordion/)
* [USWDS Card Group (Flag)](https://designsystem.digital.gov/components/card/)
* [USWDS Card Group (Regular)](https://designsystem.digital.gov/components/card/)
* [USWDS Alert](https://designsystem.digital.gov/components/alert/)
* [USWDS Summary Box](https://designsystem.digital.gov/components/summary-box/)
* [USWDS Process List](https://designsystem.digital.gov/components/process-list/)
* [Layout Grid](https://designsystem.digital.gov/utilities/layout-grid/)
  * USWDS Column (Equal Size) (Up to 4 columns)
  * USWDS Column (2 Uneven).  With the following options
    * 4:8
    * 8:4
    * 3:9
    * 9:3
* [USWDS Step Indicator](https://designsystem.digital.gov/components/step-indicator/)

REQUIREMENTS
------------

This module requires the following modules outside of Drupal core:

* [Entity Reference Revisions](https://www.drupal.org/project/entity_reference_revisions)
* [Paragraphs](https://www.drupal.org/project/paragraphs)
* [Views Reference Field](https://www.drupal.org/project/viewsreference)
* [Minimum Multiple Fields [Core Fields]](https://www.drupal.org/project/mmf_core_fields) - Used to enforce column limit.
* USWDS framework's CSS and JS included in your theme. https://designsystem.digital.gov/

Recommended Modules/Themes
------------

* [USWDS - United States Web Design System Base](https://www.drupal.org/project/uswds_base)

INSTALLATION
------------

* Install as you would normally install a contributed Drupal module. Visit:
  https://www.drupal.org/node/1897420 for further information.
* Verify installation by visiting /admin/structure/paragraphs_type and seeing
  your new Paragraph bundles.


CONFIGURATION
-------------

* Go to your content type and add a new field of type Entity revisions,
  Paragraphs.
* Allow unlimited so creators can add more than one Paragraph to each node.
* On the field edit screen, you can add instructions, and choose which
  bundles you want to allow for this field.
* Don't select the following as they are used by
  other bundles.
  * USWDS Cards (Flag)
  * USWDS Cards (Regular)
  * USWDS Accordion
  * USWDS Process Item
  * USWDS Step Indicator Item

MAINTAINERS
-----------

Current maintainers:
* [smustgrave](https://www.drupal.org/u/smustgrave)
