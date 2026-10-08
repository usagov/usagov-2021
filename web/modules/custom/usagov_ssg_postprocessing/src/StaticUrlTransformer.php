<?php

namespace Drupal\usagov_ssg_postprocessing;

/**
 * Transforms URLs into paths compatible with the static site output.
 */
final class StaticUrlTransformer {

  /**
   * Converts a letter query parameter into a static path segment.
   */
  public static function rewriteLetterPath(string $url): string {
    $query_string = parse_url($url, PHP_URL_QUERY) ?? '';
    $fragment = parse_url($url, PHP_URL_FRAGMENT);
    parse_str($query_string, $query);
    if (!$query || !isset($query['letter'])) {
      return $url;
    }

    $base_path = preg_replace('/\?.*/', '', $url);
    if ($base_path === '/') {
      $base_path = '';
    }
    $url = $base_path . '/' . $query['letter'];
    if ($fragment) {
      $url .= '#' . $fragment;
    }

    return $url;
  }

}
