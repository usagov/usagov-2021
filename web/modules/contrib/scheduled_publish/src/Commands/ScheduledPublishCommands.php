<?php

namespace Drupal\scheduled_publish\Commands;

use Drupal\scheduled_publish\Service\ScheduledPublishCron;
use Drush\Commands\DrushCommands;

/**
 * Class ScheduledPublishCommands
 *
 * @package Drupal\scheduled_publish\Commands
 */
class ScheduledPublishCommands extends DrushCommands {

  /**
   * @var \Drupal\scheduled_publish\Service\ScheduledPublishCron
   */
  private $publishCron;

  /**
   * ScheduledPublishCommands constructor.
   *
   * @param \Drupal\scheduled_publish\Service\ScheduledPublishCron $publishCron
   */
  public function __construct(ScheduledPublishCron $publishCron) {
    parent::__construct();
    $this->publishCron = $publishCron;
  }

  /**
   * @command scheduled_publish:doUpdate
   * @aliases schp
   */
  public function doUpdate() {
    $this->publishCron->doUpdate();
    $this->logger()->notice(t('Scheduled publish updates done.'));
  }
}
