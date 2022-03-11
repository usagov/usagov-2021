<?php

namespace Drupal\tome_sync;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for the exporter.
 */
interface ExporterInterface {

  /**
   * Grabs a list of content to export.
   *
   * @return array
   *   An array mapping entity type IDs to arrays of entity IDs to export.
   */
  public function getContentToExport();

  /**
   * Deletes all content and files from the export directories.
   *
   * @return bool
   *   Whether or not the deletion was successful.
   */
  public function deleteExportDirectories();

  /**
   * Exports a content entity to the target storage.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   An entity to export.
   */
  public function exportContent(ContentEntityInterface $entity);

  /**
   * Deletes an exported content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   An entity to delete.
   */
  public function deleteContentExport(ContentEntityInterface $entity);

}
