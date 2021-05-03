<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Interface for transition executor.
 */
interface ScheduledTransitionsRunnerInterface {

  /**
   * Executes a transition.
   *
   * Ignores transition time as it is already checked by job runner.
   *
   * Pass a transition that should be run. This method is responsible for
   * loading latest and the new revision, then delegating saving new revision,
   * and any intermediate revisions if applicable. The transition may also be
   * deleted depending on settings.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   *
   * @throws \Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity
   *   Thrown if any entity or entity revision is missing for a transition.
   *   Transition is never automatically deleted if exception is thrown.
   */
  public function runTransition(ScheduledTransitionInterface $scheduledTransition): void;

}
