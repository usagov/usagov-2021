<?php

namespace Drupal\tome_base;

/**
 * Contains methods useful for working with paths.
 *
 * @internal
 */
trait PathTrait {

  /**
   * Joins multiple paths.
   *
   * Original credit to Riccardo Galli.
   *
   * @see https://stackoverflow.com/a/15575293
   *
   * @return string
   *   The joined path.
   */
  protected function joinPaths() {
    $paths = [];

    foreach (func_get_args() as $arg) {
      if ($arg !== '') {
        $paths[] = $arg;
      }
    }

    return rtrim(preg_replace('#(?<!:)/+#', '/', implode('/', $paths)), '/');
  }

}
