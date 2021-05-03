<?php

namespace Drupal\s3fs\Tests;

/**
 * S3fs form validation tests.
 *
 * Ensure that the form validatior functions as designed.
 *
 * The AWS credentials must be configured in prepareConfig() because
 * settings.php, is not executed when using BrowserTestBase.
 *
 * @group s3fs
 */
class S3fsConfigValidationTest extends S3fsTestBase {

  /**
   * The config factory object.
   *
   * We should be able to get this at $config but its been overridden
   * in S3FsTestBase.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['s3fs'];

  /**
   * Coverage test for region configuration.
   */
  public function testRegionDetected() {

    $s3Config = $this->config;
    $s3CustomConfig = $this->config;

    // Set the region incorrectly so we can verify it changes before
    // it reaches the getAmazonS3Client() call.
    if ($s3Config['region'] == 'us-east-1') {
      $s3CustomConfig['region'] = 'us-east-2';
    }
    else {
      $s3CustomConfig['region'] = 'us-east-1';
    }
    $this->assertEmpty($this->s3fs->validate($s3CustomConfig));
    $this->assertEquals($s3Config['region'], $this->configFactory->get('s3fs.settings')->get('region'));

    // @todo Be able to test when using a custom_host.
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->configFactory = \Drupal::service('config.factory');

    // Write the access key settings into the config.
    $settings = [];
    $settings['settings']['s3fs.access_key'] = (object) [
      'value' => $this->config['access_key'],
      'required' => TRUE,
    ];
    $settings['settings']['s3fs.secret_key'] = (object) [
      'value' => $this->config['secret_key'],
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

}
