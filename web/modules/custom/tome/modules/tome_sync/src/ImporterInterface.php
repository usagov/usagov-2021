<?php

namespace Drupal\tome_sync;

/**
 * Provides an interface for the importer.
 */
interface ImporterInterface {

  /**
   * The key user interfaces should use to see if they're running an import.
   */
  const STATE_KEY_IMPORTING = 'tome_sync.importing';

  /**
   * Imports a content entity from the source storage.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $uuid
   *   The entity UUID.
   * @param string $langcode
   *   (optional) The langcode, if this is a translation.
   */
  public function importContent($entity_type_id, $uuid, $langcode = NULL);

  /**
   * Gets chunked arrays of content names to import.
   *
   * Chunks should be imported synchronously, but within each chunk any amount
   * of concurrency should be acceptable.
   *
   * @return array
   *   An array of arrays of content names to import.
   */
  public function getChunkedNames();

  /**
   * Gets or sets importing state.
   *
   * @param bool $importing
   *   The importing state to set.
   *
   * @return bool
   *   Returns TRUE if importing, FALSE otherwise.
   */
  public function isImporting($importing = NULL);

  /**
   * Imports all files from the file directory.
   */
  public function importFiles();

}
