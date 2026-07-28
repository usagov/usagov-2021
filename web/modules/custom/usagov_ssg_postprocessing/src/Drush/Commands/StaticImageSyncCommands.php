<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drush\Commands\DrushCommands;
use Drupal\usagov_ssg_postprocessing\StaticFileUrlMapper;
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
  public function syncImages(
    array $options = [
      'html_dir' => 'html',
      'output_files_dir' => 'html/files',
      'reference_manifest_path' => NULL,
    ],
  ): void {
    $html_dir = $options['html_dir'];
    $output_files_dir = $options['output_files_dir'];
    $reference_manifest_path = $options['reference_manifest_path'];

    $finder = new Finder();
    $finder->files()->in($html_dir)->name('*.html');
    $all_files = [];
    $html_file_count = 0;
    $rewritten_html_file_count = 0;
    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());
      $html_file_count++;
      $this->collectReferencedFiles($html, $all_files);

      $rewritten_html = $this->normalizeImageUrls($html);
      if ($rewritten_html !== $html) {
        file_put_contents($file->getRealPath(), $rewritten_html);
        $rewritten_html_file_count++;
      }
    }
    $this->output()->writeln('Found ' . count($all_files) . ' unique referenced files.');
    $reference_manifest = [];
    foreach ($all_files as $public_path => $source_url) {
      $source_relative_path = substr($public_path, strlen('public://'));
      $dest_rel_path = StaticFileUrlMapper::staticOutputRelativePathFromFileUrl($source_url);
      if ($dest_rel_path === NULL) {
        continue;
      }

      // Create destination with sanitized filename
      $rel_path_parts = pathinfo($dest_rel_path);
      $sanitized_filename = $this->sanitizeFilename($rel_path_parts['basename']);
      $dest_rel_path = $rel_path_parts['dirname'] . '/' . $sanitized_filename;

      $dest_path = $output_files_dir . '/' . $dest_rel_path;
      $dest_dir = dirname($dest_path);
      if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0775, TRUE);
      }
      $reference_manifest[$public_path] = [$dest_rel_path, 'unresolved'];

      // S3FS can read uncached objects through its stream wrapper, so avoid
      // requiring a local realpath before copying the source object.
      $source_uri = NULL;
      $source_uri_candidates = [
        $public_path,
        'public://' . str_replace('+', ' ', $source_relative_path),
        'public://' . str_replace('+', '%20', $source_relative_path),
        'public://' . urldecode($source_relative_path),
        // In case it was already sanitized with hyphens.
        'public://' . str_replace('-', ' ', $source_relative_path),
      ];
      foreach ($source_uri_candidates as $candidate_source_uri) {
        if (file_exists($candidate_source_uri)) {
          $source_uri = $candidate_source_uri;
          break;
        }
      }

      if ($source_uri !== NULL && copy($source_uri, $dest_path)) {
        $reference_manifest[$public_path][1] = 'resolved';
        $this->output()->writeln('Copied ' . $source_uri . ' to ' . $dest_path);
      }
      else {
        $this->output()->writeln('WARNING: ' . $public_path . ' (and alternatives) could not be copied');
      }
    }
    if (!empty($reference_manifest_path)) {
      $this->writeReferenceManifest($reference_manifest_path, $reference_manifest);
    }
    $this->output()->writeln('Scanned ' . $html_file_count . ' HTML files and rewrote ' . $rewritten_html_file_count . '.');
    $this->output()->writeln('HTML references normalized to use pluses.');
  }

  /**
   * Collects referenced file paths using the current src-only contract.
   *
    * @param array<string, string> $all_files
    *   The unique public URIs keyed to their source URLs.
   */
  private function collectReferencedFiles(string $html, array &$all_files): void {
    // Match only src URLs (exclude srcset).
    preg_match_all('/src="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $url) {
      if (StaticFileUrlMapper::isGeneratedStaticAssetUrl($url)) {
        continue;
      }
      $public_uri = StaticFileUrlMapper::publicUriFromFileUrl($url);
      if ($public_uri !== NULL) {
        $all_files[$public_uri] = $url;
      }
    }
  }

  /**
   * Writes the current reference-to-source mapping without changing publish behavior.
   *
   * @param array<string, array{string, string}> $reference_manifest
   *   The public URI, destination path, and source resolution status for each file.
   */
  private function writeReferenceManifest(string $reference_manifest_path, array $reference_manifest): void {
    ksort($reference_manifest);
    $rows = ["public_uri\tdestination_relative_path\tsource_status"];
    foreach ($reference_manifest as $public_uri => [$destination_path, $source_status]) {
      $rows[] = $public_uri . "\t" . $destination_path . "\t" . $source_status;
    }
    file_put_contents($reference_manifest_path, implode("\n", $rows) . "\n");
    $this->output()->writeln('Wrote referenced asset manifest to ' . $reference_manifest_path . '.');
  }

  /**
   * Normalizes supported image src attributes without reparsing the HTML file.
   */
  private function normalizeImageUrls(string $html): string {
    return preg_replace_callback(
      '/src="([^"]+)"/i',
      function ($src_matches) {
        $src_url = $src_matches[1];

        // Only process image files with /files/ and spaces or %20.
        if (strpos($src_url, '/files/') !== FALSE &&
            (strpos($src_url, ' ') !== FALSE || strpos($src_url, '%20') !== FALSE) &&
            preg_match('/\.(jpg|jpeg|png|gif|webp|svg)(\?|$)/i', $src_url)) {

          return 'src="' . $this->normalizeImageUrl($src_url) . '"';
        }

        return $src_matches[0];
      },
      $html
    ) ?? $html;
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

      // Extract the filename from the URI (format: public://path/to/file.ext)
      $uri_parts = explode('/', $original_uri);
      $filename = array_pop($uri_parts);
      $sanitized_filename = $this->sanitizeFilename($filename);
      $normalized_uri = implode('/', $uri_parts) . '/' . $sanitized_filename;

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

          // Extract filename and check if it needs sanitization
          $key_parts = explode('/', $original_key);
          $original_filename = array_pop($key_parts);
          $sanitized_filename = $this->sanitizeFilename(urldecode($original_filename));

          // Skip if filename is already clean
          if ($original_filename === $sanitized_filename &&
              strpos($original_key, ' ') === FALSE &&
              strpos($original_key, '%20') === FALSE) {
            continue;
          }

          $normalized_key = implode('/', $key_parts) . '/' . $sanitized_filename;

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
   * Helper method to sanitize filenames by removing/normalizing special characters.
   *
   * @param string $filename
   *   The filename to sanitize.
   *
   * @return string
   *   The sanitized filename.
   */
  private function sanitizeFilename(string $filename): string {
    // Split filename from extension
    $path_info = pathinfo($filename);
    $name = $path_info['filename'] ?? $filename;
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';

    // First decode any URL encoding
    $name = urldecode($name);

    // Transliterate common special characters to ASCII equivalents
    $char_map = [
      // Smart quotes to regular quotes (then removed)
      "'" => "'",
      "'" => "'",
      '"' => '"',
      '"' => '"',
      // Em/en dashes to hyphens
      '—' => '-',
      '–' => '-',
      // Other common special chars
      '…' => '',
      '®' => '',
      '™' => '',
      '©' => '',
      // Accented uppercase letters
      'À' => 'A',
      'Á' => 'A',
      'Â' => 'A',
      'Ã' => 'A',
      'Ä' => 'A',
      'Å' => 'A',
      'È' => 'E',
      'É' => 'E',
      'Ê' => 'E',
      'Ë' => 'E',
      'Ì' => 'I',
      'Í' => 'I',
      'Î' => 'I',
      'Ï' => 'I',
      'Ò' => 'O',
      'Ó' => 'O',
      'Ô' => 'O',
      'Õ' => 'O',
      'Ö' => 'O',
      'Ø' => 'O',
      'Ù' => 'U',
      'Ú' => 'U',
      'Û' => 'U',
      'Ü' => 'U',
      'Ñ' => 'N',
      'Ç' => 'C',
      'Ý' => 'Y',
      // Accented lowercase letters
      'à' => 'a',
      'á' => 'a',
      'â' => 'a',
      'ã' => 'a',
      'ä' => 'a',
      'å' => 'a',
      'è' => 'e',
      'é' => 'e',
      'ê' => 'e',
      'ë' => 'e',
      'ì' => 'i',
      'í' => 'i',
      'î' => 'i',
      'ï' => 'i',
      'ò' => 'o',
      'ó' => 'o',
      'ô' => 'o',
      'õ' => 'o',
      'ö' => 'o',
      'ø' => 'o',
      'ù' => 'u',
      'ú' => 'u',
      'û' => 'u',
      'ü' => 'u',
      'ñ' => 'n',
      'ç' => 'c',
      'ý' => 'y',
      'ÿ' => 'y',
      // Special combinations
      'ß' => 'ss',
      'Æ' => 'AE',
      'æ' => 'ae',
      'Œ' => 'OE',
      'œ' => 'oe',
    ];
    $name = str_replace(array_keys($char_map), array_values($char_map), $name);

    // Remove any remaining non-ASCII characters (printable ASCII range: 0x20-0x7E)
    $name = preg_replace('/[^\x20-\x7E]/', '', $name);

    // Replace spaces and quotes with hyphens
    $name = preg_replace('/[\s\'"]+/', '-', $name);

    // Remove any remaining characters that are not alphanumeric, dash, or underscore
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name);

    // Replace multiple consecutive hyphens with a single hyphen
    $name = preg_replace('/-+/', '-', $name);

    // Remove leading/trailing hyphens
    $name = trim($name, '-');

    return $name . $extension;
  }

  /**
   * Helper method to normalize image URLs by sanitizing filenames.
   *
   * @param string $url
   *   The image URL to normalize.
   *
   * @return string
   *   The normalized URL.
   */
  private function normalizeImageUrl(string $url): string {
    // Replace /s3/files/ and /sites/default/files/ with /files/
    $normalized_url = StaticFileUrlMapper::normalizeStaticFileUrlPrefix($url);

    // Parse URL to separate path from query/fragment
    $url_parts = parse_url($normalized_url);
    $path = $url_parts['path'] ?? $normalized_url;
    $last_slash_pos = strrpos($path, '/');

    if ($last_slash_pos !== FALSE) {
      $directory_path = substr($path, 0, $last_slash_pos + 1);
      $filename = substr($path, $last_slash_pos + 1);

      // Sanitize the filename to remove special characters
      $sanitized_filename = $this->sanitizeFilename($filename);
      $normalized_path = $directory_path . $sanitized_filename;

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
