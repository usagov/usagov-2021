<?php

namespace Drupal\tome_static\StackMiddleware;

use Drupal\page_cache\StackMiddleware\PageCache;

/**
 * Allows the cache ID to be reset between requests.
 *
 * @internal
 */
class ResettablePageCache extends PageCache {

  /**
   * {@inheritdoc}
   */
  protected $cid;

  /**
   * Resets the cache ID.
   */
  public function resetCache() {
    $this->cid = NULL;
  }

}
