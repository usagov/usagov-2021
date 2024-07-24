<?php

/**
 * Example event subscriber.
 */

// Declare the namespace that our event subscriber is in. This should follow the
// PSR-4 standard, and use the EventSubscriber sub-namespace.

namespace Drupal\usagov_redirect\RedirectSubscriber;

// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// We'll use this to perform a redirect if necessary.
use Symfony\Component\HttpFoundation\RedirectResponse;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\ResponseEvent;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribe to KernelEvents::REQUEST events and redirect if site is currently
 * in maintenance mode.
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
   * This method is called whenever the KernelEvents::REQUEST event is
   * dispatched.
   *
   * @param ResponseEvent $event
   */
  public function checkForRedirection(ResponseEvent $event) {

    $response = $event->getResponse();

    if (($response instanceof RedirectResponse)) {

      $event->setResponse(new RedirectResponse('http://localhost/'));

      // $url = Url::fromRoute('entity.node.canonical', ['node' => 1]);
      // $response = new RedirectResponse($url->toString());
      // $event->setResponse($response);

      return;

    }

  }

}
