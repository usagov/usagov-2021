<?php

namespace Drupal\tome_static;

/**
 * Provides an interface for the static generator.
 */
interface StaticGeneratorInterface {

  /**
   * The key user interfaces should use to get/set if they're running a build.
   */
  const STATE_KEY_BUILDING = 'tome_static.building';

  /**
   * The latest build URL in order to make it easier the next time.
   */
  const STATE_KEY_URL = 'tome_static.url';

  /**
   * The request attribute key used to flag requests coming from the build.
   */
  const REQUEST_KEY = '_tome_static_request';

  /**
   * Gets all uncached public-facing paths for the site.
   *
   * Entity paths will be returned in the format
   * "_entity:entity_type_id:langcode:entity_id" and should be resolved by the
   * caller using a batch process.
   *
   * @return string[]
   *   An array of paths.
   */
  public function getPaths();

  /**
   * Requests and exports a given path.
   *
   * This method should only be called for paths that need a Drupal bootstrap.
   *
   * Note that calling this method multiple times in one bootstrap may have
   * result in improper renders - it's recommended that this is called once
   * per request.
   *
   * @param string $path
   *   A path to export, or an entity to load and export in the format
   *   "_entity:entity_type_id:langcode:entity_id".
   *
   * @return string[]
   *   An array of paths that should be passed to ::exportPaths.
   */
  public function requestPath($path);

  /**
   * Exports multiple paths.
   *
   * If a path is a file that already exists, it will be immediately copied. If
   * a path needs to be fetched in a new request, it will be returned.
   *
   * @param string[] $paths
   *   An array of paths to export.
   *
   * @return string[]
   *   An array of paths that need to be fetched in new requests. Usually these
   *   are image style derivatives.
   */
  public function exportPaths(array $paths);

  /**
   * Cleans up old files from the static directory.
   */
  public function cleanupStaticDirectory();

  /**
   * Gets the static directory.
   *
   * @return string
   *   The static directory.
   */
  public function getStaticDirectory();

  /**
   * Prepares the static directory for a fresh export.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function prepareStaticDirectory();

}
