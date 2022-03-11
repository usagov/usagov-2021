<?php

namespace Drupal\tome_sync;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\FileStorage;

/**
 * Hashes normalized content in the database.
 */
class ContentHasher implements ContentHasherInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The content storage.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $storage;

  /**
   * Creates a ContentHasher object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\FileStorage $storage
   *   The content storage.
   */
  public function __construct(Connection $database, FileStorage $storage) {
    $this->database = $database;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function writeHash($encoded_content, $content_name) {
    $hash = sha1($encoded_content);

    $this->database->upsert('tome_sync_content_hash')
      ->key('name')
      ->fields([
        'name' => $content_name,
        'hash' => $hash,
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteHash($content_name) {
    $query = $this->database->delete('tome_sync_content_hash');
    $query->condition('name', $content_name);
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getChangelist() {
    $content_hashes = $this->getContentHashes();
    $source_content_hashes = $this->getSourceContentHashes();
    return [
      'modified' => array_keys(array_diff(array_intersect_key($content_hashes, $source_content_hashes), $source_content_hashes)),
      'added' => array_keys(array_diff_key($source_content_hashes, $content_hashes)),
      'deleted' => array_keys(array_diff_key($content_hashes, $source_content_hashes)),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function hashesExist() {
    return !empty($this->getContentHashes());
  }

  /**
   * Gets the content hashes from the source (filesystem).
   *
   * @return array
   *   An associative array mapping content names to hashes.
   */
  protected function getSourceContentHashes() {
    $source_content_hashes = [];
    foreach ($this->storage->listAll() as $name) {
      $source_content_hashes[$name] = sha1_file($this->storage->getFilePath($name));
    }
    return $source_content_hashes;
  }

  /**
   * Gets the content hashes from the database.
   *
   * @return array
   *   An associative array mapping content names to hashes.
   */
  protected function getContentHashes() {
    return $this->database->select('tome_sync_content_hash')
      ->fields('tome_sync_content_hash', ['name', 'hash'])
      ->execute()
      ->fetchAllKeyed(0);
  }

}
