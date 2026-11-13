<?php

/**
 * @file
 * Preprocess function for form_element_label.
 *
 * Lives here, not in usagov.theme, because uswds_base appends its own
 * preprocess/*.preprocess.inc functions after core's, and then each sub-theme's
 * in the same way (uswds_base_theme_registry_alter()). Only a file here runs
 * after uswds_base_preprocess_form_element_label().
 */

/**
 * Implements hook_preprocess_HOOK() for form_element_label.
 *
 * Pinned from uswds_base 2.15 (USAGOV-2953): 3.x renamed the required-label
 * class to usa-label--required. Delete this file to adopt the 3.x class.
 *
 * @param array<string, mixed> $variables
 */
function usagov_preprocess_form_element_label(array &$variables): void {
  $classes = $variables['attributes']['class'] ?? [];
  if (is_array($classes) && ($key = array_search('usa-label--required', $classes, TRUE)) !== FALSE) {
    $variables['attributes']['class'][$key] = 'usa-input-required';
  }
}
