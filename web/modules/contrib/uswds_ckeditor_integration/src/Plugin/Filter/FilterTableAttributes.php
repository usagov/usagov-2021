<?php

namespace Drupal\uswds_ckeditor_integration\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Filter to apply USWDS Stacked attributes.
 *
 * @Filter(
 *   id = "filter_table_attributes",
 *   title = @Translation("USWDS Stacked Table Attributes"),
 *   description = @Translation("Apply USWD table stacked attributes."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterTableAttributes extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $result = new FilterProcessResult($text);
    if (stristr($text, 'table') !== FALSE) {
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      $tables = $xpath->query('//table[@class="usa-table--stacked"]');

      // Add USWDS Class to table.
      foreach ($tables as $table) {

        $rows = $xpath->query('.//tr', $table);
        $cols = $xpath->query('./th | ./td', $rows->item(0));

        $table_headers = [];
        // Add scope to column headers.
        foreach ($cols as $col) {
          $label = $col->nodeValue;
          $table_headers[] = $label;
        }

        // Add scope to rows.
        $skip_first = TRUE;
        foreach ($rows as $row) {
          if ($skip_first) {
            $skip_first = FALSE;
            continue;
          }
          $tds = $xpath->query('./td | ./th', $row);
          $counter = 0;
          foreach ($tds as $td) {
            $data_label = $table_headers[$counter];
            $td->setAttribute('data-label', $data_label);
            $counter++;
          }
        }
      }

      $result->setProcessedText(Html::serialize($dom));
    }
    return $result;
  }

}
