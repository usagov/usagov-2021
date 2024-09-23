<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * An usagov_ssg_postprocessing controller for stating the current static-site-gen status.
 */
class SsgStatController extends ControllerBase {

  public function content() {

    $date = \Drupal::state()->get('ssg_stat_date');
    $msg = \Drupal::state()->get('ssg_stat_msg');

    if (empty($msg)) {
      $markup = "Static Site Generator has not been run on this environment yet.";
    }
    else {
      $formatDate = date('D F jS Y, h:ia T', $date);
      $markup = "Static Site Generator, status as of <b>{$formatDate}:</b><br/><ul><li><i><b>{$msg}<b></i></li></ul>";
    }

    return ['#markup' => $markup, '#cache' => ['max-age' => 0]];
  }

}
