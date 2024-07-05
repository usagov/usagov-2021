<?php

namespace Drupal\usagov_benefit_category_search\EventSubscriber;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PathSubscriber implements EventSubscriberInterface {

  public function collectPaths(CollectPathsEvent $event) {
    // Tell tome not to translate the paths we add
    $md = ['langcode' => 'es', 'language_processed' => 'language_processed'];
    $event->addPaths([
        '/benefits-search/en/pages.json' => $md,
        '/benefits-search/en/life-events.json' => $md,
        '/benefits-search/es/pages.json' => $md,
        '/benefits-search/es/life-events.json' => $md,
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
