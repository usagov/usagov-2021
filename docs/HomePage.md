# To get the homepage working
1. Add the taxonomy term "Home Page" by:
    1. Going to Structure -> Taxonomy -> Edit page type --> Add new term -> "Home Page"
Taxonomy terms are content in drupal and if not already in database need to be manually added.

2. Import config changes by running "drush cr"
3. Go to the homepage and press edit to make sure that the config changes are imported into drupal

    * If they are you should see homepage banner and homepage top links in basic page cms

    * If they are not and you do not see a Home Page Banner and/or Top Links:

      * Go to Administration  -> Structure ->  Content types  -> Basic Page -> Add field

          1. Banner:

            * Field Type of Media,
            * Machine Name: field_homepage_banner
            * admin/structure/types/manage/basic_page/display

          2. Top Links:

            * Field type of Link;
            * Label: Homepage Top Links,
            * Machine Name: field_homepage_top_links,
            * Unlimited number of values

      * Add image styles by
          * Configuration -> Media -> Image Styles
          * 480px, 640px and 1024px at 1x, 1.5x, 2x and 3x resolutions should be created.


4. On Homepage in Drupal add
    1. Body text for the welcome box,
        * We are here to help you navigate your Federal Government. Check out our Top Pages section below to see what others are looking for, or jump to All Topics and Services.
    2. "Home Page" as the page type,
        * See above if not an option
    3. A homepage banner
        * Any photo you'd like for testing
    4. Top links
        * Prepare for hurricane season,
        * Check the status of my tax refund,
        * Reach out to a Federal Agency,
        * Get government benefits and financial assistance

**Note** --> If homepage banner does not show after doing the above, refresh the page. The image styles may not have loaded. Similarly if you change the size of your window you may need to refresh. This should not be an issue on dev, stage or prod as tome will create the necessary files for the static site. If however you still do not see the banner go to content -> media -> media library. Try and upload media there. If it works there is a bug somewhere else in the code. If it does not there is an issue with media in general on the site and should be fixed by creating a new ticket for it.

# Code Organization
The homepage is currently in node--1--full.html.twig. Page.html.twig checks if the term_name is "Home Page" and if so in calls 		`<main class="main-content usa-layout-docs {{ main_classes }}" id="main-content" data-pagetype="{{term_name}}">
{{ page.content }}`
This is because the other pages are wrapped in a `grid-container` which does not allow for full page spanning.

The homepage is wraped in a 2 column main-grid. There are five components used in the homepage and each component has it's own twig file found in the includes directory.The top level components are the
- banner
- how do I box
- life events
- all topics jump button
- all topics

## Banner
The banner component is set to span rows 1 & 2 of the grid.

### Banner Image
The banner image is determined by the width of the view port for responsiveness and image loading time. New images are called for 480px, 640px and 1024px (mobile, tablet and desktop breakpoints as determined by uswds) at (1x, 1.5x, 2x and 3x resolutions). The images are created in drupal by using image styles for their respective width.Because the banner is a background image and not set with the `<img>` tag image styles were used over the responsive image module. The twig tweak module is used to apply the correct style at the specified breakpoint i.e: `background-image: url({{ image_uri|image_style('max_1920w') }});`

The banner is set as a background image to allow the grid to change size based on the welcome and how do I boxes text lengths rather than image height.

### Welcome Box
The title and welcome box text are variables set as {{ node.title.value }} and {{ node.body.value | raw}} repsectively so they can be changed in the cms.

## How Do I Box
The how do I Box is set to span rows 2 & 3 of the grid. This allows it to "sit" on top of the banner image and the blue background.
An unordered list contains the links which are set in the cms as an array of{{field_homepage_top_links}}. The links are list items and styled as buttons. They are not buttons as links should be used when linking between a site's page per uswds guidelines and accessibility.

The links should present four across in desktop, two across in tablet and stacked in mobile.

### Blue Background
The full span blue background is set to span rows 2 - 7 of the grid.

## All Topics Jump Button
The All topics jump buttons are the same components. The first is in row 4. The second is in row 6.

## Life Experiences
The life experience components spans row 5. The intro copy is written in /includes/homepage-life-events.html.twig.

### Carousel
The carousel is a view (life_events_view) of the life experience pages. It uses the short description field from the life experience pages instead of the page intro. The navigation banners are also used from the pages.

The carousel is written in javascript found in /scripts/carousel.js. The navigation svg dots are created dynamically depending on the number of life experiences pages. The carousel displays 3 cards in desktop, two in tablet and one in mobile.

## All Topics
### Header
The all topics header spans row 7 and 8. The two rows allows the second jump to sit above the white background.

### Cards
The views-view-list--sub-children.html.twig templates checks that the term-name is "Home-Page" and calls the twig template "homepage-cards.html.twig."
