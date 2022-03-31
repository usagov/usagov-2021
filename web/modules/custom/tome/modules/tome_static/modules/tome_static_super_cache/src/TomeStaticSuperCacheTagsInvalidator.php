<?php

namespace Drupal\tome_static_super_cache;

use Drupal\Core\Cache\CacheTagsInvalidator;

/**
 * Decorates the core cache tags invalidator to ignore certain tags.
 */
class TomeStaticSuperCacheTagsInvalidator extends CacheTagsInvalidator {

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    if (!isset($GLOBALS[SuperStaticCache::FULL_REBUILD_KEY]) && isset($GLOBALS[SuperStaticCache::REBUILD_KEY])) {
      $skip_tags = [
        'http_response',
        'local_task',
      ];
      foreach ($tags as $i => $tag) {
        if (strpos($tag, 'config:system.menu') === 0 || in_array($tag, $skip_tags, TRUE)) {
          unset($tags[$i]);
        }
      }
    }
    if (!empty($tags)) {
      parent::invalidateTags($tags);
    }
  }

}
