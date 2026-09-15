<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drush\Attributes\Command;
use Drush\Attributes\Usage;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for static site generation preflight checks.
 */
final class StaticPreflightCommands extends DrushCommands {

  public function __construct(
    private StateInterface $state,
    private Connection $database,
  ) {
    parent::__construct();
  }

  /**
   * Return static site generation preflight data as JSON.
   */
  #[Command(name: 'usagov:ssg-preflight', aliases: ['ssg-preflight'])]
  #[Usage(name: 'drush usagov:ssg-preflight', description: 'Print Tome disabled state, maintenance mode, and recent content changes as JSON.')]
  public function preflight(): void {
    $payload = [
      'tome_run_disabled' => $this->state->get('usagov.tome_run_disabled', ''),
      'maintenance_mode' => $this->state->get('system.maintenance_mode', ''),
      'content_updated' => (int) $this->database->query($this->contentUpdatedQuery())->fetchField(),
    ];

    $this->output()->writeln(json_encode($payload, JSON_THROW_ON_ERROR));
  }

  private function contentUpdatedQuery(): string {
    return "SELECT SUM(c) FROM ( (SELECT count(*) as c from node_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from block_content_field_data where changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from taxonomy_term_field_data WHERE changed > (UNIX_TIMESTAMP(now())-(1800)))
 UNION ( SELECT count(*) as c from config_pages WHERE changed > (UNIX_TIMESTAMP(now())-(1800)))) as x";
  }

}
