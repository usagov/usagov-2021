<?php

namespace Drupal\S3fs;

use Drupal\Core\File\Exception\DirectoryNotReadyException;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\Exception\FileExistsException;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides helpers to operate on files and stream wrappers.
 */
class S3fsFileService implements FileSystemInterface {

  /**
   * The inner service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $decorated;

  /**
   * The file logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(FileSystem $decorated, StreamWrapperManagerInterface $stream_wrapper_manager, LoggerInterface $logger) {
    $this->decorated = $decorated;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->logger = $logger;
  }

  /**
   * Copies a file in a remote streamwrapper safe manner.
   *
   * PHP convience functions copy(),rename(), move_uploaded_file(), etc do not
   * check that the write buffer is successfully flushed. As such we need to
   * handle the writes ourself so we can return when an error occurs on our
   * StreamWrapper by using using fflush().
   *
   * @param string $source
   *   A string specifying the filepath or URI of the source file.
   * @param string $destination
   *   A URI containing the destination that $source should be copied to. The
   *   URI is expected to be a remote stream.
   *
   * @return bool
   *   True on success, false on error.
   *
   * @see https://www.drupal.org/project/s3fs/issues/2972161
   */
  protected function streamWriteData($source, $destination) {
    $srcHandle = @fopen($source, "rb");
    if ($srcHandle === FALSE) {
      return FALSE;
    }
    $destHandle = @fopen($destination, 'w');
    if ($destHandle === FALSE) {
      fclose($srcHandle);
      return FALSE;
    }

    $result = FALSE;

    while (!feof($srcHandle)) {
      fwrite($destHandle, fread($srcHandle, 8192));
    }
    $result = @fflush($destHandle);
    fclose($srcHandle);
    fclose($destHandle);

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function moveUploadedFile($filename, $uri) {
    $wrapper = $this->streamWrapperManager->getViaUri($uri);
    if (is_a($wrapper, 'Drupal\s3fs\StreamWrapper\S3fsStream')) {
      return $this->streamWriteData($filename, $uri);
    }
    else {
      return $this->decorated->moveUploadedFile($filename, $uri);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function chmod($uri, $mode = NULL) {
    return $this->decorated->chmod($uri, $mode);
  }

  /**
   * {@inheritdoc}
   */
  public function unlink($uri, $context = NULL) {
    return $this->decorated->unlink($uri, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function realpath($uri) {
    return $this->decorated->realpath($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function dirname($uri) {
    return $this->decorated->dirname($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function basename($uri, $suffix = NULL) {
    return $this->decorated->basename($uri, $suffix);
  }

  /**
   * {@inheritdoc}
   */
  public function mkdir($uri, $mode = NULL, $recursive = FALSE, $context = NULL) {
    return $this->decorated->mkdir($uri, $mode, $recursive, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function rmdir($uri, $context = NULL) {
    return $this->decorated->rmdir($uri, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function tempnam($directory, $prefix) {
    return $this->decorated->tempnam($directory, $prefix);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove when Drupal 8.9 support ends.
   */
  public function uriScheme($uri) {
    if (method_exists($this->decorated, 'uriScheme')) {
      return $this->decorated->uriScheme($uri);
    }
    else {
      @trigger_error('S3FS: FileSystem::uriScheme() has been removed in core. Use \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface::getScheme() instead. See https://www.drupal.org/node/3035273', E_USER_ERROR);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @todo Remove when Drupal 8.9 support ends.
   */
  public function validScheme($scheme) {
    if (method_exists($this->decorated, 'validScheme')) {
      return $this->decorated->validScheme($scheme);
    }
    else {
      @trigger_error('S3FS: FileSystem::validScheme() Has been removed in core. Use \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface::isValidScheme() instead. See https://www.drupal.org/node/3035273', E_USER_ERROR);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copy($source, $destination, $replace = self::EXISTS_RENAME) {
    $wrapper = $this->streamWrapperManager->getViaUri($destination);
    if (is_a($wrapper, 'Drupal\s3fs\StreamWrapper\S3fsStream')) {
      $this->prepareDestination($source, $destination, $replace);
      if (!$this->streamWriteData($source, $destination)) {
        // Try again with realpath for $source.
        $real_source = $this->realpath($source) ?: $source;
        if ($real_source === FALSE || $this->streamWriteData($real_source, $destination)) {
          $this->logger->error("The specified file '%source' could not be copied to '%destination'.",
            [
              '%source' => $source,
              '%destination' => $destination,
            ]);
          throw new FileWriteException("The specified file '$source' could not be copied to '$destination'.");
        }
      }
      return $destination;
    }
    else {
      return $this->decorated->copy($source, $destination, $replace);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path) {
    return $this->decorated->delete($path);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRecursive($path, callable $callback = NULL) {
    return $this->decorated->deleteRecursive($path, $callback);
  }

  /**
   * {@inheritdoc}
   */
  public function move($source, $destination, $replace = self::EXISTS_RENAME) {
    $wrapper = $this->streamWrapperManager->getViaUri($destination);
    if (is_a($wrapper, 'Drupal\s3fs\StreamWrapper\S3fsStream')) {
      $this->prepareDestination($source, $destination, $replace);

      // Ensure compatibility with Windows.
      // @see \Drupal\Core\File\FileSystemInterface::unlink().
      if (!$this->streamWrapperManager->isValidUri($source) && (substr(PHP_OS, 0, 3) == 'WIN')) {
        chmod($source, 0600);
      }

      // Attempt to resolve the URIs. This is necessary in certain
      // configurations (see above) and can also permit fast moves across local
      // schemes.
      $real_source = $this->realpath($source) ?: $source;

      // Perform move operation.
      if (!@rename($real_source, $destination)) {
        // Both sources are not on the S3 StreamWrapper.
        // Fall back to slow copy and unlink procedure.
        // It's not necessary to use FileSystem::unlink() as the
        // Windows issue has already been resolved above.
        if (!$this->streamWriteData($real_source, $destination)) {
          $this->logger->error("The specified file '%source' could not be moved to '%destination'.", [
            '%source' => $source,
            '%destination' => $destination,
          ]);
          throw new FileWriteException("The specified file '$source' could not be moved to '$destination'.");
        }
        if (!@unlink($real_source)) {
          $this->logger->error("The source file '%source' could not be unlinked after copying to '%destination'.", [
            '%source' => $source,
            '%destination' => $destination,
          ]);
          throw new FileException("The source file '$source' could not be unlinked after copying to '$destination'.");
        }
      }

      return $destination;
    }
    else {
      return $this->decorated->move($source, $destination, $replace);
    }
  }

  /**
   * Prepares the destination for a file copy or move operation.
   *
   * - Checks if $source and $destination are valid and readable/writable.
   * - Checks that $source is not equal to $destination; if they are an error
   *   is reported.
   * - If file already exists in $destination either the call will error out,
   *   replace the file or rename the file based on the $replace parameter.
   *
   * @param string $source
   *   A string specifying the filepath or URI of the source file.
   * @param string|null $destination
   *   A URI containing the destination that $source should be moved/copied to.
   *   The URI may be a bare filepath (without a scheme) and in that case the
   *   default scheme (file://) will be used.
   * @param int $replace
   *   Replace behavior when the destination file already exists:
   *   - FileSystemInterface::EXISTS_REPLACE - Replace the existing file.
   *   - FileSystemInterface::EXISTS_RENAME - Append _{incrementing number}
   *     until the filename is unique.
   *   - FileSystemInterface::EXISTS_ERROR - Do nothing and return FALSE.
   *
   * @see \Drupal\Core\File\FileSystemInterface::copy()
   * @see \Drupal\Core\File\FileSystemInterface::move()
   */
  protected function prepareDestination($source, &$destination, $replace) {
    $original_source = $source;

    if (!file_exists($source)) {
      if (($realpath = $this->realpath($original_source)) !== FALSE) {
        $this->logger->error("File '%original_source' ('%realpath') could not be copied because it does not exist.", [
          '%original_source' => $original_source,
          '%realpath' => $realpath,
        ]);
        throw new FileNotExistsException("File '$original_source' ('$realpath') could not be copied because it does not exist.");
      }
      else {
        $this->logger->error("File '%original_source' could not be copied because it does not exist.", [
          '%original_source' => $original_source,
        ]);
        throw new FileNotExistsException("File '$original_source' could not be copied because it does not exist.");
      }
    }

    // Prepare the destination directory.
    if ($this->prepareDirectory($destination)) {
      // The destination is already a directory, so append the source basename.
      $destination = $this->streamWrapperManager->normalizeUri($destination . '/' . $this->basename($source));
    }
    else {
      // Perhaps $destination is a dir/file?
      $dirname = $this->dirname($destination);
      if (!$this->prepareDirectory($dirname)) {
        $this->logger->error("The specified file '%original_source' could not be copied because the destination directory '%destination_directory' is not properly configured. This may be caused by a problem with file or directory permissions.", [
          '%original_source' => $original_source,
          '%destination_directory' => $dirname,
        ]);
        throw new DirectoryNotReadyException("The specified file '$original_source' could not be copied because the destination directory '$dirname' is not properly configured. This may be caused by a problem with file or directory permissions.");
      }
    }

    // Determine whether we can perform this operation based on overwrite rules.
    $destination = $this->getDestinationFilename($destination, $replace);
    if ($destination === FALSE) {
      $this->logger->error("File '%original_source' could not be copied because a file by that name already exists in the destination directory ('%destination').", [
        '%original_source' => $original_source,
        '%destination' => $destination,
      ]);
      throw new FileExistsException("File '$original_source' could not be copied because a file by that name already exists in the destination directory ('$destination').");
    }

    // Assert that the source and destination filenames are not the same.
    $real_source = $this->realpath($source);
    $real_destination = $this->realpath($destination);
    if ($source == $destination || ($real_source !== FALSE) && ($real_source == $real_destination)) {
      $this->logger->error("File '%source' could not be copied because it would overwrite itself.", [
        '%source' => $source,
      ]);
      throw new FileException("File '$source' could not be copied because it would overwrite itself.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveData($data, $destination, $replace = self::EXISTS_RENAME) {
    // Write the data to a temporary file.
    $temp_name = $this->tempnam('temporary://', 'file');
    if (file_put_contents($temp_name, $data) === FALSE) {
      $this->logger->error("Temporary file '%temp_name' could not be created.", ['%temp_name' => $temp_name]);
      throw new FileWriteException("Temporary file '$temp_name' could not be created.");
    }

    // Move the file to its final destination.
    return $this->move($temp_name, $destination, $replace);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDirectory(&$directory, $options = self::MODIFY_PERMISSIONS) {
    return $this->decorated->prepareDirectory($directory, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationFilename($destination, $replace) {
    return $this->decorated->getDestinationFilename($destination, $replace);
  }

  /**
   * {@inheritdoc}
   */
  public function createFilename($basename, $directory) {
    return $this->decorated->createFilename($basename, $directory);
  }

  /**
   * {@inheritdoc}
   */
  public function getTempDirectory() {
    return $this->decorated->getTempDirectory();
  }

  /**
   * {@inheritdoc}
   */
  public function scanDirectory($dir, $mask, array $options = []) {
    return $this->decorated->scanDirectory($dir, $mask, $options);
  }

}
