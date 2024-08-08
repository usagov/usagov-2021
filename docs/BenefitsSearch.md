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

Content editors can categorize existing basic pages with page type of "Standard Page", set the weight of a page to control where in the rankings a single page shows (higher numbers show up first) and can manage the category terms available. Terms in the Benefits Category vocabulary can be related to one or more Life Events, which are shown at the top of search results which include that category.

#### Pages

* **Basic Page at _TBD_**: This is the search page for English results. It should be a basic node with a "Page Type" of "Benefits Category Search"
* **Basic Page at /es/_TBD_**: This is the search page for Spanish results. It should be a basic node with a "Page Type" of "Benefits Category Search"

#### Views

* **Benefit Search Results** Data export that lists all the Standard Pages that have been tagged to one or more benefit categories. It uses an argument to filter by language and, at the moment, makes an English and a Spanish JSON file available. (Machine name: `benefit_search_results `)
* **Benefit Categories with Life Events View** Data export that categories that reference one or more life events. It uses an argument to filter by language and, at the moment, makes an English and a Spanish JSON file available. (Machine name: `benefit_categories_with_life_events`)
* **Benefit Category Life Event Reference View** Controls the order of life events on basic page edit form so that they are grouped by language first. (Machine name: `benefit_category_life_event_reference`)
* **Benefit Search Category Reference View** Controls the order of benefit categories on basic page edit form. Groups them by language first, then sorts alphabetically. (Machine name: `benefit_search_category_reference`)
* **Benefit Search Form View** Used to build the English and Spanish search forms for filtering benefits. Only displays categories that have been tagged in a basic page node. (Machine name: `benefit_search_form`)
* **Benefit Search Results View** Data export providing JSON files that list basic pages tagged with one or more benefit category. (Machine name: `benefit_search_results`)
* **Benefit Pages admin** This view provides a table where content admins can see, search, and filter pages that have been tagged to one or more benefit categories. It shows both published and unpublished pages. (Machine name: `benefit_pages_admin`)

#### Fields and Conditional Field Groups

We use te Conditional Fields and Field Group module to customize the node add/edit forms content editors see for Basic Pages. With the module, we can show different field groups and fields so that editors can change text and labels on the homepage call-out, elements on the government benefits pages, and to categorize Standard Pages. These rules are configured in the Admin UI, in the **Manage Dependencies** tab for a content type. (`/admin/structure/types/manage/basic_page/conditionals`)

If a user is editing the homepage, they will see the following. These fields control the call-out shown on the homepage. This rule is when the page type is _Home Page_.

1. The **Homepage Benefits call-out** field group with the fields listed here. These fields do not display on other forms.
2. _Homepage Benefits Title_ Translatable text field for setting the heading text of the homepage benefits call-out. (Machine name: `field_homepage_benefits_title`)
3. _Homepage Benefits Description_ Translatable text field for the call-to-action text of the homepage benefits call-out. (Machine name: `field_homepage_benefits_descr`)
4. _Benefits Landing Page_ Entity reference to select the page the call-out button will link to. (Machine name: `field_homepage_benefits_ref`)
5. _Homepage Benefits Button_ Translatable text field for the button label in the call-out. (Machine name: `field_homepage_benefits_button`)

If a user is editing the navigation cards pages for government benefits, they will see the group. These fields control the call-out displayed on this page. This rule is based on the path aliases of the pages: `/benefits` and `/beneficios-gobierno`. Note that the `es/` prefix is omitted from the Spanish page path.

1. The **Benefits call-out** field group with the fields listed here. These fields do not display on other forms.
2. _Benefits call-out Description_ Text field for the call-to-action of the call-out. (Machine name: `field_benefits_callout_descr`)
3. _Benefits call-out Page_ Entity reference to select the page the call-out button will link to. (Machine name: `field_benefits_callout_ref`)
4. _Benefits call-out Button_ Translatable text field for the button label in the call-out. (Machine name: `field_benefits_call-out_button`)

If a user is editing one of the benefits search pages, they will see the following. This rule is when the page type is _Benefits Category Search_. These fields affect elements of the Basic Search sections.

1. The **Benefits Search Page** field group with the fields listed here. These fields do not display on other forms.
2. _Life Events Title_ Text field for the heading shown at the top of the life events grid. (Machine name: `field_benefits_life_events_title`)
3. _Life Events Description_ Text field for the description shown above the life events grid. (Machine name: `field_benefits_life_events_descr`)
3. _Life Events ID_ Text field for the anchor ID for linking directly to the life events grid. (Machine name: `field_benefits_life_events_id`)
4. _Category Search Title_ Text field for the heading shown at the top of the search form. (Machine name: `field_benefits_search_title`)
5. _Category Search Description_ Text field for the text instructions shown at the top of the search form. (Machine name: `field_benefits_search_descr`)
6. _Category Search ID_ Text field for the anchor ID for linking directly to the search form. (Machine name: `field_benefits_search_id`)

If a user is editing a Standard Page, they will see the following. This rule is when the page type is _Standard Page_. These fields are for categorizing pages to show up in search results.

1. The **Benefit Category Search** field group with the fields listed here. These fields do not display on other forms.
2. _Benefits Category_ A fieldset of checkboxes for terms in the Benefit Category vocabulary. (Machine name: `field_benefits_category`)
3. _Benefit Search Weight_ A numeric input that allows content editors to rank some search results higher in search results. (Machine name: `field_search_weight`)

### "USAGov Benefit Category Search" Custom Drupal Module

Files in `web/modules/custom/usagov_benefit_category_search`. At the moment, this module provides two deploy hooks:

* A hook function that adds the "Benefits Category Search" term to the Page Types vocabulary.
* A hook function to populate the Benefits Category vocabulary with English and Spanish terms.

> You are unlikely to need to run these hooks as they've already been executed on prod. The items they create should already exist in the database.

#### Data Export Paths

The **Benefit Search Results** and **Benefit Categories with Life Events View** use arguments (confusingly called "context" in the Views UI) to filter the items shown by language (langcode). These arguments must appear as "subdirectories" of the view path (`_data/benefits-search/en/pages.json`) and cannot appear as part of the resource/filename (`/_data/benefits-search/pages-en.json`).

While this allows us to have a single view for English and Spanish results, Tome has no information about what and how many valid arguments to use with the view and by default skips these views in an export. The `usagov_benefit_category_search` module provides a Tome event subscriber to add the paths the benefits search pages expect to function. If we add other languages, this event listener must be updated to include them. Currently, it exports these paths:

* `/_data/benefits-search/en/pages.json`
* `/_data//benefits-search/en/life-events.json`
* `/_data//benefits-search/es/pages.json`
* `/_data//benefits-search/es/life-events.json`

#### Customized Form Widgets and Validation

The terms, fields, and widgets used in the Basic Page node type and Benefit Category vocabulary are set up with core Drupal functionality. The entity reference fields can work in this default state but have one limitation that impacts the content editor experience and could lead to data entry issues. The two references are the Benefit Category checkboxes on the Basic Page node (Standard Page) and the Life Event references on the individual Category terms. The views that populate the lists are configured to show all items regardless of the language. This means that someone editing an English page would see both English and Spanish categories. Potentially, they could select a Spanish category on an English page and vice-versa. The same issue happens editing a Benefit Category term to pick related Life Events. Some guard rails are implemented to prevent selecting items from a language that doesn't match the source entity.

1. The `usagov_benefit_category_search` module adds a custom validator to node add/edit forms. When someone edits a Standard Page, it will show an error if they've selected any categories that are from a different language than the node they are editing.
2. The `usagov_benefit_category_search` module adds a custom validator to node add/edit forms. When someone edits a Benefits Category Search page, it checks that the Category Search ID and Life Events IDs contain only letters, numbers, and minus sign. It also checks that the two values are unique per page.
3The `usagov_benefit_category_search` module adds a custom validator to benefit category term add/edit forms. When someone edits a Benefit Category term, it will show an error if they've selected any life events that are from a different language than the node they are editing.
4. The `usagov_benefit_category_search` module adds JavaScript to node add/edit forms to hide Life Events that do not match the term's language. It also adds a button to display all Life Events(for debugging) and will not hide terms that are checked.
5. The `usagov_admin_styles` module adds JavaScript to node add/edit forms to hide category terms that do not match the page's language. It also adds a button to display all terms and will not hide terms that are checked.

### DataLayer

The `usagov_benefit_category_search` module adds an HTML prerender hook that can add two items to the datalayer sent to Google Tag Manager for analysis. It only adds thee for Standard Pages.

If a page is tagged, the datalayer will have two entries:

```
"hasBenefitCategory": true,
"benefitCategories": "Cash Assistance, Food",
```
If page is not tagged, the datalayer shows:

```
"hasBenefitCategory": false,
```

### JavaScript Sources

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

Upon first deployment against a new database snapshot:

1. Import configuration `bin/drush cim` to enable new views, fields for basic page content type.
2. Run `bin/drush cr` to clear drupal caches and twig template caches to pick up on config changes and file updates.
3. Run the tag pages script to tag existing pages to the categories, so you get some results when you search. This JSON file is in `scripts/drush/data`. The path should be absolute inside the docker container or relative to the working directory `/var/www/web`.

Outside of docker, use:

```
bin/drush php:script scripts/drush/benefits-category-tag-pages.php /var/www/scripts/drush/data/benefits-sample.2024-05-22.json
```

If you're in the container, in the `/var/www/` directory use a relative path like below or the absolute path used above:

```
drush php:script scripts/drush/benefits-category-tag-pages.php ../scripts/drush/data/benefits-sample.2024-05-22.json
```

4. Run the make pages script to make the english and spanish pages, relate them to each other, and add the call-outs to the homepage and government benefits pages.

```
bin/drush php:script scripts/drush/benefits-category-make-pages.php
```

5. Associate terms to life events. You need to edit at least one term in English and one Spanish term from the benefits category vocabulary to reference one of the pages via the "Life Events" field.

6. Enable the benefits search call-outs at `/admin/config/development/usagov_benefit_category_search`. Press the button to enable the call-outs. This will also hide the life events carousel on the homepage.

## Known Issues and Concerns

* The current search/filtering functionality sorts results by the weight that editors enter, or by the view's default sort otherwise. This may not make sense to user's or align with their expectations. We could look into using page traffic to order the results.
