<?php

namespace Drupal\tome_sync;

use Drupal\Core\Site\Settings;
use Drupal\file\FileInterface;
use Drupal\tome_base\PathTrait;
use Drupal\Core\Config\StorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\FileSecurity\FileSecurity;
use Drupal\Core\StreamWrapper\StreamWrapperManager;

/**
 * Handles file import and exports by keeping a file export directory in sync.
 *
 * @internal
 */
class FileSync implements FileSyncInterface {

  use PathTrait;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Creates an FileSync object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function importFiles() {
    $file_directory = $this->getFileDirectory();
    if (!is_dir($file_directory)) {
      return;
    }
    /** @var \Drupal\file\FileInterface $file */
    foreach ($this->fileSystem->scanDirectory($file_directory, '/.*/') as $file) {
      $destination = 'public://' . ltrim(str_replace($file_directory, '', $file->uri), '/');
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->copy($file->uri, $destination, FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteExportDirectory() {
    $file_directory = $this->getFileDirectory();
    if (file_exists($file_directory)) {
      if (!$this->fileSystem->deleteRecursive($file_directory)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exportFile(FileInterface $file) {
    $this->ensureFileDirectory();
    $file_directory = $this->getFileDirectory();
    if (strpos($file->getFileUri(), 'public://') === 0 && file_exists($file->getFileUri())) {
      $destination = $this->joinPaths($file_directory, StreamWrapperManager::getTarget($file->getFileUri()));
      $directory = dirname($destination);
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
      $this->fileSystem->copy($file->getFileUri(), $destination, FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFileExport(FileInterface $file) {
    $file_directory = $this->getFileDirectory();
    if (strpos($file->getFileUri(), 'public://') === 0) {
      $path = $this->joinPaths($file_directory, StreamWrapperManager::getTarget($file->getFileUri()));
      if (file_exists($path)) {
        $this->fileSystem->delete($path);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFile($filename) {
    $path = $this->joinPaths($this->getFileDirectory(), $filename);
    if (file_exists($path)) {
      $this->fileSystem->delete($path);
    }
  }

  /**
   * Gets the file directory.
   *
   * @return string
   *   The file directory.
   */
  protected function getFileDirectory() {
    return Settings::get('tome_files_directory', '../files') . '/public';
  }

  /**
   * Ensures that the file directory exists.
   */
  protected function ensureFileDirectory() {
    $file_directory = $this->getFileDirectory();
    $this->fileSystem->prepareDirectory($file_directory, FileSystemInterface::CREATE_DIRECTORY);
    FileSecurity::writeHtaccess($file_directory);
    if (!file_exists($file_directory)) {
      throw new StorageException('Failed to create config directory ' . $file_directory);
    }
  }

}
