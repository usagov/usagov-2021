<?php

namespace Drupal\tome_static_super_cache\Plugin\views\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\views\Plugin\views\cache\CachePluginBase;
use Drupal\views\ViewExecutable;

/**
 * Omits list cache tags from a View and expires cache on entity CRUD.
 *
 * @ViewsCache(
 *   id = "tome_static_super_cache_smart_tag",
 *   title = @Translation("Smart tag based"),
 *   help = @Translation("Tag based caching of data, where entity CRUD is used for expiry instead of list cache tags.")
 * )
 */
class SmartTag extends CachePluginBase {

  /**
   * Determines whether or not cache should be used. Defaults to TRUE.
   *
   * @var bool
   */
  protected $cache = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = $this->view->storage->getCacheTags();
    $tags = Cache::mergeTags($tags, $this->view->getQuery()->getCacheTags());
    $tags = Cache::mergeTags($tags, [$this->getTagForView($this->view)]);
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Smart tag based');
  }

  /**
   * {@inheritdoc}
   */
  protected function cacheExpire($type) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultCacheMaxAge() {
    return CacheBackendInterface::CACHE_PERMANENT;
  }

  /**
   * Determines the cache tag for the given View.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   A View executable.
   *
   * @return string
   *   The cache tag for the given View.
   */
  public function getTagForView(ViewExecutable $view) {
    return implode(':', [
      'tome_static_super_cache_smart_tag',
      $view->id(),
      $view->current_display,
    ]);
  }

  /**
   * Disables cache.
   */
  public function disableCache() {
    $this->cache = FALSE;
  }

  /**
   * Enables cache.
   */
  public function enableCache() {
    $this->cache = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGet($type) {
    if ($this->cache) {
      return parent::cacheGet($type);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheSet($type) {
    if ($this->cache) {
      parent::cacheSet($type);
    }
  }

}
