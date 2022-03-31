<?php

namespace Drupal\tome_base\Commands;

use Consolidation\AnnotatedCommand\AnnotatedCommand;
use Consolidation\AnnotatedCommand\CommandData;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Contains logic for working around Drush bugs.
 *
 * @todo Remove when https://github.com/drush-ops/drush/pull/3770 is closed.
 *
 * @internal
 */
class CliWorkaroundCommands extends DrushCommands {

  /**
   * This tricks "drush php" into working for Symfony console commands.
   *
   * @hook pre-command php:cli
   */
  public function preCommand(CommandData $commandData) {
    foreach (Drush::getApplication()->all('tome') as $name => $command) {
      if (!($command instanceof AnnotatedCommand)) {
        $dupe_command = new AnnotatedCommand($name);
        $dupe_command->setDefinition($command->getDefinition());
        Drush::getApplication()->add($dupe_command);
      }
    }
  }

}
