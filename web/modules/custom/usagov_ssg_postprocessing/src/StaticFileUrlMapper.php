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
    // Reject absolute URLs pointing at a host we do not own. Other agencies
    // also serve Drupal-style "/sites/default/files/" and "/files/" paths, so
    // a bare substring match would wrongly map an off-site link (e.g. a PDF on
    // an hhs.gov or army.mil page) onto our public:// scheme. Relative and
    // protocol-relative URLs have no foreign host and pass through.
    if (preg_match('~^(?:[a-z][a-z0-9+.\-]*:)?//([^/?#]*)~i', $url, $authority_match)) {
      $host = preg_replace(['/^[^@]*@/', '/:\d+$/'], '', $authority_match[1]);
      $host = strtolower($host);
      if ($host !== '' && $host !== 'usa.gov' && !str_ends_with($host, '.usa.gov')) {
        return NULL;
      }
    }

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
   *
   * Drupal writes aggregated JavaScript and CSS beneath /files/js/ and
   * /files/css/. Those bundles are produced during generation and never exist
   * as public:// S3FS objects, so they must not be treated as referenced
   * assets to copy.
   */
  public static function isGeneratedStaticAssetUrl(string $url): bool {
    return str_contains($url, '/files/js/') || str_contains($url, '/files/css/');
  }

  /**
   * Extracts candidate asset URLs referenced by a static HTML document.
   *
   * Covers src attributes, srcset candidate lists, href download links, and
   * CSS url() references in <style> blocks and inline style attributes.
   * Callers are expected to filter the result through publicUriFromFileUrl()
   * so only public:// file references are retained; non-file URLs (page
   * links, off-site assets) are returned here but dropped downstream.
   *
   * @return string[]
   *   The referenced URLs in the order each pattern is scanned, including
   *   duplicates.
   */
  public static function referencedUrlsFromHtml(string $html): array {
    $urls = [];

    // src="..." attributes: images, iframes, scripts, and media sources.
    if (preg_match_all('/\bsrc="([^"]+)"/i', $html, $matches)) {
      foreach ($matches[1] as $url) {
        $urls[] = $url;
      }
    }

    // srcset="url 320w, url 640w" responsive candidate lists. Each
    // comma-separated candidate is "url [descriptor]"; keep the URL token.
    if (preg_match_all('/\bsrcset="([^"]+)"/i', $html, $matches)) {
      foreach ($matches[1] as $srcset) {
        foreach (explode(',', $srcset) as $candidate) {
          $candidate = trim($candidate);
          if ($candidate !== '') {
            $urls[] = preg_split('/\s+/', $candidate, 2)[0];
          }
        }
      }
    }

    // href="..." links, e.g. PDF and document downloads under /files/.
    if (preg_match_all('/\bhref="([^"]+)"/i', $html, $matches)) {
      foreach ($matches[1] as $url) {
        $urls[] = $url;
      }
    }

    // CSS url(...) references, e.g. background images and @font-face fonts.
    if (preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $html, $matches)) {
      foreach ($matches[1] as $url) {
        $urls[] = trim($url);
      }
    }

    return $urls;
  }

}
