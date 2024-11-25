<?php

namespace Drupal\usagov_redirect\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class Custom404Subscriber
 * Listens to 404 errors and performs custom actions.
 */
class Custom404Subscriber implements EventSubscriberInterface {

  /**
   * Responds to 404 Not Found exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event to process.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();

    // We only want to react on 404 (Not Found) pages.
    if (!($exception instanceof NotFoundHttpException)) {
      return;
    }

    // Determine what path we would be on if we remove all known problem-characters from this path
    $currentPath = ltrim($_SERVER['REQUEST_URI'], '/');
    $testPath = $currentPath;
    $badCharacters = [
      '%C2%A0', /* non-breaking space */
      '%E2%80%89', /* think space */
      '%20', /* regular space (encoded) */
      ' ' /* regular space */
    ];
    foreach ($badCharacters as $badCharacter) {
      $testPath = str_replace($badCharacter, '', $testPath);
    }

    // We only want to react when the URL-path contains certain known problem-characters.
    if ($testPath == $currentPath) {
      return;
    }

    // Test is $testPath really points to an existing Drupal node. Bail if it does not.
    $testSysPath = \Drupal::service('path_alias.manager')->getPathByAlias('/' . $testPath);
    if (!str_starts_with($testSysPath, '/node/')) {
      return;
    }

    // At this point we know that if we were to go to this same path with the problem-characters removed,
    // then we should get a valid page-load rather than a 404. So now we will redirect there.
    header("Location: /" . $testPath, TRUE, 301);
    exit();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::EXCEPTION => 'onException',
    ];
  }

}
