<?php
/**
 * Example event subscriber.
 */

// Declare the namespace that our event subscriber is in. This should follow the
// PSR-4 standard, and use the EventSubscriber sub-namespace.

namespace Drupal\usagov_redirect\RedirectSubscriber;

// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\RequestEvent;
// We'll use this to perform a redirect if necessary.
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Subscribe to KernelEvents::REQUEST events and redirect if site is currently
 * in maintenance mode.
 */
class RedirectSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForRedirection');
    return $events;
  }

  /**
   * This method is called whenever the KernelEvents::REQUEST event is
   * dispatched.
   *
   * @param RequestEvent $event
   */
  public function checkForRedirection(RequestEvent $event) {

    $response = $event->getResponse();
    echo "<script>console.log('Debug Objects: " . $response . "' );</script>";

    if (($response instanceof RedirectResponse)) {

      $event->setResponse(new RedirectResponse('http://localhost/'));
      return;
    }

  //   $uri  = $response->getTargetUrl();

  //   $raw_html = `<!DOCTYPE html>
  //   <html>
  //       <head>
  //           <meta charset="UTF-8" />
  //           <meta name="robots" content="noindex" />
  //           <meta http-equiv="refresh" content="0;url=\'$uri\'" />

  //           <title>Redirecting to $uri</title>
  //       </head>
  //       <body>
  //           Redirecting to <a href="$uri">$uri</a>.
  //       </body>
  //   </html>`;

  //   $renderer = \Drupal::service('renderer');
  //   $HTML_conversion = $renderer->render($raw_html);

  //   $response->setContent($HTML_conversion);

  }
}
