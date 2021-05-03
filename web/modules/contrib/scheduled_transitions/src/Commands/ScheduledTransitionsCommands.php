<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Commands;

use Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commandfile for Scheduled Transitions.
 */
class ScheduledTransitionsCommands extends DrushCommands {

  /**
   * Job runner for Scheduled Transitions.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface
   */
  protected $jobs;

  /**
   * Constructs a new ScheduledTransitionsCommands.
   *
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsJobsInterface $jobs
   *   Job runner for Scheduled Transitions.
   */
  public function __construct(ScheduledTransitionsJobsInterface $jobs) {
    $this->jobs = $jobs;
  }

  /**
   * Fills queue with crawler jobs.
   *
   * @command scheduled-transitions:queue-jobs
   * @aliases sctr-jobs
   */
  public function crawlJobCreator() {
    $this->jobs->jobCreator();
    $this->logger()->success(dt('Scheduled transitions queued.'));
  }

}
