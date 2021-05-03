<?php

/**
 * @file
 * Preprocess function for this hook.
 */

/**
 * Implements hook_preprocess_menu__REGION().
 *
 * This made possible by our custom theme suggestion.
 *
 * @see uswds_theme_suggestions_menu_alter()
 */
function uswds_base_preprocess_menu__primary_menu(&$variables) {

  $variables['megamenu'] = theme_get_setting('uswds_header_mega');

  // Display 2nd level as column headers for 3rd - Megamenu.
  $variables['mega_display_second'] = theme_get_setting('uswds_header_mega_display_second');

  // Do we want to duplicate parent items as the first child?
  $variables['duplicate_parent'] = theme_get_setting('uswds_menu_accordion_duplicate_parent');

}
