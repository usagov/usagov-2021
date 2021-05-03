<?php

namespace Drupal\scheduled_publish\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\views\Views;

/**
 * Class ScheduledPublishListing.
 *
 * @package Drupal\scheduled_publish\Controller
 */
class ScheduledPublishListing extends ControllerBase {

  /**
   * Gets the listing view if possible.
   */
  public static function viewListing() {
    if (scheduled_publish_get_node_fields()) {
      $view = Views::getView('scheduled_publish');
      $view->setDisplay('block_1');
      return $view->buildRenderable();
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => t('A scheduled publish field has to be added to a content type before this functionality can be used.'),
    ];
  }

}
