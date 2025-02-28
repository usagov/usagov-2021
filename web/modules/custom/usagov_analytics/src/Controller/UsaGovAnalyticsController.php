<?php

namespace Drupal\usagov_analytics\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller routines for USA Contact Center API routes.
 */
class UsaGovAnalyticsController extends ControllerBase {

  /**
   * Controller routines for USA Contact Center API routes.
   */
  public function runScript() {

    // Check if the user is authenticated.
    $current_user = \Drupal::currentUser();

    if ($current_user->isAuthenticated()) {

      // Run the python script.
      $output = shell_exec('
        cd modules/custom/usagov_analytics/src/Scripts
        chmod +x setup.sh
        sh ./setup.sh
      ');

      // Create a file response.
      $response = new Response($output);
      $response->headers->set('Content-Type', 'text/plain');
      $response->headers->set('Content-Disposition', 'attachment; filename="script_output.txt"');

      return $response;
    }
    else {
      return new Response('Access denied.', 403);
    }
  }

}
