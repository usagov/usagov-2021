<?php

namespace Drupal\s3fs\StreamWrapper;

/**
 * Defines a Drupal s3fs stream wrapper class for use with private scheme.
 *
 * Provides support for storing files on the amazon s3 file system with the
 * Drupal file interface.
 */
class PrivateS3fsStream extends S3fsStream {

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->t('Private files (s3fs)');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Private files served from Amazon S3.');
  }

}
