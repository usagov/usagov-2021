<?php

/**
 * @file
 * Preprocess function for this hook.
 */

/**
 * Implements hook_preprocess_block__DELTA__REGION().
 *
 * This made possible by our custom theme suggestion.
 *
 * @see uswds_theme_suggestions_block_alter()
 */
function uswds_base_preprocess_block__block_content__hero(&$variables) {
  _uswds_base_mark_block_content_items($variables, 'hero');
}
