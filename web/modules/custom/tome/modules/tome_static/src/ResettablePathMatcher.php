<?php

namespace Drupal\tome_static;

use Drupal\Core\Path\PathMatcher;

/**
 * Decorates the path matcher to allow for resetting of static cache.
 *
 * @internal
 */
class ResettablePathMatcher extends PathMatcher {

  /**
   * Resets the static.
   */
  public function resetCache() {
    $this->isCurrentFrontPage = NULL;
  }

}
