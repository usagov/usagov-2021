<?php

/**
 * @file
 * Preprocess function for blocks.
 */

/**
 * Implements hook_preprocess().
 */
function uswds_base_preprocess(&$variables) {
  // Set the path to the base theme.
  $variables['active_theme_path'] = base_path() . \Drupal::theme()->getActiveTheme()->getPath();

  // Set the path to the base theme.
  if (theme_get_setting('cdn_provider') == 'custom' || theme_get_setting('cdn_provider') == 'jsdelivr') {
    $variables['uswds_images'] = theme_get_setting('cdn_custom_img') . '/';
  }
  else {
    $variables['uswds_images'] = $variables['active_theme_path'] . '/assets/img/';
  }
}
