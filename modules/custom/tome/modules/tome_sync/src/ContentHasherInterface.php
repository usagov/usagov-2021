<?php

namespace Drupal\tome_sync;

/**
 * Interface for classes that can hash normalized content.
 */
interface ContentHasherInterface {

  /**
   * Writes a content hash.
   *
   * @param string $encoded_content
   *   The encoded content, typically JSON.
   * @param string $content_name
   *   The content name.
   */
  public function writeHash($encoded_content, $content_name);

  /**
   * Deletes a content hash.
   *
   * @param string $content_name
   *   The content name.
   */
  public function deleteHash($content_name);

  /**
   * Get a list of changed content.
   *
   * @return array
   *   An array of arrays of content names, keyed by the kind of change:
   *   - deleted
   *   - added
   *   - modified
   */
  public function getChangelist();

  /**
   * Validates that hashes exist.
   *
   * @return bool
   *   Whether or not any hashes exist.
   */
  public function hashesExist();

}
