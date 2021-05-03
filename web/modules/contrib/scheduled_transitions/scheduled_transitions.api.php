<?php

// @codingStandardsIgnoreFile

/**
 * @file
 * API Examples for Scheduled Transitions.
 *
 * Contains hooks and event examples for Scheduled Transitions module.
 */

declare(strict_types = 1);

/**
 * Event subscribers for Scheduled Transitions Field.
 *
 * Define a service, e.g:
 * <code>
 * ```yaml
 *  my_module.my_event_subscriber:
 *    class: Drupal\my_module\EventSubscriber\MyEventSubscriber
 *    tags:
 *     - { name: event_subscriber }
 * ```
 * </code>
 */
class MyEventSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * Custom logic to determine new revision for a scheduled transition.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   New revision event.
   */
  public function newRevision(\Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event): void {
    $scheduledTransition = $event->getScheduledTransition();
    $entity = $scheduledTransition->getEntity();
    $newRevision = $entity;
    $event->setNewRevision($newRevision);
    $event->stopPropagation();;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      \Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents::NEW_REVISION => ['newRevision'],
    ];
  }

}
