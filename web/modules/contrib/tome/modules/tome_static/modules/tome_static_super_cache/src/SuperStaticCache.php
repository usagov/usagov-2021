<?php

namespace Drupal\tome_static_super_cache;

use Drupal\tome_static\StaticCache;

/**
 * Decorates the Tome Static cache service to prevent deletions.
 */
class SuperStaticCache extends StaticCache {

  /**
   * The global key to flag a full rebuild.
   *
   * @var string
   */
  const FULL_REBUILD_KEY = 'tome_static_super_cache_full_rebuild';

  /**
   * The global key to flag a normal rebuild.
   *
   * @var string
   */
  const REBUILD_KEY = 'tome_static_super_cache_rebuild';

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    if (isset($GLOBALS[self::FULL_REBUILD_KEY])) {
      parent::deleteAll();
    }
  }

}
