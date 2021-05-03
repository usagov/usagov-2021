<?php

namespace Drupal\tome_sync;

use Drupal\Core\Config\FileStorage;

/**
 * Defines the JSON file storage.
 *
 * @internal
 */
class JsonFileStorage extends FileStorage {

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'json';
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return \Drupal::service('serializer')->encode($data, 'json', ['json_encode_options' => JSON_PRETTY_PRINT]);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($data) {
    return \Drupal::service('serializer')->decode($data, 'json');
  }

}
