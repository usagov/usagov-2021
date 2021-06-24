# USAgov Drupal 9 Theme

This is the USAgov custom theme for Drupal 9. It is a subtheme of the uswds_base theme.

## Gulp Commands

`gulp init`
* Copies fonts, images, and javascript from the USWDS within node_modules.
* Compiles CSS from `/sass/styles.scss` which applies our sass settings to the sourcecode from USWDS within node_modules.

`gulp watch`
* Compiles CSS from `/sass/styles.scss` then watches for changes in `sass/**/*.scss`


## Making changes

### HTML
* Create template overrides in the `/templates` directory
* See examples in `./web/themes/contrib/uswds_base/templates`
* Clear the Drupal cache after saving template changes
* [Working with TWIG Templates](https://www.drupal.org/docs/theming-drupal/twig-in-drupal/working-with-twig-templates)
* [Overriding Templates](https://www.drupal.org/docs/7/theming/overriding-themable-output/beginners-guide-to-overriding-themable-output#s-overriding-a-template-file)

### CSS
* Run `gulp watch`
* Edit scss files in `/sass`
* Most files are settings for USWDS
* Custom styles can go into `_usagov-styles.scss`

### Javascript
* USWDS javascript is ccompiled and copied from the uswds within node_modules, so we won't be changing it.
* Custom javascript can be added to the `/scripts` directory and then added to a drupal library wich can be referenced by any templates that need the custom javascript. [More info](https://www.drupal.org/docs/creating-custom-modules/adding-stylesheets-css-and-javascript-js-to-a-drupal-module#twig)