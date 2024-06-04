# Implementation of the Benefit Search Page

Currently under development.

## What it Does

Content managers can tag basic pages to one or more Benefits Categories. Pages that have been categorized can be filtered by users on a specialized English or Spanish page.

Content editors should also be able to add, edit, remove terms from the Benefits Categories vocabulary.

## Approach

The new specialized page makes use of two Drupal views and custom JavaScript to present the benefits search interface on the page. Published basic pages nodes tagged to one or more benefit categories are available for search.

### Drupal Structures

The following are accessible within the Drupal Admin UI:

* **Benefits Category** Taxonomy Vocabulary (Machine name: `benefits_category`)
* **Benefits Category** Field for Basic Pages (Machine name: `field_benefits_category`)
* **Search Weight** Field for Basic Pages (Machine name: `field_search_weight`)
* **Basic Page at _TBD_**: This is the search page for English results. It should be a basic node with a "Page Type" of "Benefits Category Search"
* **Basic Page at /es/_TBD_**: This is the search page for Spanish results. It should be a basic node with a "Page Type" of "Benefits Category Search"
* **Benefit Categories with Life Events View** Data export that provides two JSON files: one for English categories that reference one or more life events. (Machine name: `benefit_categories_with_life_events`)
* **Benefit Category Life Event Reference View** Controls the order of life events on basic page edit form so that they are grouped by language first. (Machine name: `benefit_category_life_event_reference`)
* **Benefit Search Category Reference View** Controles the order of benefit categories on basic page edit form. Groups them by language first, then sorts alphabetically. (Machine name: `benefit_search_category_reference`)
* **Benefit Search Form View** Used to build the English and Spanish search forms for filtering benefits. Only displays categories that have been tagged in a basic page node. (Machine name: `benefit_search_form`)
* **Benefit Search Results View** Data export providing JSON files that list basic pages tagged with one or more benefit category. (Machine name: `benefit_search_results`)

Content editors can categorize existing basic pages, set the weight of a page to control where in the rankings a single page shows (higher numbers show up first) and can manage the category terms available. Terms in the Benefits Category vocabulary can be related to one or more Life Events, which are shown at the top of search results which include that category.

### "USAGov Benefit Category Search" Custom Drupal Module

Files in `web/modules/custom/usagov_benefit_category_search`
. At the moment, this module provides two deploy hooks:

* A hook function that adds the "Benefits Category Search" term to the Page Types vocabulary.
* A hook function to populate the Benefits Category vocabulary with English and Spanish terms.

### JavaScript sources

Two JavaScript source files are required for the page to function properly.

* `usagov\scripts\benefitSearch.js`: Loads the corpus for searching and the category to life event data from two data export views. Then, it sets up the event handler to react to benefit search form submit events. This handler finds any matches, ranks them by the number of categories that match and customized search weight for each basic page, and prepends any matching life events. Then, it divides the search results into one or more pages to display to the user along with configuring a Pager component for navigating between pages.
* `usagov\scripts\pagination.js`: An independent script for rendering a USWDS pager component "from scratch." All it needs to know is how many pages are in a result set. It renders the initial page and then listens for clicks on pager elements. Clicks are routed to an external callback handler with one parameter - the page to display. This component's only responsibility is to update the presentation of the pager and notify an external function when a different page needs to be shown to the user.

### Twig Templates

* `views-view-list--benefit-search-form.html.twig`: Builds the form with checkboxes for categories and loads the JavaScript file that makes the whole thing work. Adds a div to display the results of a search. Checks if parent page language is English or Spanish and updates labels to match.
* `views-view-fields--benefit-search-form.html.twig` Styles an individual category as a USWDS checkbox input with the markup expected by `benefitSearch.js`.
* `node--basic_page.html.twig` This template was updated to embed the Benefit Search Form view if the page type is "Benefits Search" via `/templates/includes/benefits-search.html.twig`.

### CSS

`_benefits-search.scss` adds styles to theme the search page to match the approved design for mobile and desktop presentations. Where possible, USWDS utility classes were used instead of creating one-time use classes.

## Setup

Upon first deployment against a given database:

1. Import configuration `bin/drush cim` to enable new views, fields for basic page content type.
2. Run `bin/drush deploy:hook` to create the terms in the benefits category vocab.

## Known Issues and Concerns

* Despite having the module for the deploy hooks, all the functionality for the benefits search is part of the theme. Some code could probably be moved to the module to keep it together in one place.

