<?php

namespace Drupal\usagov_redirect\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound path to handle redirects.
 */
class UsagovInboundPathProcessor implements InboundPathProcessorInterface {

  /**
   * Processes the inbound path before resolving the route.
   *
   * @param string $path
   *   The inbound path.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return string
   *   The modified path.
   */
  public function processInbound($path, $request) {

    // This function was written in order to to prevent users from going to
    // a path of index.php without a proceeding slash after the ".php",
    // this is needed in rare edge-cases because without the slash, the
    // AlaisHandeler crashes the PHP-thread.

    // I am using a PathProcessor interface so that I can get this code to execute before the AlaisHandeler.
    // While it would seem like we could simply return the updated path here, that doesn't work because
    // the $request object has already been built.

    // If the original URL request is /index.php but the following character is not a slash.
    if (substr($_SERVER['REQUEST_URI'], 0, 10) === '/index.php' && substr($_SERVER['REQUEST_URI'], 10, 1) !== '/') {

      // // Then we will add a slash after the ".php" and redirect the user.
      // $sendTo = $_SERVER['REQUEST_URI'];
      // $sendTo = str_replace('index.php', 'index.php/', $sendTo);
      // header('Location: ' . $sendTo);

      // EDIT: A per ticket USAGOV-1986 the commented-out code above was flagged as a vulnerability.
      // So we will now instead redirect the user to the front-page, as this is considered a bad-path anyways.
      header('Location: /');
      http_response_code(302);
      exit();
    }

    return $path;
  }

}
