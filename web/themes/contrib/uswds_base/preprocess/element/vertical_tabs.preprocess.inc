<?php

/**
 * @file
 * Preprocess function for this hook.
 */

use Drupal\Core\Render\Markup;

/**
 * Implements hook_preprocess_vertical_tabs().
 */
function uswds_base_preprocess_vertical_tabs(&$variables) {
  $prefix = '<ul class="usa-accordion-bordered usa-list.usa-list--unstyled">';
  $suffix = '</ul><!-- USWDS fieldset end -->';
  $content = $variables['children'];
  $content = str_replace($prefix, '', $content);
  $content = str_replace($suffix, '', $content);
  $variables['children'] = Markup::create($content);

  $variables['attributes']['class'][] = 'usa-accordion-bordered';
}
