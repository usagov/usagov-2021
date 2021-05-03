<?php

namespace Drupal\address_test\EventSubscriber;

use Drupal\address\Event\AddressEvents;
use Drupal\address\Event\AvailableCountriesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to Address events for testing.
 *
 * @see \Drupal\Tests\address\FunctionalJavascript\AddressDefaultWidgetTest::testEvents()
 */
class AddressTestEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AddressEvents::AVAILABLE_COUNTRIES][] = ['onAvailableCountries'];
    return $events;
  }

  /**
   * Alters the available countries.
   *
   * @param \Drupal\address\Event\AvailableCountriesEvent $event
   *   The available countries event.
   */
  public function onAvailableCountries(AvailableCountriesEvent $event) {
    $event->setAvailableCountries($this->getAvailableCountries());
  }

  /**
   * Generates a set of available countries.
   *
   * @return array
   *   The countries.
   */
  public function getAvailableCountries() {
    return [
      'AU' => 'AU',
      'BR' => 'BR',
      'CA' => 'CA',
      'GB' => 'GB',
      'US' => 'US',
    ];
  }

}
