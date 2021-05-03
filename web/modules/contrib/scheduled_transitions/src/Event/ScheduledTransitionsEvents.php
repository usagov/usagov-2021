<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Event;

/**
 * Defines events for scheduled transitions.
 */
final class ScheduledTransitionsEvents {

  /**
   * Used to determine the new revision for a transition.
   *
   * @Event
   *
   * @see \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent
   */
  const NEW_REVISION = 'scheduled_transitions.new_revision';

}
