<?php

namespace Drupal\scanner\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Controller for Search and Replace module.
 */
class ScannerController extends ControllerBase {

  /**
   * Queries the database and builds the results for the "Undo" listing.
   * 
   * @return array
   *   A render array (table).
   */
  public function undoListing() {
    $connection = \Drupal::service('database');
    $query = $connection->query('SELECT * from {scanner} WHERE undone = 0');
    $results = $query->fetchAll();
    $header = [
      $this->t('Date'),
      $this->t('Searched'),
      $this->t('Replaced'),
      $this->t('Count'),
      $this->t('Operation'),
    ];
    $rows = [];

    // Build the rows of the table.
    foreach ($results as $result) {
      $undo_link = Link::fromTextAndUrl($this->t('Undo'), Url::fromUri("internal:/admin/content/scanner/undo/$result->undo_id/confirm"))->toString();
      $rows[] = [
        \Drupal::service('date.formatter')->format($result->time),
        $result->searched,
        $result->replaced,
        $result->count,
        $undo_link,
      ];
    }

    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => NULL,
    ];

    return $table;
  }

}