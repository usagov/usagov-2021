<?php

namespace Drupal\usagov_redirect\RedirectSubscriber;

// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// We'll use this to check if the event response is from this instance.
use Symfony\Component\HttpFoundation\RedirectResponse;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\ResponseEvent;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribe to KernelEvents::RESPONSE events and adds the robots noindex to redirect pages
 */
class RedirectSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkForRedirection'];
    return $events;
  }

  /**
   * This method is called whenever the KernelEvents::RESPONSE event is
   * dispatched.
   *
   * @param ResponseEvent $event
   */
  public function checkForRedirection(ResponseEvent $event) {

    $response = $event->getResponse();

    // If it is not a redirect we are not going to do anything.
    if (!($response instanceof RedirectResponse)) {
      return;
    }

    // Get the URL we want to redirect to
    $uri = $response->getTargetUrl();

    // Set the html for the redirect pages
    $response->setContent(
      sprintf('<!DOCTYPE html>
      <html>
          <head>
              <meta charset="UTF-8" />
              <meta name="robots" content="noindex" />
              <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

              <title>Redirecting to %1$s</title>
          </head>
          <body>
              Redirecting to <a href="%1$s">%1$s</a>.
          </body>
      </html>', htmlspecialchars($uri, ENT_QUOTES, 'UTF-8')));

  }

}
