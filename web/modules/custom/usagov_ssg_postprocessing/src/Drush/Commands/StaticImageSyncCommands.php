<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

/**
 * Drush command to copy referenced images from S3FS/public:// to static output.
 * Also rewrites HTML references to static file paths.
 */

use Drupal\Core\File\FileSystemInterface;

class StaticImageSyncCommands extends DrushCommands {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * StaticImageSyncCommands constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    parent::__construct();
    $this->fileSystem = $file_system;
  }

  /**
   * Copies referenced images from S3FS/public:// to static output and rewrites HTML references.
   *
   * @param array<string, string> $options
   *   Options array with 'html_dir' and 'output_files_dir'.
   */

  public function syncImages(array $options = ['html_dir' => 'html', 'output_files_dir' => 'html/files']): void {
    $html_dir = $options['html_dir'];
    $output_files_dir = $options['output_files_dir'];

    $finder = new Finder();
    $finder->files()->in($html_dir)->name('*.html');
    $all_files = [];
    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());
      // Match src and srcset URLs.
      preg_match_all('/(?:src|srcset)="([^"]+)"/', $html, $matches);
      foreach ($matches[1] as $url) {
        // Only process /s3/files/ or /sites/default/files/ or /files/.
        if (preg_match('#/(s3/files|sites/default/files|files)/(.+?)(["\?\s])#', $url . ' ', $m)) {
          $relative_path = $m[1] . '/' . $m[2];
          // Remove query/fragment.
          $relative_path = preg_replace('/[\?"].*$/', '', $relative_path);
          $all_files[$relative_path] = TRUE;
        }
      }
    }
    $this->output()->writeln('Found ' . count($all_files) . ' unique referenced files.');
    foreach (array_keys($all_files) as $rel_path) {
      // Try public://files/... or public://s3/files/....
      $public_path = 'public://' . $rel_path;
      $dest_path = $output_files_dir . '/' . $rel_path;
      $dest_dir = dirname($dest_path);
      if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0775, TRUE);
      }
      $real_source = $this->fileSystem->realpath($public_path);
      // If not found, try replacing + with space and %20.
      if (!$real_source || !file_exists($real_source)) {
        $alt_paths = [
          str_replace('+', ' ', $rel_path),
          str_replace('+', '%20', $rel_path),
        ];
        foreach ($alt_paths as $alt_rel_path) {
          $alt_public_path = 'public://' . $alt_rel_path;
          $alt_real_source = $this->fileSystem->realpath($alt_public_path);
          if ($alt_real_source && file_exists($alt_real_source)) {
            $real_source = $alt_real_source;
            $this->output()->writeln('Mapped ' . $rel_path . ' to ' . $alt_rel_path);
            break;
          }
        }
      }
      if ($real_source && file_exists($real_source)) {
        copy($real_source, $dest_path);
        $this->output()->writeln('Copied ' . $real_source . ' to ' . $dest_path);
      }
      else {
        $this->output()->writeln('WARNING: ' . $public_path . ' (and alternatives) do not exist');
      }
    }
    // Now rewrite HTML references.
    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());
      $html = preg_replace('#/s3/files/#', '/files/', $html);
      $html = preg_replace('#/sites/default/files/#', '/files/', $html);
      file_put_contents($file->getRealPath(), $html);
    }
    $this->output()->writeln('HTML references rewritten.');
  }

}
