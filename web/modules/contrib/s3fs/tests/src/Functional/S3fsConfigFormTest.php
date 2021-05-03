<?php

namespace Drupal\Tests\s3fs\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;


/**
 * Tests s3fs configuration form.
 *
 * @group s3fs
 */
class S3fsConfigFormTest extends BrowserTestBase {

  use StringTranslationTrait;

/**
   * A user with administration access.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['s3fs'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer s3fs',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test the S3fs config form.
   */
  public function testS3fsConfigurationForm() {
    $edit['bucket'] = 's3fs-testing-bucket';
    $edit['use_cname'] = 1;
    $edit['domain'] = 'domaincheck.com';
    $edit['use_path_style_endpoint'] = 1;
    $edit['encryption'] = 'AES256';
    $edit['use_https'] = 1;
    $edit['root_folder'] = 'rootfoldercheck';
    $edit['presigned_urls'] = '60|private_files/*';
    $edit['saveas'] = 'video/*';
    $edit['torrents'] = 'big_files/*';
    $this->drupalPostForm('admin/config/media/s3fs', $edit, t('Save configuration'));
    $this->assertSession()->statusCodeEquals(200);
  }

}
