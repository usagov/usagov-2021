<?php

namespace Drupal\tome_sync;

use Drupal\file\FileInterface;

/**
 * Abstractly handles file import and exports.
 */
interface FileSyncInterface {

  /**
   * Imports all files from the file directory.
   */
  public function importFiles();

  /**
   * Deletes the file export directory.
   *
   * @return bool
   *   Whether or not the deletion was successful.
   */
  public function deleteExportDirectory();

  /**
   * Exports a file to the export directory.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   */
  public function exportFile(FileInterface $file);

  /**
   * Deletes an exported file by entity.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file entity.
   */
  public function deleteFileExport(FileInterface $file);

  /**
   * Deletes an exported file by name.
   *
   * @param string $filename
   *   The file name.
   */
  public function deleteFile($filename);

}
