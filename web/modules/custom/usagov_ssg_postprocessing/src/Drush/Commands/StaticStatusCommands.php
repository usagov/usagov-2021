<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drupal\Core\State\StateInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for static site generation status.
 */
final class StaticStatusCommands extends DrushCommands {

  public function __construct(private StateInterface $state) {
    parent::__construct();
  }

  /**
   * Update static site generation status state.
   */
  #[Command(name: 'usagov:ssg-status', aliases: ['ssg-status'])]
  #[Argument(name: 'timestamp', description: 'Unix timestamp to show for the status update.')]
  #[Argument(name: 'message', description: 'Status message to show for static site generation.')]
  #[Usage(name: 'drush usagov:ssg-status 1719878400 "Static Site Generation Started"', description: 'Update the status timestamp and message.')]
  public function updateStatus(int|string $timestamp, string $message): void {
    $this->state->set('ssg_stat_date', (int) $timestamp);
    $this->state->set('ssg_stat_msg', $message);
    $this->output()->writeln('Static site generation status updated.');
  }

}
