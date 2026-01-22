<?php

namespace Drupal\usagov_redirect\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to block Spanish blog pages.
 */
class BlogLanguageSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Run before routing
    $events[KernelEvents::REQUEST][] = ['onRequest', 31];
    return $events;
  }

  /**
   * Block access to Spanish blog pages.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    // Only act on master requests
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $path = $request->getPathInfo();

    // Check if this is a Spanish blog path
    if (str_starts_with($path, '/es/blog')) {
      throw new NotFoundHttpException('Page not found.');
    }
  }

}
