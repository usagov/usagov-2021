<?php

/**
 * @file
 * Preprocess function for this hook.
 */

/**
 * Implements hook_preprocess_form_element().
 */
function uswds_base_preprocess_form_element(&$variables) {
  // Add an element type for the label.
  $type = $variables['element']['#type'];
  $variables['label']['#element_type'] = $type;

  if (!empty($variables['errors'])) {
    $variables['error_id'] = $variables['element']['#attributes']['id'];
    $variables['attributes']['class'][] = 'usa-input-error';
  }
}
