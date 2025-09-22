<?php

namespace Drupal\usagov_ssg_postprocessing\Drush\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Finder\Finder;

/**
 * Drush command to copy referenced images from S3FS/public:// to static output.
 * Also rewrites HTML references to static file paths.
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StaticImageSyncCommands extends DrushCommands {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * StaticImageSyncCommands constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    FileSystemInterface $file_system,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct();
    $this->fileSystem = $file_system;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * Create method for dependency injection.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Pre-generate image styles for all blog post featured images.
   *
   * @command usagov:ssg-pregenerate-blog-images
   * @aliases ssg-pregenerate-blog-images
   */
  public function pregenerateBlogImages(): void {
    $this->output()->writeln('Pre-generating image styles for blog post featured images...');

    // Load all published blog posts
    $blog_posts = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'blog_post',
        'status' => 1,
      ]);

    $image_style_storage = $this->entityTypeManager->getStorage('image_style');
    $media_storage = $this->entityTypeManager->getStorage('media');

    // Get important image styles used in blog views
    $important_styles = [
      'cke_media_resize_large', // Used in blog post view
      'cke_media_resize_medium', // May be used in teasers
      'cke_media_resize_small', // May be used in cards
      'thumbnail', // General thumbnail use
      'medium', // Standard medium size
      'large', // Standard large size
    ];

    $generated_count = 0;
    $failed_count = 0;

    foreach ($blog_posts as $blog_post) {
      // @phpstan-ignore-next-line
      if (!$blog_post->hasField('field_featured_image') || $blog_post->get('field_featured_image')->isEmpty()) {
        continue;
      }

      // @phpstan-ignore-next-line
      $featured_image_ref = $blog_post->get('field_featured_image')->first();
      if (!$featured_image_ref) {
        continue;
      }

      $media_entity = $media_storage->load($featured_image_ref->target_id);
      // @phpstan-ignore-next-line
      if (!$media_entity || !$media_entity->hasField('field_media_image') || $media_entity->get('field_media_image')->isEmpty()) {
        continue;
      }

      // @phpstan-ignore-next-line
      $image_file = $media_entity->get('field_media_image')->entity;
      // @phpstan-ignore-next-line
      if (!$image_file) {
        continue;
      }

      /** @var \Drupal\file\Entity\File $image_file */
      $source_uri = $image_file->getFileUri();
      // @phpstan-ignore-next-line
      $this->output()->writeln('Processing blog post: ' . $blog_post->getTitle() . ' (Image: ' . $source_uri . ')');

      foreach ($important_styles as $style_name) {
        $image_style = $image_style_storage->load($style_name);
        if (!$image_style instanceof ImageStyleInterface) {
          continue;
        }

        $derivative_uri = $image_style->buildUri($source_uri);

        // Check if derivative already exists
        if (file_exists($this->fileSystem->realpath($derivative_uri))) {
          continue;
        }

        // Create the derivative
        if ($image_style->createDerivative($source_uri, $derivative_uri)) {
          $this->output()->writeln('  Generated ' . $style_name . ' derivative: ' . $derivative_uri);
          $generated_count++;
        }
        else {
          $this->output()->writeln('  ERROR: Failed to generate ' . $style_name . ' derivative: ' . $derivative_uri);
          $failed_count++;
        }
      }
    }

    $this->output()->writeln('Pre-generation complete:');
    $this->output()->writeln('  Generated: ' . $generated_count . ' image derivatives');
    $this->output()->writeln('  Failed: ' . $failed_count . ' image derivatives');
  }

  /**
   * Copies referenced images from S3FS/public:// to static output and rewrites HTML references.
   *
   * @param array<string, string> $options
   *   Options array with 'html_dir' and 'output_files_dir'.
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
    $image_style_references = [];

    foreach ($finder as $file) {
      $html = file_get_contents($file->getRealPath());
      // Match src and srcset URLs.
      preg_match_all('/(?:src|srcset)="([^"]+)"/', $html, $matches);
      foreach ($matches[1] as $url) {
        // Check for image style URLs first (these need special handling)
        if (preg_match('#/(s3/files|sites/default/files|files)/styles/([^/]+)/.*?/(.*?)(["\?\s])#', $url . ' ', $m)) {
          $style_name = $m[2];
          $original_path = $m[3];
          // Remove query/fragment from original path
          $original_path = preg_replace('/[\?"].*$/', '', $original_path);
          $style_path = $m[1] . '/styles/' . $style_name . '/' . $original_path;
          $image_style_references[$style_path] = [
            'style' => $style_name,
            'original' => $original_path,
            'full_path' => $style_path,
          ];
          $all_files[$style_path] = TRUE;
        }
        // Only process /s3/files/ or /sites/default/files/ or /files/ (original files).
        elseif (preg_match('#/(s3/files|sites/default/files|files)/(.+?)(["\?\s])#', $url . ' ', $m)) {
          $relative_path = $m[1] . '/' . $m[2];
          // Remove query/fragment.
          $relative_path = preg_replace('/[\?"].*$/', '', $relative_path);
          $all_files[$relative_path] = TRUE;
        }
      }
    }

    $this->output()->writeln('Found ' . count($all_files) . ' unique referenced files.');
    $this->output()->writeln('Found ' . count($image_style_references) . ' image style references that may need generation.');

    // First, generate any missing image styles
    $this->generateImageStyles($image_style_references);

    // Then copy all files
    foreach (array_keys($all_files) as $rel_path) {
      $this->copyFile($rel_path, $output_files_dir);
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

  /**
   * Generate missing image styles for referenced images.
   *
   * @param array<string, array{style: string, original: string, full_path: string}> $image_style_references
   *   Array of image style references to generate.
   */
  protected function generateImageStyles(array $image_style_references): void {
    $image_style_storage = $this->entityTypeManager->getStorage('image_style');

    foreach ($image_style_references as $style_info) {
      $style_name = $style_info['style'];
      $original_path = $style_info['original'];

      // Load the image style.
      $image_style = $image_style_storage->load($style_name);
      if (!$image_style instanceof ImageStyleInterface) {
        $this->output()->writeln('WARNING: Image style "' . $style_name . '" not found');
        continue;
      }

      // Construct the source URI for the original image
      $source_uri = 'public://' . $original_path;

      // Check if source file exists
      $real_source = $this->fileSystem->realpath($source_uri);
      if (!$real_source || !file_exists($real_source)) {
        // Try with URL decoding and space/plus replacements
        $alt_paths = [
          str_replace('+', ' ', $original_path),
          str_replace('+', '%20', $original_path),
          urldecode($original_path),
        ];

        foreach ($alt_paths as $alt_path) {
          $alt_source_uri = 'public://' . $alt_path;
          $alt_real_source = $this->fileSystem->realpath($alt_source_uri);
          if ($alt_real_source && file_exists($alt_real_source)) {
            $source_uri = $alt_source_uri;
            $real_source = $alt_real_source;
            $this->output()->writeln('Mapped original path ' . $original_path . ' to ' . $alt_path);
            break;
          }
        }
      }

      if (!$real_source || !file_exists($real_source)) {
        $this->output()->writeln('WARNING: Source image not found: ' . $source_uri);
        continue;
      }

      // Get the derivative URI
      $derivative_uri = $image_style->buildUri($source_uri);

      // Check if derivative already exists
      if (file_exists($this->fileSystem->realpath($derivative_uri))) {
        $this->output()->writeln('Image style derivative already exists: ' . $derivative_uri);
        continue;
      }

      // Create the derivative
      if ($image_style->createDerivative($source_uri, $derivative_uri)) {
        $this->output()->writeln('Generated image style derivative: ' . $derivative_uri);
      }
      else {
        $this->output()->writeln('ERROR: Failed to generate image style derivative: ' . $derivative_uri);
      }
    }
  }

  /**
   * Copy a file from public:// to the static output directory.
   *
   * @param string $rel_path
   *   The relative path of the file to copy.
   * @param string $output_files_dir
   *   The output files directory.
   */
  protected function copyFile(string $rel_path, string $output_files_dir): void {
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
        urldecode($rel_path),
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

}
