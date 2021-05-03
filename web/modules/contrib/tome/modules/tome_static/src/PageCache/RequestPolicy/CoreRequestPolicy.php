<?php

namespace Drupal\tome_static\PageCache\RequestPolicy;

use Drupal\Core\PageCache\ChainRequestPolicy;
use Drupal\Core\PageCache\RequestPolicy\NoSessionOpen;
use Drupal\Core\Session\SessionConfigurationInterface;

/**
 * Allows CLI page requests to be cached.
 *
 * @see \Drupal\Core\PageCache\DefaultRequestPolicy
 *
 * @internal
 */
class CoreRequestPolicy extends ChainRequestPolicy {

  /**
   * Constructs the CoreRequestPolicy object.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(SessionConfigurationInterface $session_configuration) {
    $this->addPolicy(new UnsafeMethod());
    $this->addPolicy(new NoSessionOpen($session_configuration));
  }

}
