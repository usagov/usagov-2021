<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Cache\CacheCollectorInterface;
use Drupal\path_alias\AliasManager;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Clears the menu active trail cache between Tome requests.
 *
 * @internal
 */
class RequestPrepareSubscriber implements EventSubscriberInterface {

  public function __construct(
    private CacheCollectorInterface $menu_active_trail,
    private AliasManager $alias_manager,
  ) {

  }

  /**
   * Clear additional caches.
   *
   * Fixes issues found when tome export path count is greater than 1.
   */
  public function requestPrepare(): void {
    // Fixes menu blocks rendering with the wrong items because the
    // active path incorrectly persists between page requests.
    $this->menu_active_trail->clear();
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
