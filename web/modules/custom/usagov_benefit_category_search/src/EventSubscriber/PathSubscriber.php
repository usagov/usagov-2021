<?php

namespace Drupal\usagov_benefit_category_search\EventSubscriber;

use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PathSubscriber implements EventSubscriberInterface {

  public function collectPaths(CollectPathsEvent $event) {
    // need the basepath for pages.gov exports
    $base_path = rtrim(trim(base_path()), '/');

    // Tell tome not to translate the paths we add
    $md = ['langcode' => 'en', 'language_processed' => 'language_processed'];
    $event->addPaths([
      $base_path . '/_data/benefits-search/en/pages.json' => $md,
      $base_path . '/_data/benefits-search/en/life-events.json' => $md,
      $base_path . '/_data/benefits-search/es/pages.json' => $md,
      $base_path . '/_data/benefits-search/es/life-events.json' => $md,
    ]);
  }

  /**k
   * {@inheritdioc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths'];
    return $events;
  }

}
