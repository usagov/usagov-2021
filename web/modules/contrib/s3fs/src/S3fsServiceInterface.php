<?php

namespace Drupal\s3fs;

/**
 * S3fs service interface.
 */
interface S3fsServiceInterface {

  /**
   * Validate the S3fs config.
   *
   * @param $config
   *   Array of configuration settings from which to configure the client.
   *
   * @return array
   *   Empty array if configuration is valid, errors array otherwise.
   */
  function validate(array $config);

  /**
   * Sets up the S3Client object.
   *
   * @param $config
   *   Array of configuration settings from which to configure the client.
   *
   * @return \Aws\S3\S3Client
   *   The fully-configured S3Client object.
   *
   * @throws \Drupal\s3fs\S3fsException
   */
  function getAmazonS3Client(array $config);

  /**
   * Refreshes the metadata cache.
   *
   * Iterates over the full list of objects in the s3fs_root_folder within S3
   * bucket (or the entire bucket, if no root folder has been set), caching
   * their metadata in the database.
   *
   * It then caches the ancestor folders for those files, since folders are not
   * normally stored as actual objects in S3.
   *
   * @param array $config
   *   An s3fs configuration array.
   */
  function refreshCache(array $config);

  /**
   * Convert file metadata returned from S3 into a metadata cache array.
   *
   * @param string $uri
   *   The uri of the resource.
   * @param array $s3_metadata
   *   An array containing the collective metadata for the object in S3.
   *   The caller may send an empty array here to indicate that the returned
   *   metadata should represent a directory.
   *
   * @return array
   *   A file metadata cache array.
   */
  function convertMetadata($uri, array $s3_metadata);

}
