<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Cache\CacheCollectorInterface;
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
  ) {

  }

  public function requestPrepare() {
    $this->menu_active_trail->clear();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::REQUEST_PREPARE][] = ['requestPrepare'];
    return $events;
  }

}
