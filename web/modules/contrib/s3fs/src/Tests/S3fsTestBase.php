<?php

namespace Drupal\s3fs\Tests;

use Drupal\Core\Config\Config;
use Drupal\Tests\BrowserTestBase;

/**
 * S3 File System Test Base.
 *
 * Provides a base for BrowserTest to execute against.
 *
 * The AWS credentials must be configured in prepareConfig() or using
 * environment variables because settings.php does not get executed
 * by BrowserTestBase.
 *
 * Environment variables available for configuration:
 *   S3FS_AWS_KEY - AWS IAM user key
 *   S3FS_AWS_SECRET - AWS IAM secret
 *   S3FS_AWS_BUCKET - Name of S3 bucket
 *   S3FS_AWS_REGION - Region of bucket.
 *
 * @group s3fs
 */
abstract class S3fsTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['s3fs'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The s3fs module config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The AWS SDK for PHP S3Client object.
   *
   * @var \Aws\S3\S3Client
   */
  protected $s3;

  /**
   * The AWS SDK for PHP S3Client object.
   *
   * @var \Drupal\S3fs\S3fsService
   */
  protected $s3fs;

  /**
   * S3 Credentials provided and bucket exists.
   *
   * @var bool
   */
  protected $bucketNotFound = FALSE;

  /**
   * Folder name to use for placing tests files.
   *
   * @var string
   */
  protected $remoteTestsFolder = '_s3fs_tests';

  /**
   * Full base key path for tests folder.
   *
   * @var string
   */
  protected $remoteTestsFolderKey = '';

  /**
   * URI for accessing the data via StreamWrapper.
   *
   * @var string
   */
  protected $remoteTestsFolderUri = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->prepareConfig($this->config('s3fs.settings'));
    if (empty($this->config['access_key']) || empty($this->config['secret_key']) || empty($this->config['bucket'])) {
      // No sense to test anything if credentials absent.
      $this->bucketNotFound = TRUE;
      $this->markTestSkipped('S3 not configured');
    }

    $this->s3fs = \Drupal::service('s3fs');

    $this->connection = $this->container->get('database');
    $this->s3 = $this->s3fs->getAmazonS3Client($this->config);
    if (!empty($this->config['root_folder'])) {
      $this->remoteTestsFolderKey = "{$this->config['root_folder']}/$this->remoteTestsFolder";
    }
    else {
      $this->remoteTestsFolderKey = $this->remoteTestsFolder;
    }
    $this->remoteTestsFolderUri = "s3://{$this->remoteTestsFolder}";
    $this->bucketNotFound = !$this->s3->doesBucketExist($this->config['bucket']);
    if (!$this->bucketNotFound) {
      // Empty out the bucket before the test, to prevent unexpected errors.
      $this->s3->deleteMatchingObjects($this->config['bucket'], $this->remoteTestsFolderKey);
      $this->verbose("Deleted file(s) from S3 test folder to prepare for the test.");
    }
    else {
      // No need to continue tests if can't access the bucket. Either the
      // credentials are incorrect or problem with S3Client.
      $this->fail("Unable to access bucket '{$this->config['bucket']}' in region '{$this->config['region']}'.
          Please verify the S3 settings.");
    }
  }

  /**
   * Clean up S3 folder.
   */
  protected function tearDown(): void {
    if (!$this->bucketNotFound) {
      $this->s3->deleteMatchingObjects($this->config['bucket'], $this->remoteTestsFolderKey);
    }
    parent::tearDown();
  }

  /**
   * Converts s3fs config to an array.
   *
   * @param \Drupal\Core\Config\Config $config
   *   A s3fs.settings config object.
   */
  protected function prepareConfig(Config $config) {
    $this->config = [];

    if (getenv('S3FS_AWS_KEY')) {
      $config->set('access_key', getenv('S3FS_AWS_KEY'))->save();
    }
    if (getenv('S3FS_AWS_SECRET')) {
      $config->set('secret_key', getenv('S3FS_AWS_SECRET'))->save();
    }
    if (getenv('S3FS_AWS_BUCKET')) {
      $config->set('bucket', getenv('S3FS_AWS_BUCKET'))->save();
    }
    if (getenv('S3FS_AWS_REGION')) {
      $config->set('region', getenv('S3FS_AWS_REGION'))->save();
    }

    /*
    // Set the real credentials here.
    $config->set('access_key', '')
    ->set('secret_key', '')
    ->set('bucket', '')
    ->set('region', 'us-east-1')
    ->save();
     */

    foreach ($config->get() as $prop => $value) {
      $this->config[$prop] = $value;
    }
  }

}
