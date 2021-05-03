<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

/**
 * Job runner for Scheduled Transitions.
 */
interface ScheduledTransitionsJobsInterface {

  /**
   * Checks for transitions needing to be run, and adds them to a queue.
   */
  public function jobCreator(): void;

}
