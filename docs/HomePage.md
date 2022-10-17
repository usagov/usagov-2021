# To get the homepage working
1. Make sure that the config changes are imported into drupal
  1. If they are you should see homepage banner, homepage top links in basic page & a new taxonomy term of Home Page
  2. Image styles for 480px, 640px and 1024px at 1x, 1.5x, 2x and 3x resolutions should be created.
2. On Homepage in Drupal add
  1. a homepage banner,
  2. top links,
  3. body text for the welcome box and,
  4. "Home Page" as the page type.


# Code Organization
The homepage is currently in node--1--full.html.twig. Page.html.twig checks if the term_name is "Home Page" and if so in calls 		`<main class="main-content usa-layout-docs {{ main_classes }}" role="main" id="main-content" data-pagetype="{{term_name}}">
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

### Blue Background
The full span blue background is set to span rows 2 - 7 of the grid.

## All Topics Jump Button
The All topics jump buttons are the same components. The first is in row 4. The second is in row 6.

## Life Events
The life events components spans row 5.
### Carousel
TBD



## All Topics
### Header
The all topics header spans row 7 and 8. The two rows allows the second jump to sit above the white background.

### Cards
The views-view-list--sub-children.html.twig templates checks that the term-name is "Home-Page" and calls the twig template "homepage-cards.html.twig."
