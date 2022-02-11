<?php

/**
 * @file
 * Contains Drupal\google_docs_filter\Plugin\Filter\FilterGoogledocs
 */

namespace Drupal\google_docs_filter\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a filter to clean google doc code!
 *
 * @Filter(
 *   id = "filter_google_docs",
 *   title = @Translation("Google Docs Filter"),
 *   description = @Translation("Format Google Doc code"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_HTML_RESTRICTOR,
 * )
 */

class FilterGoogledocs extends FilterBase {

  public function process($text, $langcode) {

    $replace = $this->t('');
    $new_text = str_replace(['rel="noopener"', 'target="_blank"'], $replace, $text);
    $result = new FilterProcessResult($new_text);
    return $result;

  }

}
