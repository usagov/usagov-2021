<?php

namespace Drupal\s3fs\Batch;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Class S3fsFileMigrationBatch.
 *
 * @package Drupal\s3fs\Batch
 */
class S3fsFileMigrationBatch implements S3fsFileMigrationBatchInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(array $config, $scheme) {
    if ($scheme === 'public') {
      $source_folder = realpath(PublicStream::basePath());
      $target_folder = !empty($config['public_folder']) ? $config['public_folder'] . '/' : 's3fs-public/';
    }
    elseif ($scheme === 'private') {
      $source_folder = PrivateStream::basePath() ? PrivateStream::basePath() : '';
      $source_folder_real = realpath($source_folder);
      if (empty($source_folder) || empty($source_folder_real)) {
        $this->messenger()->addError($this->t('Private file system base path is unknown. Unable to perform S3 copy.'));
        return;
      }
      $target_folder = !empty($config['private_folder']) ? $config['private_folder'] . '/' : 's3fs-private/';
    }
    else {
      $this->messenger()->addError($this->t('Scheme @scheme is not allowed', ['%scheme' => $scheme]));
      return;
    }

    $file_paths = $this->dirScan($source_folder);

    if (!empty($file_paths)) {
      // Create batch.
      $batch = $this->getBatch();

      $total = count($file_paths);
      $file_paths_chunks = array_chunk($file_paths, 50, TRUE);
      unset($file_paths);

      foreach ($file_paths_chunks as $chunk) {
        $batch['operations'][] = [[get_class($this), 'copyOperation'], [$chunk, $total, $source_folder, $target_folder, $scheme]];
      }

      batch_set($batch);

      $batch =& batch_get();

      // Drush integration
      if (PHP_SAPI === 'cli') {
        $batch['progressive'] = FALSE;
        drush_backend_batch_process();
      }
    }
    else {
      $this->messenger()->addMessage($this->t('There weren\'t files to migrate.'), 'ok');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dirScan($dir) {
    $output = [];
    $files = scandir($dir);
    foreach ($files as $file) {
      $path = "$dir/$file";

      if ($file != '.' && $file != '..') {
        // In case they put their private root folder inside their public one,
        // skip it. When listing the private file system contents, $path will
        // never trigger this.
        if ($path == realpath(PrivateStream::basePath() ? PrivateStream::basePath() : '')) {
          continue;
        }

        if (is_dir($path)) {
          $output = array_merge($output, $this->dirScan($path));
        }
        else {
          $output[] = $path;
        }
      }
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getBatch() {
    return [
      'operations' => [],
      'finished' => [get_class($this), 'finished'],
      'title' => $this->t('Copy files to S3'),
      'init_message' => $this->t('The copying process is about to start..'),
      'progress_message' => $this->t('Processed batch @current out of @total.'),
      'error_message' => $this->t('Something wrong happened, please check the logs.')
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function copyOperation(array $file_paths, $total, $source_folder, $target_folder, $scheme, &$context) {
    if (!isset($context['results']['scheme'])) {
      $context['results']['scheme'] = $scheme;
      $context['results']['progress'] = 0;
      $context['results']['percent_progress'] = 0;
      $context['results']['total'] = $total;
      $context['results']['time_start'] = time();
    }
    foreach ($file_paths as $path) {
      $relative_path = $target_folder . substr_replace($path, '', 0, strlen($source_folder) + 1);

      // Finally get to make use of S3fsStreamWrapper's "S3 is actually a local
      // file system. No really!" functionality.
      copy($path, "s3://$relative_path");

      // Update our progress information.
      $context['results']['progress']++;

      // Show status message each 5 files
      if ($context['results']['progress'] % 5 == 0) {
        $current_percent_progress = floor(($context['results']['progress'] / $context['results']['total']) * 100);

        if ($context['results']['percent_progress'] != $current_percent_progress) {
          $context['results']['percent_progress'] = $current_percent_progress;
        }

        $context['message'] = static::getCopyOperationMessage($context);
      }
    }
  }

  /**
   * @param $context
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  private static function getCopyOperationMessage($context) {
    return new TranslatableMarkup('@percent_progress% (@progress/@total) time elapsed @elapsed_time (hh:mm:ss)', [
      '@percent_progress' => $context['results']['percent_progress'],
      '@progress' => $context['results']['progress'],
      '@total' => $context['results']['total'],
      '@elapsed_time' => static::getElapsedTimeFormatted($context['results']['time_start']),
    ]);
  }

  /**
   * @param integer $time_start
   *
   * @return string
   */
  private static function getElapsedTimeFormatted($time_start) {
    $time_elapsed_seconds = time() - $time_start;
    return gmdate('H:i:s', $time_elapsed_seconds);
  }

  /**
   * {@inheritdoc}
   */
  public static function finished($success, $results, $operations) {
    \Drupal::messenger()->addStatus(new TranslatableMarkup('Copied all local %scheme files to S3 in @elapsed_time (hh:mm:ss).', [
      '%scheme' => $results['scheme'],
      '@elapsed_time' => static::getElapsedTimeFormatted($results['time_start']),
    ]));
  }

}
