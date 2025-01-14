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

      $command = escapeshellcmd('cd modules ls');
      $output = shell_exec($command);
      echo $output;

      // // Run the python script.
      // $output = shell_exec('python3 /modules/custom/usagov_analytics/Scripts/gsc_api_v3.py');

      // echo $output;
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
