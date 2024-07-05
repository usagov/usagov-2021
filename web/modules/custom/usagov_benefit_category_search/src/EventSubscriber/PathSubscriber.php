<?php

namespace Drupal\usagov_benefit_category_search\EventSubscriber;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PathSubscriber implements EventSubscriberInterface {

  public function collectPaths(CollectPathsEvent $event) {
    $event->addPaths([
        '/benefits-search/en/pages.json',
        '/benefits-search/en/life-events.json',
        '/benefits-search/es/pages.json',
        '/benefits-search/es/life-events.json',
      ]
    );
  }

  /**k
   * {@inheritdioc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths'];
    return $events;
  }
}
