<?php

namespace Drupal\s3fs\Tests;

use Drupal\Core\File\Exception\FileNotExistsException;

/**
 * S3 File System Service Decorator Tests.
 *
 * Ensure that the decorator override works correctly.
 *
 * @group s3fs
 */
class S3fsFileServiceTest extends S3fsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['s3fs'];

  /**
   * Coverage test for the file_system service decorator.
   *
   * Most of this is implicitly tested in S3fsTest. however lets make sure
   * to explicitly test the decorator.
   */
  public function testFileService() {
    $testUri1 = "{$this->remoteTestsFolderUri}/test_file1.txt";
    $testUri2 = "{$this->remoteTestsFolderUri}/test_file2.txt";
    $testUri3 = "{$this->remoteTestsFolderUri}/test_file3.txt";
    $testUri4 = "{$this->remoteTestsFolderUri}/test_file4.txt";

    $fileSystem = \Drupal::service('file_system');

    $file_contents = file_get_contents(__DIR__ . '/../../tests/fixtures/test.txt');
    $temp_file = $fileSystem->saveData($file_contents, 'temporary://123');

    $this->assertTrue($fileSystem->moveUploadedFile($temp_file, $testUri1), 'Uploaded file with S3fsFileService');
    $this->assertFalse($fileSystem->moveUploadedFile('/tmp/invalidFile', $testUri1), 'Did not upload nonexistent file');

    $this->assertEquals($testUri2, $fileSystem->move($testUri1, $testUri2), 'Moved file with S3fsFileService');
    $this->expectException(FileNotExistsException::class);
    $fileSystem->move($testUri1, $testUri3);

    $this->assertEquals($testUri4, $fileSystem->copy($testUri2, $testUri4), 'Copied file from uri2 to uri4');
    $this->expectException(FileNotExistsException::class);
    $fileSystem->copy($testUri1, $testUri2);
  }

}
