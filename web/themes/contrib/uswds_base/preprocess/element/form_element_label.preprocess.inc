<?php

/**
 * @file
 * Preprocess function for this hook.
 */

/**
 * Implements hook_preprocess_form_element_label().
 */
function uswds_base_preprocess_form_element_label(&$variables) {

  if (!empty($variables['required'])) {
    $variables['attributes']['class'][] = 'usa-input-required';
  }

  if (!empty($variables['element']['#element_type']) && $variables['element']['#element_type'] == 'checkbox') {
    $variables['is_checkbox'] = TRUE;
  }
  else {
    $variables['is_checkbox'] = FALSE;
  }

  if (!empty($variables['element']['#element_type']) && $variables['element']['#element_type'] == 'radio') {
    $variables['is_radio'] = TRUE;
  }
  else {
    $variables['is_radio'] = FALSE;
  }

}
