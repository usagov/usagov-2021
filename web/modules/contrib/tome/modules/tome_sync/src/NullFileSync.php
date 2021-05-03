<?php

namespace Drupal\tome_sync;

use Drupal\file\FileInterface;

/**
 * Implements all file sync methods as no-ops.
 *
 * This is useful for sites that do not store files in Git, or want to
 * implement their own syncing strategy for files.
 *
 * @internal
 */
class NullFileSync implements FileSyncInterface {

  /**
   * {@inheritdoc}
   */
  public function importFiles() {}

  /**
   * {@inheritdoc}
   */
  public function deleteExportDirectory() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exportFile(FileInterface $file) {}

  /**
   * {@inheritdoc}
   */
  public function deleteFileExport(FileInterface $file) {}

  /**
   * {@inheritdoc}
   */
  public function deleteFile($filename) {}

}
