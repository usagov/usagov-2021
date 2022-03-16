<?php

namespace Drupal\tome_static\PageCache\RequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reject when the HTTP method is not safe.
 *
 * The policy denies caching if request method is neither GET nor HEAD (see RFC
 * 2616, section 9.1.1 - Safe Methods).
 *
 * @internal
 */
class UnsafeMethod implements RequestPolicyInterface {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    if (!$request->isMethodCacheable()) {
      return static::DENY;
    }
  }

}
