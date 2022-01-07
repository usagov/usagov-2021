<?php

namespace Drupal\tome_base;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Contains methods useful for finding the current executable.
 *
 * @internal
 */
trait ExecutableFinderTrait {

  /**
   * Finds an executable string for the current process.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   The command input.
   *
   * @return string
   *   An executable string, i.e. "drush @foo.bar" or "./vendor/bin/drupal".
   */
  protected function findExecutable(InputInterface $input) {
    $args = [];
    foreach ($_SERVER['argv'] as $arg) {
      if ($arg === $input->getFirstArgument()) {
        break;
      }
      if (strpos($arg, '--backend') !== 0) {
        $args[] = $arg;
      }
    }
    if (isset($_SERVER['PWD']) && !is_file($args[0]) && is_file($_SERVER['PWD'] . '/' . $args[0])) {
      $args[0] = $_SERVER['PWD'] . '/' . $args[0];
    }
    return implode(' ', $args);
  }

}
