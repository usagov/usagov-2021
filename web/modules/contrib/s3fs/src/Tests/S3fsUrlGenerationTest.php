<?php

namespace Drupal\s3fs\Tests;

use Drupal\Tests\BrowserTestBase;

/**
 * S3 File System Tests.
 *
 * Ensure that the remote file system functionality provided by S3 File System
 * works correctly.
 *
 * The AWS credentials must be configured in prepareConfig() because
 * settings.php, which does not get executed during a BrowserTestBase.
 *
 * @group s3fs
 */
class S3fsUrlGenerationTest extends BrowserTestBase {

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
  protected $config;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('s3fs.settings')
      ->set('presigned_urls', "600|signreq/*\n300|shortsignttl/*")
      ->set('saveas', '.*saveas/*')
      ->set('torrents', '.*tordir/*')
      ->set('bucket', '513ec7bfc9ac489781a764057973d870')
      ->set('region', 'us-east-1')
      ->set('access_key', 'BogusAccessKey')
      ->set('secret_key', 'BogusSecretKey')
      ->save();
  }

  /**
   * Test default link generation.
   */
  public function testDefaultUriGeneration() {
    $urlBase = 'http://513ec7bfc9ac489781a764057973d870.s3.amazonaws.com';
    $this->runTests($urlBase);
  }

  /**
   * Test defaults secure link generation.
   */
  public function testHttpsDefaultUriGeneration() {
    $urlBase = 'https://513ec7bfc9ac489781a764057973d870.s3.amazonaws.com';
    $this->config('s3fs.settings')->set('use_https', TRUE)->save();
    $this->runTests($urlBase);
  }

  /**
   * Test default link generation using a different region.
   */
  public function testDefaultDifferentRegion() {
    $urlBase = 'http://513ec7bfc9ac489781a764057973d870.s3.us-east-2.amazonaws.com';
    $this->config('s3fs.settings')->set('region', 'us-east-2')->save();
    $this->runTests($urlBase);
  }

  /**
   * Test Custom Hostname link generation.
   */
  public function testCustomHostname() {
    $urlBase = 'http://test.example.org';

    $this->config('s3fs.settings')
      ->set('use_cname', TRUE)
      ->set('domain', 'test.example.org')
      ->save();

    $this->runTests($urlBase);

  }

  /**
   * Test Custom Hostname with port link generation.
   */
  public function testCustomHostnameWithPort() {
    $urlBase = 'http://test.example.org:8080';

    $this->config('s3fs.settings')
      ->set('use_cname', TRUE)
      ->set('domain', 'test.example.org:8080')
      ->save();

    $this->runTests($urlBase);

  }

  /**
   * Test custom hostname secure link generation.
   */
  public function testHttpsCustomHostname() {
    $urlBase = 'https://test.example.org';

    $this->config('s3fs.settings')->set('use_https', TRUE)->save();

    $this->config('s3fs.settings')
      ->set('use_cname', TRUE)
      ->set('domain', 'test.example.org')
      ->save();

    $this->runTests($urlBase);
  }

  /**
   * Test Custom hostname using a different region.
   */
  public function testCustomHostnameDifferentRegion() {
    $urlBase = 'http://test.example.org';

    $this->config('s3fs.settings')
      ->set('use_cname', TRUE)
      ->set('domain', 'test.example.org')
      ->set('region', 'us-east-2')
      ->save();

    $this->runTests($urlBase);
  }

  /**
   * Test using a path based endpoint bucket.
   */
  public function testPathBasedEndpoint() {
    $urlBase = 'http://s3.amazonaws.com/513ec7bfc9ac489781a764057973d870';
    $this->config('s3fs.settings')->set('use_path_style_endpoint', TRUE)->save();

    $this->runTests($urlBase);
  }

  /**
   * Test using a root folder.
   */
  public function testWithRootFolder() {
    $urlBase = 'http://513ec7bfc9ac489781a764057973d870.s3.amazonaws.com/MyRootFolder';
    $this->config('s3fs.settings')->set('root_folder', 'MyRootFolder')->save();

    $this->runTests($urlBase);
  }

  /**
   * Execute common tests.
   *
   * @param string $urlBase
   *   Base path including scheme that links are expected to include.
   */
  protected function runTests(string $urlBase) {
    $publicFile = 's3://public.txt';
    $signedLongFile = 's3://signreq/signed.txt';
    $signedShortFile = 's3://shortsignttl/shortsigned.txt';
    $torrentWorksFile = 's3://tordir/thisworks.txt';
    $torrentFail = 's3://signreq/tordir/thiswontwork.txt';
    $forcedSaveFile = 's3://saveas/forcedsave.txt';
    $forcedSavePresignFile = 's3://signreq/saveas/alsoforcesaved.txt';

    $publicFileUri = file_create_url($publicFile);
    $this->assertEquals($urlBase . '/public.txt', $publicFileUri, 'Public request as expected');

    // Presigned URL.
    $signedLongUri = file_create_url($signedLongFile);
    $this->assertStringContainsString('X-Amz-Signature', $signedLongUri, 'Signed request contains a signature');
    $this->assertStringContainsString('X-Amz-SignedHeaders=host', $signedLongUri, 'Host is part of signed request');
    $this->assertStringContainsString('X-Amz-Expires=600', $signedLongUri, 'Signed for 600 Seconds');
    // @todo Calculate a signature ourselves based on the URL to see if it is correct.
    $this->assertStringContainsString('X-Amz-Expires=300', file_create_url($signedShortFile), 'Signed for 300 seconds');

    // Torrent based download.
    $this->assertEquals($urlBase . '/tordir/thisworks.txt?torrent', file_create_url($torrentWorksFile), 'Download via torrent');
    $this->assertStringNotContainsString('torrent', file_create_url($torrentFail), 'Signed URLS can not use torrent download');

    // Save URLS as file.
    $forcedSaveUri = file_create_url($forcedSaveFile);
    $this->assertStringContainsString('X-Amz-Signature', $forcedSaveUri, 'Forced save request contains a signature');
    $this->assertStringContainsString('response-content-disposition=attachment', $forcedSaveUri, 'Forced save includes content-disposition header');

    $forcedSavePresignUri = file_create_url($forcedSavePresignFile);
    $this->assertStringContainsString('X-Amz-Signature', $forcedSavePresignUri, 'Forced Save on a presign contains a signature');
    $this->assertStringContainsString('response-content-disposition=attachment', $forcedSavePresignUri, 'Forced Save with forced presign still includes content-disposition');

  }

}
