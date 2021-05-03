<?php

namespace Drupal\s3fs\Batch;

/**
 * Interface S3fsFileMigrationBatchInterface.
 *
 * @package Drupal\s3fs\Batch
 */
interface S3fsFileMigrationBatchInterface {

  /**
   * Copies all the local files from the specified file system into S3.
   *
   * @param array $config
   *   An s3fs configuration array.
   * @param $scheme
   *   A variable defining which scheme (Public or Private) to copy.
   */
  public function execute(array $config, $scheme);

  /**
   * Scans a given directory.
   *
   * @param string $dir
   *   The directory to be scanned.
   *
   * @return array
   *   Array of file paths.
   */
  public function dirScan($dir);

  /**
   * Return batch definition.
   *
   * @return array
   *   Array of batch definition.
   */
  public function getBatch();

  /**
   * Batch operation callback that copy files to S3 File System.
   *
   * @param array   $file_paths
   *   Array with file paths to process.
   * @param integer $total
   *   Total number of files to process in batch.
   * @param string  $source_folder
   *   Folder from copy the file.
   * @param string  $target_folder
   *   Folder to copy the file.
   * @param string  $scheme
   *   Scheme from copy files. E.g.: public.
   * @param array   $context
   *   Batch context.
   */
  public static function copyOperation(array $file_paths, $total, $source_folder, $target_folder, $scheme, &$context);

  /**
   * Finished batch callback.
   *
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function finished($success, $results, $operations);

}
