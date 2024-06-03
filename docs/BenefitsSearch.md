# Implementation of the Federal Directory

Currently under development!

## What it Does

Content managers can

## Approach

We've tried to use "core Drupal" to build as much of this as possible.

### Drupal Structures

The following are accessible within the Drupal Admin UI:

* **Federal Directory Record** Content type (Machine name: `directory_record`)
* **Federal Agencies** View (Machine name: `federal_agencies`)
* **Federal Agencies A-Z** Block (Machine name: )
* **Indice Agencias A-Z** Block (Machine name: )
* **Block Layout** modified to include the Federal Agencies A-Z Block and the Indice Agencias A-Z Block in the content area when the displayed page matches the /agency-index path.
* **Basic Page at /agency-index path**: This is the "home" for a Block provided by the Federal Agencies View. It provides the page title and menu configuration (for the left sidebar).
* **Basic Page at /es/indice-agencias path**: This is the "home" for the Spanish Block provided by the Federal Agencies View. It provides the page title and menu configuration (for the left sidebar).
* **Agency Synonym** Content type (Machine name: `agency_synonym`)

Content editors will be able to create and manage content of type **Federal Directory Record** and "Agency Synonym" in the same way as they do other content. They are not expected to modify the Federal Agencies View or the block layout. It will be possible to modify the intro or body of the basic page at /agency-index, although no such need is anticipated.

### "USAGov Directories" Custom Drupal Module

Files in `web/modules/custom/usagov_directories`. This module provides two kinds of functionality:

* Convert URLs with query parameters like `?letter=a` to path parts like `/a` when Tome is used to generate the static site pages.
* Admin forms to support parts of the import process. (We'll be able to remove these eventually.)

Refer to the files in `web/modules/custom/usagov_directories/docs` for more details.

### Twig Templates

**Bolded** items are doing something that might be "interesting."

* **views-view-summary.html.twig:** Overrides link generation for the letters (producing "/agency-index?letter=b", for example). Plus a bunch of specific layout, including the search box. Checks if row language is set to English or Spanish to provide correct links.
* views-view-list.html.twig: Overrides the layout of the part of the view that shows a heading and list. Mostly it's just adding the H2 heading and emitting the content without the unordered-list output the standard viewws get. Checks if block language is set to English or Spanish to provide correct links.
* views-view-fields.html.twig: Lays out the fields for an individual directory record within the view. (A subset of the directory record content is displayed within an accordion design element.) Checks if row language is set to English or Spanish to provide correct headers.
* field--node--directory-record.html.twig: Use <span> instead of <div> for fields; use unordered lists for fields that have lists with multiple entries (for example, a list of links with more than one item).

### Spanish Search Page

### CSS

There is some added CSS (SASS). Perhaps someone who worked on that would like to add notes! It should be in harmony with how CSS is done elsewhere on the site.

## Setup

Upon merge or first deployment against a given database:

1. Ensure the **USAGov Directories** module (a.k.a. `usagov_directories`) is enabled.
1. Sync Configuration -- this will bring in the Federal Directory Record content type, Federal Agencies view, and Block Layout.
1. **Manual step:** add a standard page with the following settings, and Publish it:
  * **Title:** Directory of U.S. Government Agencies and Departments
  * **Language:** English
  * **URL alias:** /agency-index
  * **Promotion options:** Not promoted
  * **Menu settings:**
    - **Provide a menu link:** Checked
    - **Menu link title:** Directory of U.S. Government Agencies and Departments
    - **Parent item:** -- About the U.S. and Its
    - **Weight:** 0
1. Flush all the caches, of course.

## Known Issues and Concerns


## Rejected Approaches



