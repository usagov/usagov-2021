# Implementation of the Federal Directory 

Currently under development!

The Federal Directory is a glossary-style index, which will appear at the path /federal-agencies. 

## What it Does

Content managers can add individual directory records, which contain key information for a specific organization (federal agency). These are served in a glossary-style listing. Individual entries can be displayed as stand-alone pages. 

## Approach

We've tried to use "core Drupal" to build as much of this as possible.

### Drupal Structures

The following are accessbile within the Drupal Admin UI: 

* **Federal Directory Record** Content type (Machine name: `directory_record`)
* **Federal Agencies** View (Machine name: `federal_agencies`)
* **Block Layout** modified to include the Federal Agencies View Block in the content area when the displayed page matches the /federal-agencies path. 
* **Basic Page at /federal-agencies path**: This is the "home" for a Block provided by the Federal Agencies View. It provides the page title and menu configuration (for the left sidebar). 

Content editors will be able to create and manage content of type "Federal Directory Record" in the same way as they do other content. They are not expected to modify the Federal Agencies View or the block layout. It will be possible to modify the intro or body of the basic page at /federal-agencies, although no such need is anticipated.

The `directory_record` content type, `federal_agencies` view, and Block Layout all produce artifacts (YAML) that are checked in to this repo.

The basic page at /federal-agencies is constructed manually via the Drupal admin UI.

#### More on the Federal Agencies View setup

The `federal_agencies` view uses a contextual filter on the query parameter "letter" to get the current letter to display (defaulting to "a" if none is supplied). This format (as opposed to using a path part like "/a") plays nicely with Drupal's routing, which by default sees /federal-agencies and /federal-agencies/a as requests for different nodes. The path-part syntax would have worked well if we used the "page" display of the view, but that did not play well with the rest of our existing layout.

The final form of this view has `Page`, `Block`, and `Alpha List` displays. At the /federal-agencies path, we call for  the `Block` display, which brings along `Alpha List` (the list of linked letters) as an attachment. DO NOT DELETE the `Page` Display, though -- without it, Drupal hits an error while rendering the `Alpha List`.

### "USAGov Directories" Drupal Module

Files in `web/modules/custom/usagov_directories`

This very small module converts URLs with query parameters like `?letter=a` to path parts like `/a` when Tome is used to generate the static site pages. This makes static site generation work for the federal-agencies pages, and makes paths that match what the current usa.gov uses. 

The module adds an event subscriber that listens for two `tome_static` events and rewrites URLs. The code is based on a class Tome already includes that rewrites `page=2` query parameters for views that use that convention. (The Tome version checks both for the `page` parameter and for an integer value. The usagov_directories version looks for a `letter` parameter but doesn't validate the values further. It might make sense to look for a single character.)

### Twig Templates

**Bolded** items are doing something that might be "interesting." 

For the A-Z view: We've added twig templates to override the default layout for a view. This is accomplished using conditional clauses within the usagov twig templates -- "if we're at the federal-agencies path, do this layout, otherwise do the default thing." In addition to layout, we're overriding how links are built for the glossary letters; by default we would get links to the bare view, not back to the /federal-agencies page.

* **views-view-summary.html.twig:** Overrides link generation for the letters (producing "/federal-agencies?letter=b", for example). Plus a bunch of specific layout, including the search box.
* views-view-list.html.twig: Overrides the layout of the part of the view that shows a heading and list. Mostly it's just adding the H2 heading and emitting the content without the unordered-list output the standard viewws get. 
* views-view-fields.html.twig: Lays out the fields for an individual directory record within the view. (A subset of the directory record content is displayed within an accordion design element.) 

* field--node--directory-record.html.twig: Use <span> instead of <div> for fields; use unordered lists for fields that have lists with multiple entries (for example, a list of links with more than one item).

The left nav menu presents a challenge -- if the left nav menu is supposed to appear on the full-page display of a directory record! In order to get the menu to populate, the directory record must be configured to appear in the menu. But we will have hundreds of directory records! Currently, a modification to the twig file for the left nav menu detects the number of nodes at a level is greater than 50 and cuts menu generation off at that point.

* **menu--sidebar_first.html.twig** suppresses menu listings of >50 at a level

### CSS

There is some added CSS (SASS). Perhaps someone who worked on that would like to add notes! It should be in harmony with how CSS is done elsewhere on the site.

## Setup 

Upon merge or first deployment against a given database:

1. Ensure the **USAGov Directories** module (a.k.a. `usagov_directories`) is enabled.
1. Sync Configuration -- this will bring in the Federal Directory Record content type, Federal Agencies view, and Block Layout.
1. **Manual step:** add a standard page with the following settings, and Publish it:
   * **Title:** Directory of U.S. Government Agencies and Departments
   * **Language:** English
   * **URL alias:** /federal-agencies
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

## TODO

Known features that have yet to be implemented: 

### Non-trivial features: 

* Synonyms
* Data import from mothership

### Probably well-understood:

* Spanish version of the glossary-style view. 
* Carousel-style letters at the bottom of a glossary page


## Rejected Approaches

Getting the `federal_agencies` view to do the right thing was a challenge. It's basically a View in glossary mode, which is probably 80% of what we want. The last 20% consists of page layout and playing nicely with Tome. Things I (@akf) tried that didn't work:

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



