<?php

namespace Drupal\samlauth_test\EventSubscriber;

use Drupal\Core\Url;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that subscribes to Samlauth events for testing.
 */
class TestSamlauthEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SamlauthEvents::USER_LINK][] = ['onUserLink'];
    $events[SamlauthEvents::USER_SYNC][] = ['onUserSync'];
    return $events;
  }

  /**
   * Performs actions to synchronize users with SAML data on login.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The event.
   */
  public function onUserSync(SamlauthUserSyncEvent $event) {
    if (\Drupal::state()->get('samlauth_test_urltostring_mask', 0) & 8) {
      Url::fromRoute('<front>')->toString();
    }
  }

  /**
   * Allows to link an existing user on SAML login.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserLinkEvent $event
   *   The event.
   */
  public function onUserLink(SamlauthUserLinkEvent $event) {
    if (\Drupal::state()->get('samlauth_test_urltostring_mask', 0) & 16) {
      Url::fromRoute('<front>')->toString();
    }
  }

}
