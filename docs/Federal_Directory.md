# Implementation of the Federal Directory

Currently under development!

The Federal Directory is a glossary-style index, which will appear at the path /agency-index.

## What it Does

Content managers can add individual directory records, which contain key information for a specific organization (federal agency). These are served in a glossary-style listing. Individual entries can be displayed as stand-alone pages.

## Approach

We've tried to use "core Drupal" to build as much of this as possible.

### Drupal Structures

The following are accessbile within the Drupal Admin UI:

* **Federal Directory Record** Content type (Machine name: `directory_record`)
* **Federal Agencies** View (Machine name: `federal_agencies`)
* **Federal Agencies A-Z** Block (Machine name: )
* **Indice Agencias A-Z** Block (Machine name: )
* **Block Layout** modified to include the Federal Agencies A-Z Block and the Indice Agencias A-Z Block in the content area when the displayed page matches the /agency-index path.
* **Basic Page at /agency-index path**: This is the "home" for a Block provided by the Federal Agencies View. It provides the page title and menu configuration (for the left sidebar).
* **Basic Page at /es/indice-agencias path**: This is the "home" for the Spanish Block provided by the Federal Agencies View. It provides the page title and menu configuration (for the left sidebar).
* **Agency Synonym** Content type (Machine name: `agency_synonym`)

Content editors will be able to create and manage content of type **Federal Directory Record** and "Agency Synonym" in the same way as they do other content. They are not expected to modify the Federal Agencies View or the block layout. It will be possible to modify the intro or body of the basic page at /agency-index, although no such need is anticipated.

The synonyms are their own content type **Agency Synonym**.  Agency Synonym has one field, **Agency Reference**, which is a content entity reference( to a **Federal Directory Record**) type and limited to 1. For example the synonym *GSA* would contain one agency refernce to the Federal Directory Record *U.S. General Services Administration*.

The `directory_record` content type,`agency_synonym` content type, `federal_agencies` view, and Block Layout all produce artifacts (YAML) that are checked in to this repo.

The basic page at /agency-index is constructed manually via the Drupal admin UI.

#### More on the Federal Agencies View setup

The `federal_agencies` view uses a contextual filter on the query parameter "letter" to get the current letter to display (defaulting to "a" if none is supplied). This format (as opposed to using a path part like "/a") plays nicely with Drupal's routing, which by default sees /agency-index and /agency-index/a as requests for different nodes. The path-part syntax would have worked well if we used the "page" display of the view, but that did not play well with the rest of our existing layout.

The final form of this view has `Page`, `Block`, and `Alpha List` displays. At the /agency-index path, we call for  the `Block` display, which brings along `Alpha List` (the list of linked letters) as an attachment. DO NOT DELETE the `Page` Display, though -- without it, Drupal hits an error while rendering the `Alpha List`.

#### Agency Synonyms in Federal Agencies View
The synonyms are shown on the Federal Agencies View along with the Federal Directory Records. To show them in the view a filter criteria of “content type = agency synonym” was added to a new filter group. This filter group is separate from the filter group for federal directory records with an OR clause.

### "USAGov Directories" Custom Drupal Module

Files in `web/modules/custom/usagov_directories`. This module provides two kinds of functionality:

* Convert URLs with query parameters like `?letter=a` to path parts like `/a` when Tome is used to generate the static site pages.
* Admin forms to support parts of the import process. (We'll be able to remove these eventually.)

Refer to the files in `web/modules/custom/usagov_directories/docs` for more details.

### Twig Templates

**Bolded** items are doing something that might be "interesting."

For the A-Z view: We've added twig templates to override the default layout for a view. This is accomplished using conditional clauses within the usagov twig templates -- "if we're displaying the federal_agencies view, do this layout, otherwise do the default thing." In addition to layout, we're overriding how links are built for the glossary letters; by default we would get links to the bare view, not back to the /agency-index page.

* **views-view-summary.html.twig:** Overrides link generation for the letters (producing "/agency-index?letter=b", for example). Plus a bunch of specific layout, including the search box. Checks if row language is set to English or Spanish to provide correct links.
* views-view-list.html.twig: Overrides the layout of the part of the view that shows a heading and list. Mostly it's just adding the H2 heading and emitting the content without the unordered-list output the standard viewws get. Checks if block language is set to English or Spanish to provide correct links.
* views-view-fields.html.twig: Lays out the fields for an individual directory record within the view. (A subset of the directory record content is displayed within an accordion design element.) Checks if row language is set to English or Spanish to provide correct headers.

* field--node--directory-record.html.twig: Use <span> instead of <div> for fields; use unordered lists for fields that have lists with multiple entries (for example, a list of links with more than one item).

The left nav menu presents a challenge -- if the left nav menu is supposed to appear on the full-page display of a directory record! In order to get the menu to populate, the directory record must be configured to appear in the menu. But we will have hundreds of directory records! Currently, a modification to the twig file for the left nav menu detects the number of nodes at a level is greater than 50 and cuts menu generation off at that point.

* **menu--sidebar_first.html.twig** suppresses menu listings of >50 at a level

### Spanish Directories
To implement Spanish Directories the same view **Federal Agencies** is used but a secondary block **Indice Agencias A-Z** with *Content: Translation language set to Español* and a secondary **Alpha List ES** also with translation set to Español. Both the  **Federal Agencies A-Z** and **Indice Agencias A-Z** blocks have the link to standalone page text changed from `<a href="{{ view_node }}">More information about {{ title }}&nbsp;></a>` to only `{{ view_node }}`. This allows the rest of the link text to be set in **views-view-fields.html.twig** with the correct language following the pattern for the rest of the fields.

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

### Left Nav Menu

I've introduced a limitation in the Left Nav menu -- if there are more than 50 nodes at a level, the menu will suppress that level (and of course, anything below). I cannot imagine we will want anything near 50 menu items to display, but we should expect to have more than 50 federal agency items, so this lets us keep them in the menu structure without displaying a huge menu.

We need to find out from the Content team whether they actually want the left nav menu on pages showing an individual Federal Directory record. If they don't want it, we still need to figure out how to get Breadcrumbs without getting the menu.

Conversely, if they do want the left nav menu, we should possibly include the entry for *just* the currently-displayed record, eliminating only its siblings.

### Twig files more generally

Everyone working on this so far is new at twig. We have probably done some awkward stuff, particularly in:

* field--node--directory-record.html.twig
* menu--sidebar_first.html.twig
* node--directory-record--full.html.twig

There are probably some things being calculated in twig that should be handled in a preprocess hook, or something.

## Rejected Approaches

Getting the `federal_agencies` view to do the right thing was a challenge. It's basically a View in glossary mode, which is probably 80% of what we want. The last 20% consists of page layout and playing nicely with Tome. (Note that in these attempts, the URL path was /federal-agencies; the change to /agency-index came later.) Things I (@akf) tried that didn't work:

* Set the View's path to `/federal-agencies`. This shows the View's `Page` Display (with `Alpha List` attached).
   - Good: View displays, uses path-part syntax for URLs, e.g., `/federal-agencies/d` sets the View to show agencies that start with "D". Letter-based pagination works and the URLs should be just fine for static site generation with Tome.
   - Bad: Left side menu doesn't appear, even when I specified menu settings on the View.
   - Bad: Other elements of the page are messed up. Page Title didn't display, some elements appear in Spanish even though the View is set for English.
* As above, but add an explicit Link entry for the /federal-agencies path in the "Left Menu English" menu.
   - Good: This works, at least for the initial page.
   - Bad: Didn't work /federal-agencies/a, etc. (if I recall correctly)
* As above, but add some of the "missing" content as header or footer elements in the View.
   - Bad: This just plain didn't do anything.
* Display the view as a `Block` on a standard page at the `/federal-agencies` path, using the default link generation (which produces links like `/federal-agencies/d`).
  - Good: This solves all the layout issues for the first page.
  - Bad: It was necessary to change the path of the View itself (I chose `/federal-agencies-view`) in order to get the standard page to render at that location. This meant that the alpha list produced links to `/federal-agencies-view/d` and so on, giving us the messed-up display of the original approach.
* As above, but rewrite the links in twig. Modify the block layout so it includes the view's Block on any path that matches `/federal-agencies/*` (that * matches a single letter)
  - Good: The URL rewriting worked.
  - Bad: Drupal interprets paths with a letter appended as routes that should specify different nodes. The View block shows up on a "Page Not found" page.
* As above, but add a module to modify Drupal's routing for the `/federal-agencies/...` paths.
  - Good: Nothing, really. I was able to identify a request like `/federal-agencies/d` and render the node at `/federal-agencies` (by referring to it by ID), but the result was not what I wanted.
  - Bad: I think this basically catches the request too late in the process. You can change the routing and render a node instead of the View, but you don't get the left nav menu, breadcrumbs, or correct-language elements elsewhere on the page.
  - Bad: I failed to pass the desired letter through to the View in this scenario.
* The first implementation of Spanish Directories created a separate view. This methodology worked however it required  three additional Twig files (*views-view-summary-Spanish.html.twig, views-view-list-Spanish.html.twig, views-view-fields-Spanish.html.twig*) and two additional configuration files. To reduce repeat code the decision was made to utilize the same View with duplicate block. This allowed the original (*views-view-summary.html.twig, views-view-list.html.twig, views-view-fields.html.twig*) with a language check.
* Using only one block was considered for both languages however to implement this would then require a supplemental pre-process hook to only retrieve the agencies and Alpha List of the selected language. This would actually introduce more code into the repository to be maintained than the current implementation with two blocks. Though the block and alpha list or essentially duplicates of the English ones this allows them to share the same three Twig files for better maintainability.

