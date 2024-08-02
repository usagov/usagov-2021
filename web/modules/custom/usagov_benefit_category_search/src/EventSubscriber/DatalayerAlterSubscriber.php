<?php

namespace Drupal\usagov_benefit_category_search\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;

class DatalayerAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      DatalayerAlterEvent::EVENT_NAME => 'onDatalayerAlter',
    ];
  }

  /**
   * Adds category information to the datalayer.
   */
  public function onDatalayerAlter(DatalayerAlterEvent $event):void {

  }

}
