# "USAGov Directories" custom Drupal module

This module provides support for the Federal Directory.

## Static site generation modification

We need to convert query parameters like `?letter=a` to path parts like `/a` when Tome is used to generate the static site pages. This makes static site generation work for the agency-index pages.

This module adds an event subscriber that listens for two `tome_static` events and rewrites URLs. The code is based on a class Tome already includes that rewrites `page=2` query parameters for views that use that convention. (The Tome version checks both for the `page` parameter and for an integer value. The usagov_directories version looks for a `letter` parameter but doesn't validate the values further. It might make sense to look for a single character.)

## Synonym support

For the synonyms to show their “parent” agency a pre-rendering hook was added to the `usagov_directories.module file`. This hook grabs the result array of the federal agencies view and for every result that is a synonym creates a shallow copy its parent agency. The view displays the synonym title and the information from the copy of the parent agency. A shallow copy was created because creating a reference to the parent agency in the result array instead did not allow for the synonym title to be shown in the view. 

## Tools for importing records from "mothership"

To import records, we use the Drupal "Feeds" module, plus several utilities and forms hosted in this module. Refer to [Importing_Federal_Agency_Records](Importing_Federal_Agency_Records.md) and [Importing_State_Records](Importing_State_Records.md) for details.

