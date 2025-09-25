<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Database\Connection;
use Aws\S3\S3Client;

/**
 * Drush command to copy referenced images from S3FS/public:// to static output.
 * Also rewrites HTML references to static file paths.
 */
class StaticImageSyncCommands extends DrushCommands {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * StaticImageSyncCommands constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(FileSystemInterface $file_system, Connection $database) {
    parent::__construct();
    $this->fileSystem = $file_system;
    $this->database = $database;
  }

  /**
   * Copies referenced images from S3FS/public:// to static output and rewrites HTML references.
   *
   * @command usagov:ssg-sync-images
   * @aliases ssg-sync-images
   */
  public function syncImages(array $options = ['html_dir' => 'html', 'output_files_dir' => 'html/files']): void {
    $html_dir = $options['html_dir'];
    $output_files_dir = $options['output_files_dir'];

    $finder = new Finder();
    $finder->files()->in($html_dir)->name('*.html');
    $all_files = [];
    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());
      // Match only src URLs (exclude srcset).
      preg_match_all('/src="([^"]+)"/', $html, $matches);
      foreach ($matches[1] as $url) {
        // Only process /s3/files/ or /sites/default/files/ or /files/.
        if (preg_match('#/(s3/files|sites/default/files|files)/(.+?)(["\?\s])#', $url . ' ', $m)) {
          $relative_path = $m[1] . '/' . $m[2];
          // Remove query/fragment.
          $relative_path = preg_replace('/[\?"].*$/', '', $relative_path);
          // Normalize spaces and %20 to plus signs for consistency
          $relative_path = str_replace(['%20', ' '], '+', $relative_path);
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
      // If not found, try alternative encodings (space, %20) to find the actual file
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
    // Now rewrite HTML references to use static paths and normalize URLs.
    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());

      // Process only src attributes in img tags (exclude srcset)
      $html = preg_replace_callback(
        '/src="([^"]+)"/i',
        function ($src_matches) {
          $src_url = $src_matches[1];

          // Only process image files with /files/ and spaces or %20
          if (strpos($src_url, '/files/') !== FALSE &&
              (strpos($src_url, ' ') !== FALSE || strpos($src_url, '%20') !== FALSE) &&
              preg_match('/\.(jpg|jpeg|png|gif|webp|svg)(\?|$)/i', $src_url)) {

            $normalized_url = $this->normalizeImageUrl($src_url);
            return 'src="' . $normalized_url . '"';
          }

          return $src_matches[0];
        },
        $html
      );

      file_put_contents($file->getRealPath(), $html);
    }
    $this->output()->writeln('HTML references normalized to use pluses.');
  }

  /**
   * Normalize S3FS file table URIs to use pluses instead of spaces or %20.
   *
   * @command usagov:ssg-normalize-s3fs-uris
   * @aliases ssg-normalize-s3fs-uris
   */
  public function normalizeS3fsUris(): void {
    $this->output()->writeln('Normalizing S3FS file table URIs to use pluses...');

    // Check if s3fs_file table exists
    if (!$this->database->schema()->tableExists('s3fs_file')) {
      $this->output()->writeln('WARNING: s3fs_file table does not exist. Skipping S3FS URI normalization.');
      return;
    }

    // Find all URIs with spaces or %20 encoding
    $query = $this->database->select('s3fs_file', 's')
      ->fields('s', ['uri']);

    $or_condition = $query->orConditionGroup()
      ->condition('uri', '%[ %]%', 'LIKE')
      ->condition('uri', '%[%20]%', 'LIKE');

    $query->condition($or_condition);

    $results = $query->execute();
    $updated_count = 0;
    $failed_count = 0;

    foreach ($results as $record) {
      $original_uri = $record->uri;
      $normalized_uri = str_replace(['%20', ' '], '+', $original_uri);

      if ($original_uri !== $normalized_uri) {
        try {
          // Check if normalized URI already exists
          $exists = $this->database->select('s3fs_file', 's')
            ->fields('s', ['uri'])
            ->condition('uri', $normalized_uri)
            ->countQuery()
            ->execute()
            ->fetchField();

          if ($exists > 0) {
            $this->output()->writeln('SKIP: Normalized URI already exists: ' . $normalized_uri);
            continue;
          }

          // Update the URI
          $this->database->update('s3fs_file')
            ->fields(['uri' => $normalized_uri])
            ->condition('uri', $original_uri)
            ->execute();

          $this->output()->writeln('Updated: ' . $original_uri . ' → ' . $normalized_uri);
          $updated_count++;
        }
        catch (\Exception $e) {
          $this->output()->writeln('ERROR updating ' . $original_uri . ': ' . $e->getMessage());
          $failed_count++;
        }
      }
    }

    $this->output()->writeln('S3FS URI normalization complete:');
    $this->output()->writeln('  Updated: ' . $updated_count . ' URIs');
    $this->output()->writeln('  Failed: ' . $failed_count . ' URIs');
  }

  /**
   * Rename S3 files to use pluses instead of spaces or %20 encoding.
   *
   * @command usagov:ssg-rename-s3-files
   * @aliases ssg-rename-s3-files
   * @option dry-run Run in dry-run mode without making actual changes
   */
  public function renameS3Files(array $options = ['dry-run' => FALSE]): void {
    $dry_run = $options['dry-run'];
    $this->output()->writeln('Renaming S3 files to use pluses...' . ($dry_run ? ' (DRY RUN)' : ''));

    // Check if AWS SDK is available
    if (!class_exists('Aws\S3\S3Client')) {
      $this->output()->writeln('ERROR: AWS SDK not available. Cannot rename S3 files.');
      return;
    }

    // Get S3 configuration from environment
    $bucket_name = getenv('BUCKET_NAME');
    $aws_access_key = getenv('AWS_ACCESS_KEY_ID');
    $aws_secret_key = getenv('AWS_SECRET_ACCESS_KEY');
    $aws_region = getenv('AWS_DEFAULT_REGION') ?: 'us-east-1';
    $aws_endpoint = getenv('AWS_ENDPOINT');

    if (empty($bucket_name) || empty($aws_access_key) || empty($aws_secret_key)) {
      $this->output()->writeln('ERROR: Missing AWS configuration. Set BUCKET_NAME, AWS_ACCESS_KEY_ID, and AWS_SECRET_ACCESS_KEY.');
      return;
    }

    try {
      $s3_config = [
        'version' => 'latest',
        'region' => $aws_region,
        'credentials' => [
          'key' => $aws_access_key,
          'secret' => $aws_secret_key,
        ],
      ];

      if (!empty($aws_endpoint)) {
        $s3_config['endpoint'] = $aws_endpoint;
        $s3_config['use_path_style_endpoint'] = TRUE;
      }

      $s3_client = new S3Client($s3_config);
    }
    catch (\Exception $e) {
      $this->output()->writeln('ERROR: Failed to create S3 client: ' . $e->getMessage());
      return;
    }

    $renamed_count = 0;
    $failed_count = 0;
    $prefix = 'cms/public/';

    try {
      // List all objects in the bucket with the CMS public prefix
      $paginator = $s3_client->getPaginator('ListObjects', [
        'Bucket' => $bucket_name,
        'Prefix' => $prefix,
      ]);

      foreach ($paginator as $page) {
        if (!isset($page['Contents'])) {
          continue;
        }

        foreach ($page['Contents'] as $object) {
          $original_key = $object['Key'];

          // Skip if no spaces or %20 in the key
          if (strpos($original_key, ' ') === FALSE && strpos($original_key, '%20') === FALSE) {
            continue;
          }

          $normalized_key = str_replace(['%20', ' '], '+', $original_key);

          if ($original_key !== $normalized_key) {
            $this->output()->writeln('Renaming: ' . $original_key . ' → ' . $normalized_key);

            if (!$dry_run) {
              try {
                // Check if target already exists
                $exists = $s3_client->doesObjectExistV2($bucket_name, $normalized_key);
                if ($exists) {
                  $this->output()->writeln('SKIP: Target already exists: ' . $normalized_key);
                  continue;
                }

                // Copy object to new location
                $s3_client->copyObject([
                  'Bucket' => $bucket_name,
                  'Key' => $normalized_key,
                  'CopySource' => $bucket_name . '/' . $original_key,
                  'ACL' => 'public-read',
                ]);

                // Delete original object
                $s3_client->deleteObject([
                  'Bucket' => $bucket_name,
                  'Key' => $original_key,
                ]);

                $renamed_count++;
                $this->output()->writeln('SUCCESS: Renamed ' . $original_key);
              }
              catch (\Exception $e) {
                $this->output()->writeln('ERROR renaming ' . $original_key . ': ' . $e->getMessage());
                $failed_count++;
              }
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->output()->writeln('ERROR listing S3 objects: ' . $e->getMessage());
      return;
    }

    $this->output()->writeln('S3 file renaming complete:');
    $this->output()->writeln('  Renamed: ' . $renamed_count . ' files');
    $this->output()->writeln('  Failed: ' . $failed_count . ' files');
  }

  /**
   * Comprehensive command to normalize all URL encoding
   * across static HTML, S3FS table, and S3 files.
   *
   * @command usagov:ssg-normalize-all-urls
   * @aliases ssg-normalize-all-urls
   * @option dry-run Run in dry-run mode for S3 operations without making actual changes
   * @option skip-s3fs Skip S3FS table normalization
   * @option skip-s3-files Skip S3 file renaming
   * @option html_dir HTML directory for static files
   * @option output_files_dir Output files directory
   */
  public function normalizeAllUrls(
    array $options = [
      'dry-run' => FALSE,
      'skip-s3fs' => FALSE,
      'skip-s3-files' => FALSE,
      'html_dir' => 'html',
      'output_files_dir' => 'html/files',
    ],
  ): void {
    $this->output()->writeln('=== USAGOV URL NORMALIZATION TOOL ===');
    $this->output()->writeln('Normalizing URLs to use plus signs (+) instead of spaces or %20 encoding');
    $this->output()->writeln('');

    // Step 1: Normalize S3FS table URIs first (affects Drupal's understanding of files)
    if (!$options['skip-s3fs']) {
      $this->output()->writeln('STEP 1: Normalizing S3FS table URIs...');
      $this->normalizeS3fsUris();
      $this->output()->writeln('');
    }
    else {
      $this->output()->writeln('STEP 1: Skipping S3FS table normalization (--skip-s3fs flag set)');
      $this->output()->writeln('');
    }

    // Step 2: Rename actual S3 files (affects the physical storage)
    if (!$options['skip-s3-files']) {
      $this->output()->writeln('STEP 2: Renaming S3 files...');
      $this->renameS3Files(['dry-run' => $options['dry-run']]);
      $this->output()->writeln('');
    }
    else {
      $this->output()->writeln('STEP 2: Skipping S3 file renaming (--skip-s3-files flag set)');
      $this->output()->writeln('');
    }

    // Step 3: Process static HTML output and normalize URLs
    $this->output()->writeln('STEP 3: Processing static HTML files and normalizing URLs...');
    $this->syncImages([
      'html_dir' => $options['html_dir'],
      'output_files_dir' => $options['output_files_dir'],
    ]);
    $this->output()->writeln('');

    $this->output()->writeln('=== URL NORMALIZATION COMPLETE ===');
    $this->output()->writeln('All URLs should now use plus signs (+) instead of spaces or %20 encoding.');
    $this->output()->writeln('Next steps:');
    $this->output()->writeln('1. Test the static site to ensure images are loading correctly');
    $this->output()->writeln('2. Run a Tome build to verify everything works together');
    $this->output()->writeln('3. Deploy and validate the changes');
  }

  /**
   * Helper method to normalize image URLs by replacing spaces and %20 with plus signs.
   *
   * @param string $url
   *   The image URL to normalize.
   *
   * @return string
   *   The normalized URL.
   */
  private function normalizeImageUrl(string $url): string {
    // Replace /s3/files/ and /sites/default/files/ with /files/
    $normalized_url = preg_replace('#/s3/files/#', '/files/', $url);
    $normalized_url = preg_replace('#/sites/default/files/#', '/files/', $normalized_url);

    // Find the filename part and normalize spaces to plus signs
    $url_parts = parse_url($normalized_url);
    $path = $url_parts['path'] ?? $normalized_url;
    $last_slash_pos = strrpos($path, '/');

    if ($last_slash_pos !== FALSE) {
      $directory_path = substr($path, 0, $last_slash_pos + 1);
      $filename = substr($path, $last_slash_pos + 1);
      $normalized_filename = str_replace(['%20', ' '], '+', $filename);
      $normalized_path = $directory_path . $normalized_filename;

      // Rebuild URL with query/fragment if present
      if (isset($url_parts['query']) || isset($url_parts['fragment'])) {
        $normalized_url = $normalized_path;
        if (isset($url_parts['query'])) {
          $normalized_url .= '?' . $url_parts['query'];
        }
        if (isset($url_parts['fragment'])) {
          $normalized_url .= '#' . $url_parts['fragment'];
        }
      }
      else {
        $normalized_url = $normalized_path;
      }
    }

    return $normalized_url;
  }

}
