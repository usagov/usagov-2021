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

      $relative_path = substr($url, $prefix_position + 1);
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

}
