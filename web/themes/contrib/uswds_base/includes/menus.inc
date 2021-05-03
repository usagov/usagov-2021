<?php

/**
 * @file
 * Utility code related to menus and menu blocks.
 */

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function uswds_base_theme_suggestions_menu_alter(array &$suggestions, array $variables) {
  if (!empty($variables['items'])) {
    foreach ($variables['items'] as $item) {
      if (!empty($item['#uswds_base_region'])) {
        if (_uswds_base_process_menu_region($item['#uswds_base_region'])) {
          $suggestions[] = 'menu__' . $item['#uswds_base_region'];
        }
      }
      // We only need to look at one.
      break;
    }
  }
  return $suggestions;
}

/**
 * Helper function to see if we should alter any menu.
 *
 * @param string $region
 *   The machine name of the region.
 *
 * @return bool
 *   Return if this should process the menu or not.
 */
function _uswds_base_process_menu_region($region) {
  $menu_bypass = theme_get_setting('uswds_menu_bypass');
  if (empty($menu_bypass)) {
    $menu_bypass = [];
  }
  return (empty($menu_bypass[$region]));
}

/**
 * Helper function to mark menu items as being in one of our menu regions.
 *
 * This is the way we communicate a menu block's region to its preprocessor
 * and template.
 *
 * @see preprocess/uswds_base_preprocess_block__system_menu_block__*
 */
function _uswds_base_mark_menu_items(&$variables, $region) {
  foreach ($variables['content']['#items'] as $key => &$item) {
    $item['#uswds_base_region'] = $region;
  }
}
