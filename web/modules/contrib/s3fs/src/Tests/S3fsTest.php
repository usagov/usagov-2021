<?php

namespace Drupal\s3fs\Tests;

use Drupal\Component\Utility\UrlHelper;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;

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
class S3fsTest extends S3fsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['s3fs', 'image'];

  /**
   * Coverage test for the stream wrapper.
   */
  public function testStreamWrapperCoverage() {
    $test_uri1 = "{$this->remoteTestsFolderUri}/test_file1.txt";
    $test_uri2 = "{$this->remoteTestsFolderUri}/test_file2.txt";

    $this->assertTrue(\Drupal::service('stream_wrapper_manager')->isValidScheme('s3'), '"s3" is a valid stream wrapper scheme.');
    $this->assertEqual(\Drupal::service('stream_wrapper_manager')->getClass('s3'), 'Drupal\s3fs\StreamWrapper\S3fsStream', 'URIs with scheme "s3" should be handled by S3fsStream.');

    // The test.txt file is enough data to force multiple calls to write_stream().
    $file_contents = file_get_contents(__DIR__ . '/../../tests/fixtures/test.txt');

    $this->assertTrue(\Drupal::service('file_system')->mkdir($this->remoteTestsFolderUri), 'Exercised mkdir to create the testing directory (in the DB).');
    $this->assertTrue(is_dir($this->remoteTestsFolderUri), 'Make sure the folder we just created correctly reports that it is a folder.');

    $this->verbose("Exercising file upload functionality.");
    $start_time = time();
    $s3_file1 = file_save_data($file_contents, $test_uri1);
    $end_time = time();
    $total = $end_time - $start_time;
    $this->verbose("Upload time: $total seconds");

    $this->assertTrue(\Drupal::service('stream_wrapper_manager')->isValidUri($s3_file1->getFileUri()), "Uploaded the first test file, $test_uri1.");

    $this->verbose("Exercising file copy functionality.");
    $s3_file2 = file_copy($s3_file1, $test_uri2);
    $this->assertNotIdentical($s3_file2, FALSE, "Copied the the first test file to $test_uri2.");

    $this->verbose('Exercising the dir_*() functions.');
    $files = \Drupal::service('file_system')->scanDirectory($this->remoteTestsFolderUri, '#.*#');
    $this->assertTrue(isset($files[$test_uri1]), 'The first test file is in the tests directory.');
    $this->assertTrue(isset($files[$test_uri2]), 'The second test file is in the tests directory.');
    $this->assertEqual(count($files), 2, "There are exactly two files in the tests directory.");

    $this->verbose('Exercising getExternalUrl().');
    $url = file_create_url($test_uri1);
    $this->assertNotIdentical($url, FALSE, 'file_create_url() succeeded.');

    $this->verbose('Exercising unlink().');
    $this->assertIdentical(self::fileDelete($s3_file1), TRUE, "Deleted the first test file.");
    $this->assertIdentical(file_exists($test_uri1), FALSE, 'The wrapper reports that the first test file no longer exists.');

    $this->verbose('Exercising rename().');
    $this->assertTrue(rename($test_uri2, $test_uri1), "Renamed the second test file to the newly-vacated URI of $test_uri1.");
    $s3_file2->setFileUri($test_uri1);
    $s3_file2->save();

    $this->verbose('Exercising rmdir().');
    $this->assertFalse(\Drupal::service('file_system')->rmdir($this->remoteTestsFolderUri), 'rmdir() did not delete the tests folder because it is not empty.');
    $this->assertTrue(self::fileDelete($s3_file2), 'Deleted the last test file.');
    $this->assertTrue(\Drupal::service('file_system')->rmdir($this->remoteTestsFolderUri), 'Deleted the tests folder.');
    $this->assertFalse(is_dir($this->remoteTestsFolderUri), 'The wrapper reports that the tests folder is gone.');
  }

  /**
   * Test the image derivative functionality.
   */
  public function testImageDerivatives() {
    // Prevent issues with derivative tokens during test.
    $this->config('image.settings')->set('allow_insecure_derivatives', TRUE)->save();

    $img_uri1 = "{$this->remoteTestsFolderUri}/test.png";
    $img_localpath = __DIR__ . '/../../tests/fixtures/test.png';

    // Upload the test image.
    $this->assertTrue(\Drupal::service('file_system')->mkdir($this->remoteTestsFolderUri), 'Created the testing directory in the DB.');
    $img_data = file_get_contents($img_localpath);
    $img_file = file_save_data($img_data, $img_uri1);
    $this->assertNotIdentical($img_file, FALSE, "Copied the the test image to $img_uri1.");

    // Request a derivative.
    // Parse query parameters to ensure they get passed.
    $style_url_parsed = UrlHelper::parse(ImageStyle::load('thumbnail')->buildUrl($img_uri1));
    $derivative = $this->drupalGet($style_url_parsed['path'], ['query' => $style_url_parsed['query']]);
    $this->assertNotFalse(imagecreatefromstring($derivative), 'The returned derivative is a valid image.');
  }

  /**
   * Test the cache refresh.
   */
  public function testCacheRefresh() {
    // Add several files to the bucket using the AWS SDK directly, so that
    // s3fs won't cache them.
    $filenames = ['files/test2.txt', 'parts/test3.txt', 'test.txt'];
    foreach ($filenames as $filename) {
      $filename = $this->remoteTestsFolderKey . '/' . $filename;
      $this->s3->putObject(
        [
          'Bucket' => $this->config['bucket'],
          'Key' => $filename,
          'ACL' => 'public-read',
        ]
      );
    }

    $config = $this->config;
    // Set the current test folder as the root prefix.
    $config['root_folder'] = $this->remoteTestsFolderKey;
    \Drupal::service('s3fs')->refreshCache($config);

    // Query the DB to confirm that all the new files are cached.
    $result = $this->connection->select('s3fs_file', 's')
      ->fields('s')
      ->condition('dir', 0, '=')
      ->execute();
    $cached_files = [];
    foreach ($result as $record) {
      $cached_files[] = str_replace('s3://', '', $record->uri);
    }
    $this->assertEqual($filenames, $cached_files, 'The test files were all cached.');

    // Flush the cache, then do a refresh using the root_folder setting.
    // Only the file in the root folder (test3.txt) should become cached.
    $this->connection->delete('s3fs_file')->execute();
    $config['root_folder'] = $this->remoteTestsFolderKey . '/parts';
    \Drupal::service('s3fs')->refreshCache($config);

    // Confirm that only the file in the "parts" folder was cached.
    $records = $this->connection->select('s3fs_file', 's')
      ->fields('s')
      ->condition('dir', 0, '=')
      ->execute()
      ->fetchAll();
    $this->assertEqual(count($records), 1, 'There was only one file in the partially refreshed cache.');
    $this->assertEqual($records[0]->uri, 's3://test3.txt', 'That file was the one in the "parts" folder, which is now the root folder, so "parts" is not in the URI.');
  }

  /**
   * File delete wrapper that returns result.
   *
   * @param \Drupal\file\FileInterface $file
   *   A file object to delete.
   *
   * @return bool
   *   TRUE if file was deleted, FALSE otherwise.
   */
  protected static function fileDelete(FileInterface $file) {
    $file->delete();
    $exists = file_exists($file->getFileUri());
    return !$exists;
  }

}
