<?php

namespace Drupal\s3fs\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\s3fs\Batch\S3fsFileMigrationBatchInterface;
use Drupal\s3fs\S3fsServiceInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Log\LogLevel;

/**
 * Class S3fsCommands.
 *
 * @package Drupal\s3fs\Commands
 */
class S3fsCommands extends DrushCommands {

  /**
   * @var \Drupal\s3fs\S3fsServiceInterface
   */
  private $s3fs;

  /**
   * @var \Drupal\s3fs\Batch\S3fsFileMigrationBatchInterface
   */
  private $s3fsFileMigrationBatch;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $s3fsConfig;

  /**
   * S3fsCommands constructor.
   *
   * @param \Drupal\s3fs\S3fsServiceInterface                  $s3fs
   * @param \Drupal\s3fs\Batch\S3fsFileMigrationBatchInterface $s3fs_file_migration_batch
   * @param \Drupal\Core\Config\ConfigFactoryInterface         $config_factory
   */
  public function __construct(
    S3fsServiceInterface $s3fs,
    S3fsFileMigrationBatchInterface $s3fs_file_migration_batch,
    ConfigFactoryInterface $config_factory
  ) {
    $this->s3fs = $s3fs;
    $this->s3fsFileMigrationBatch = $s3fs_file_migration_batch;
    $this->s3fsConfig = $config_factory->get('s3fs.settings');
  }

  /**
   * Refresh the S3 File System metadata cache.
   *
   * @command s3fs:refresh-cache
   * @aliases s3fs-rc, s3fs-refresh-cache
   */
  public function refreshCache() {
    $config = $this->s3fsConfig->get();

    if ($errors = $this->s3fs->validate($config)) {
      foreach ($errors as $error) {
        $this->logger()->error($error);
      }
      throw new \Exception(
        new TranslatableMarkup('Unable to validate your s3fs configuration settings. Please configure S3 File System from the admin/config/media/s3fs page and try again.')
      );
    }

    $this->s3fs->refreshCache($config);
  }

  /**
   * Copy local files from your public and/or private file system(s) into your S3 bucket, if s3fs is configured to take them over.
   *
   * @command s3fs:copy-local
   * @aliases s3fs-cl, s3fs-copy-local
   * @option scheme Limit the process to an specific scheme. E.g. (public or private), all by default.
   * @usage drush s3fs-copy-local
   *   Copy local files from your public and/or private file system(s) into your S3 bucket.
   * @usage drush s3fs-copy-local --scheme=public
   *   Copy local files only from your public file system into your S3 bucket.
   */
  public function copyLocal(array $options = ['scheme' => 'all']) {
    $scheme = $options['scheme'];
    $this->logger()->log(LogLevel::OK, new TranslatableMarkup('You are going to copy @scheme scheme(s).', ['@scheme' => $scheme]));
    $this->logger()->warning(new TranslatableMarkup('You should have read "Copy local files to S3" section in README.txt.'));
    $this->logger()->warning(new TranslatableMarkup('This command only is useful if you have or you are going to have enabled s3 for public/private in your setting.php'));

    if (!$this->io()->confirm(new TranslatableMarkup('Are you sure?'))) {
      return new UserAbortException();
    }

    $config = $this->s3fsConfig->get();

    if ($errors = $this->s3fs->validate($config)) {
      foreach ($errors as $error) {
        $this->logger()->error($error);
      }
      throw new \Exception(
        new TranslatableMarkup('Unable to validate your s3fs configuration settings. Please configure S3 File System from the admin/config/media/s3fs page and try again.')
      );
    }

    if ($scheme == 'all' || $scheme == 'public') {
      $this->logger()->log(LogLevel::OK, new TranslatableMarkup('Starting @scheme scheme', ['@scheme' => 'public']));
      $this->s3fsFileMigrationBatch->execute($config, 'public');
    }

    if ($scheme == 'all' || $scheme == 'private') {
      if (Settings::get('file_private_path')) {
        $this->logger()->log(LogLevel::OK, new TranslatableMarkup('Starting @scheme scheme', ['@scheme' => 'private']));
        $this->s3fsFileMigrationBatch->execute($config, 'private');
      }
      else {
        $this->logger()->warning(new TranslatableMarkup('Scheme @scheme is not properly configured, you must enable this scheme in your settings.php',
          ['@scheme' => 'private']
        ));
      }
    }
  }

}
