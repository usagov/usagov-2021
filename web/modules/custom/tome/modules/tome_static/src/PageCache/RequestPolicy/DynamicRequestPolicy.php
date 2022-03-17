<?php

namespace Drupal\tome_static\PageCache\RequestPolicy;

use Drupal\Core\PageCache\ChainRequestPolicy;

/**
 * Allows CLI page requests to be cached.
 *
 * @see \Drupal\dynamic_page_cache\PageCache\RequestPolicy\DefaultRequestPolicy
 *
 * @internal
 */
class DynamicRequestPolicy extends ChainRequestPolicy {

  /**
   * Constructs the DefaultRequestPolicy object.
   */
  public function __construct() {
    $this->addPolicy(new UnsafeMethod());
  }

}
