<?php

namespace Drupal\tome_static;

use Drupal\Core\Plugin\Context\LazyContextRepository;

/**
 * Decorates the context repository to allow for resetting of contexts.
 *
 * @internal
 */
class LazyResettableContextRepository extends LazyContextRepository {

  /**
   * Resets the context cache.
   */
  public function resetCache() {
    $this->contexts = [];
  }

}
