<?php

namespace Drupal\usagov_ssg_postprocessing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * An usagov_ssg_postprocessing controller for stating the current static-site-gen status.
 */
class SsgStatController extends ControllerBase {

  protected $requestStack;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

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

  /*
   * This is a utility use in order to test what the WAF and proxies will do with wait-timeouts.
   * See ticket USAGOV-1927.
   */
  public function siteLagTest() {

    $request = $this->requestStack->getCurrentRequest();
    $waitParam = $request->query->get('wait');

    if (!empty($waitParam)) {
      $wait = intval($waitParam);
    }
    if (!empty($wait)) {
      sleep($wait);
      $message = "Waited {$wait} seconds before returning this page.";
    }
    else {
      $message = "Append something like ?wait=30 in your address bar to make this page lag.";
    }

    return ['#markup' => $message, '#cache' => ['max-age' => 0]];
  }

}
