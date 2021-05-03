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
function uswds_base_preprocess_menu__mobile_menu(&$variables) {

  // Pass in uuids to help match accordion buttons with content.
  foreach ($variables['items'] as &$item) {
    $uuid = $item['original_link']->getDerivativeId();
    $item['uuid'] = $uuid;
  }

  // Do we want to duplicate parent items as the first child?
  $variables['duplicate_parent'] = theme_get_setting('uswds_menu_accordion_duplicate_parent');
}
