<?php

namespace Drupal\tome_static;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Determines if pages are statically cached.
 */
interface StaticCacheInterface {

  /**
   * Filters paths that are uncached from a given array.
   *
   * @param string $base_url
   *   The base scheme/host for this request.
   * @param array $original_paths
   *   An array of paths.
   *
   * @returns array
   *   An array of uncached paths.
   */
  public function filterUncachedPaths($base_url, array $original_paths);

  /**
   * Adds to the Tome cache.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object.
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   A response object that should be stored in the page cache.
   * @param string $original_path
   *   The original, placeholdered path.
   * @param string $destination
   *   The path to the saved static file.
   *
   * @returns bool
   *   Whether or not adding to the cache was successful.
   */
  public function setCache(Request $request, Response $response, $original_path, $destination);

  /**
   * Gets files that are in cache but invalid, and can be deleted.
   *
   * The caller of this method must immediately act on or store returned expired
   * files, as they are removed from cache storage after being returned.
   *
   * @return array
   *   Files that have been expired.
   */
  public function getExpiredFiles();

  /**
   * Checks if the cache is empty.
   *
   * @return bool
   *   Whether or not the cache is empty.
   */
  public function isCacheEmpty();

}
