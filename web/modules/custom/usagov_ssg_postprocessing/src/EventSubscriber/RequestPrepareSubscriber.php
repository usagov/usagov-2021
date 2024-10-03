<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\path_alias\AliasManager;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears non-core caches between Tome requests.
 *
 * This event is useful when running tome with more than one path per process to
 * clear any caches used by contrib modules.
 */
class RequestPrepareSubscriber implements EventSubscriberInterface {

  public function __construct(
    private AliasManager $alias_manager,
  ) {}

  /**
   * Clear additional caches.
   *
   * Fixes issues found when tome export path count is greater than 1.
   */
  public function requestPrepare(): void {
    // Fixes redirects exporting with the target node's content instead
    // of an HTML redirect.
    $this->alias_manager->cacheClear();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::REQUEST_PREPARE][] = ['requestPrepare'];
    return $events;
  }

}
