# Implementation of the Benefit Search Page

Currently under development.

## What it Does

Content managers can tag basic pages to one or more Benefits Categories. Pages that have been categorized can be filtered by users on a specialized English or Spanish page.

Content editors should also be able to add, edit, remove terms from the Benefits Categories vocabulary.

## Approach

The new specialized page makes use of two Drupal views and custom JavaScript to present the benefits search interface on the page. Published basic pages nodes tagged to one or more benefit categories are available for search.

### Drupal Structures

The following are accessible within the Drupal Admin UI:

#### Entities

* **Benefits Category** Taxonomy Vocabulary (Machine name: `benefits_category`)
* Adds a "Benefits Search Page" page type for Basic Pages.

#### Fields

* **Benefits Category** Field for Basic Pages (Machine name: `field_benefits_category`)
* **Search Weight** Field for Basic Pages (Machine name: `field_search_weight`)
* **Homepage Benefits Title** Field for the heading off the benefits call out on the homepage. (Machine name: `field_homepage_benefits_title`)
* **Homepage Benefits Description** Field for the description/call-to-action in the benefits call out on the homepage. (Machine name: `field_homepage_benefits_descr`)
* **Benefits Landing Page** Reference to the page that the call out on the home page links to. (Machine name: `field_homepage_benefits_ref`)
* **Homepage Benefits Button** Field to set the text of the button in the call out on the home page. (Machine name: `field_homepage_benefits_button`)
* **Benefits Callout Description**  Field for the call-to-action text in the benefits call out on the Benefits navigation card page. (Machine name: `field_benefits_callout_descr`)
* **Benefits Callout Page** Reference to the page that the benefits call out on the Benefits navigation cards page links to. (Machine name: `field_benefits_callout_ref`)
* **Benefits Callout Button** Field to set the text of the button in the call out on the Benefits navigation cards page.  (Machine name: `field_benefits_callout_button`)

Content editors can categorize existing basic pages with page type of "Standard Page", set the weight of a page to control where in the rankings a single page shows (higher numbers show up first) and can manage the category terms available. Terms in the Benefits Category vocabulary can be related to one or more Life Events, which are shown at the top of search results which include that category.

#### Pages

* **Basic Page at _TBD_**: This is the search page for English results. It should be a basic node with a "Page Type" of "Benefits Category Search"
* **Basic Page at /es/_TBD_**: This is the search page for Spanish results. It should be a basic node with a "Page Type" of "Benefits Category Search"

#### Views

* **Benefit Search Results** Data export that lists all the Standard Pages that have been tagged to one or more benefit categories. It uses a argument to filter by language and, at the moment, makes an English and a Spanish JSON file available. (Machine name: `benefit_search_results `)
* **Benefit Categories with Life Events View** Data export that categories that reference one or more life events. It uses a argument to filter by language and, at the moment, makes an English and a Spanish JSON file available. (Machine name: `benefit_categories_with_life_events`)
* **Benefit Category Life Event Reference View** Controls the order of life events on basic page edit form so that they are grouped by language first. (Machine name: `benefit_category_life_event_reference`)
* **Benefit Search Category Reference View** Controls the order of benefit categories on basic page edit form. Groups them by language first, then sorts alphabetically. (Machine name: `benefit_search_category_reference`)
* **Benefit Search Form View** Used to build the English and Spanish search forms for filtering benefits. Only displays categories that have been tagged in a basic page node. (Machine name: `benefit_search_form`)
* **Benefit Search Results View** Data export providing JSON files that list basic pages tagged with one or more benefit category. (Machine name: `benefit_search_results`)

#### Conditional Fields and Field Group

We use te Conditional Fields and Field Group module to customize the node add/edit forms content editors see for Basic Pages. With the module, we can show different field groups and fields so that editors can change text and labels on the homepage callout, elements on the government benefits pages, and to categorize Standard Pages. These rules are configured in the Admin UI, in the **Manage Dependencies** tab for a content type. (`/admin/structure/types/manage/basic_page/conditionals`)

If a user is editing the homepage, they will see the following. These fields control the callout shown on the homepage. This rule is when the page type is _Home Page_.

1. The **Homepage Benefits Callout** field group with the fields listed here. These fields do not display on other forms.
2. _Homepage Benefits Title_ Translatable text field for setting the heading text of the homepage benefits callout.
3. _Homepage Benefits Description_ Translatable text field for the call-to-action text of the homepage benefits callout.
4. _Benefits Landing Page_ Entity reference to select the page the callout button will link to.
5. _Homepage Benefits Button_ Translatable text field for the button label in the callout.

If a user is editing the navigation cards pages for government benefits, they will see the group. These fields control the callout displayed on this page. This rule is based on the path aliases of the pages: `/benefits` and `/beneficios-gobierno`. Note that the `es/` prefix is omitted from the Spanish page path.

1. The **Benefits Callout** field group with the fields listed here. These fields do not display on other forms.
2. _Benefits Callout Description_ Text field for the call-to-action of the callout.
3. _Benefits Callout Page_ Entity reference to select the page the callout button will link to.
5. _Benefits Callout Button_ Translatable text field for the button label in the callout.

If a user is editing one of the benefits search pages, they will see the following. This rule is when the page type is _Benefits Category Search_. These fields affect elements of the Basic Search sections.

1. The **Benefits Search Page** field group with the fields listed here. These fields do not display on other forms.
2. _Life Events Title_ Text field for the heading shown at the top of the life events grid.
3. _Life Events Description_ Text field for the description shown above the life events grid.
4. _Category Search Title_ Text field for the heading shown at the top of the search form.
5. _Category Search Description_ Text field for the text instructions shown at the top of the search form.

If a user is editing a Standard Page, they will see the following. This rule is when the page type is _Standard Page_. These fields are for categorizing pages to show up in search results.

1. The **Benefit Category Search** field group with the fields listed here. These fields do not display on other forms.
2. _Benefits Category_ A fieldset of checkboxes for terms in the Benefit Category vocabulary.
3. _Benefit Search Weight_ A numeric input that allows content editors to rank some search results higher in search results.




### "USAGov Benefit Category Search" Custom Drupal Module

Files in `web/modules/custom/usagov_benefit_category_search`. At the moment, this module provides two deploy hooks:

* A hook function that adds the "Benefits Category Search" term to the Page Types vocabulary.
* A hook function to populate the Benefits Category vocabulary with English and Spanish terms.

> You are unlikely to need to run these hooks as they've already been executed on prod. The items they create should already exist in the database.

#### Data export paths


#### Customized Form Widgets and Validation


### DataLayer

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

Upon first deployment against a new database snaphot:

1. Import configuration `bin/drush cim` to enable new views, fields for basic page content type.
2. Run `bin/drush cr` to clear drupal caches and twig template caches so it picks up on config changes and file updates.
3. Run the tag pages script to tag existing pages to the categories, so you get some results when you search. This JSON file is in `scripts/drush/data`. The path should be absolute inside the docker container or relative to the working directory `/var/www/web`.

Outside of docker, use:

```
bin/drush php:script scripts/drush/benefits-category-tag-pages.php /var/www/scripts/drush/data/benefits-sample.2024-05-22.json
```

If you're in the container, in the `/var/www/` directory use a relative path like below or the absolute path used above:

```
drush php:script scripts/drush/benefits-category-tag-pages.php ../scripts/drush/data/benefits-sample.2024-05-22.json
```

4. Run the make pages script to make the english and spanish pages, relate them to each other, and add the call outs to the homepage and government benefits pages.

```
bin/drush php:script scripts/drush/benefits-category-make-pages.php
```

5. Associate terms to life events. You need to edit at least one term in English and one Spanish term from the benefits category vocabulary to reference one of the pages via the "Life Events" field.

6. Enable the benefits search callouts at `/admin/config/development/usagov_benefit_category_search`. Tick the box to display the callouts and press the save button.

## Known Issues and Concerns

* Despite having the module for the deploy hooks, all the functionality for the benefits search is part of the theme. Some code could probably be moved to the module to keep it together in one place.
