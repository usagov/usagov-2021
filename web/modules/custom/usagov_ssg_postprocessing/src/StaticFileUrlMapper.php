<?php

namespace Drupal\usagov_ssg_postprocessing;

/**
 * Maps file URLs emitted during static generation to their public URIs.
 */
final class StaticFileUrlMapper {

  /**
   * Returns the public URI that corresponds to a supported file URL.
   */
  public static function publicUriFromFileUrl(string $url): ?string {
    foreach ([
      '/s3/files/',
      '/sites/default/files/',
      '/files/',
    ] as $prefix) {
      $prefix_position = strpos($url, $prefix);
      if ($prefix_position === FALSE) {
        continue;
      }

      $relative_path = substr($url, $prefix_position + strlen($prefix));
      $relative_path = preg_split('/[?#]/', $relative_path, 2)[0];
      $relative_path = str_replace(['%20', ' '], '+', $relative_path);

      return 'public://' . $relative_path;
    }

    return NULL;
  }

  /**
   * Normalizes legacy public file URL prefixes for static output.
   */
  public static function normalizeStaticFileUrlPrefix(string $url): string {
    return str_replace(
      ['/s3/files/', '/sites/default/files/'],
      '/files/',
      $url,
    );
  }

  /**
   * Returns the path used beneath the static output directory.
   */
  public static function staticOutputRelativePathFromFileUrl(string $url): ?string {
    if (self::publicUriFromFileUrl($url) === NULL) {
      return NULL;
    }

    $static_url = self::normalizeStaticFileUrlPrefix($url);
    $path = preg_split('/[?#]/', $static_url, 2)[0];
    $path_position = strpos($path, '/files/');

    return $path_position === FALSE ? NULL : substr($path, $path_position + 1);
  }

  /**
   * Determines whether a URL is a generated static asset, not an S3FS object.
   */
  public static function isGeneratedStaticAssetUrl(string $url): bool {
    return str_contains($url, '/files/js/');
  }

}
