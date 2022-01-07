<?php

namespace Drupal\tome_sync;

use Drupal\Core\Site\Settings;

/**
 * Provides a factory for creating json file storage objects.
 *
 * @internal
 */
class JsonFileStorageFactory {

  /**
   * Returns a JsonFileStorage object.
   *
   * @return \Drupal\tome_sync\JsonFileStorage
   *   The JSON file storage.
   */
  public static function getContent() {
    return new JsonFileStorage(Settings::get('tome_content_directory', '../content'));
  }

}
