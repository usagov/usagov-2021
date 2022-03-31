<?php

namespace Drupal\tome_static\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wraps an array of paths that will be processed by the static generator.
 *
 * Metadata for paths can also be provided, which can be used to pass data
 * between event subscribers.
 *
 * If your event subscriber modifies a path, you should set the "original_path"
 * metadata to the untransformed path, if the original path is not already set.
 *
 * If your event subscriber adds a path that is already multilingual, you
 * should set the "language_processed" metadata to any value, and the
 * "langcode" metadata to the language for the path.
 */
class CollectPathsEvent extends Event {

  /**
   * An array mapping path names to metadata.
   *
   * @var array
   */
  protected $paths;

  /**
   * Constructs a CollectPathsEvent object.
   *
   * @param array $paths
   *   An array of paths.
   */
  public function __construct(array $paths) {
    $this->paths = array_fill_keys($paths, []);
  }

  /**
   * Gets the paths.
   *
   * @param bool $with_metadata
   *   Whether or not metadata should be returned. Defaults to FALSE.
   *
   * @return array
   *   An array of paths, or an array mapping path names to metadata.
   */
  public function getPaths($with_metadata = FALSE) {
    return $with_metadata ? $this->paths : array_keys($this->paths);
  }

  /**
   * Adds a single path.
   *
   * @param string $path
   *   A path to add.
   * @param array $metadata
   *   (Optional) Metadata for this path.
   */
  public function addPath($path, array $metadata = []) {
    $this->paths[$path] = $metadata;
  }

  /**
   * Adds multiple paths.
   *
   * @param array $paths
   *   An array of paths, or an array mapping path names to metadata.
   */
  public function addPaths(array $paths) {
    foreach ($paths as $key => $value) {
      if (is_array($value)) {
        $this->paths[$key] = $value;
      }
      else {
        $this->paths[$value] = [];
      }
    }
  }

  /**
   * Replaces a single path.
   *
   * @param string $path
   *   The path to replace.
   * @param string $new_path
   *   The new path.
   * @param array $metadata
   *   (Optional) Metadata for this path.
   */
  public function replacePath($path, $new_path, array $metadata = []) {
    if (isset($this->paths[$path])) {
      unset($this->paths[$path]);
      $this->paths[$new_path] = $metadata;
    }
  }

  /**
   * Replaces all paths.
   *
   * @param array $paths
   *   An array of paths, or an array mapping path names to metadata.
   */
  public function replacePaths(array $paths) {
    $this->paths = [];
    $this->addPaths($paths);
  }

  /**
   * Deletes a single path.
   *
   * @param string $path
   *   A path to delete.
   */
  public function deletePath($path) {
    unset($this->paths[$path]);
  }

}
