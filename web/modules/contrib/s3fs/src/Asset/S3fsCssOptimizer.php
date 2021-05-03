<?php

namespace Drupal\s3fs\Asset;

use Drupal\Core\Asset\CssOptimizer;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Optimizes a CSS asset.
 */
class S3fsCssOptimizer extends CssOptimizer {

  /**
   * Drupal ConfigFactory Service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructor for S3fs CSS rewriter.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal service config.factory.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * Return absolute urls to access static files that aren't in S3 bucket.
   *
   * @param array $matches
   *   An array of matches by a preg_replace_callback() call that scans for
   *   url() references in CSS files, except for external or absolute ones.
   *
   * @return string
   *   The file path.
   */
  public function rewriteFileURI($matches) {
    $alwaysSecure = !empty($this->configFactory->get('s3fs.settings')->get('use_https'));
    $useCssjsHost = !empty($this->configFactory->get('s3fs.settings')->get('use_cssjs_host'));
    $cssjsHost = $this->configFactory->get('s3fs.settings')->get('cssjs_host');

    // Prefix with base and remove '../' segments where possible.
    $path = $this->rewriteFileURIBasePath . $matches[1];

    $last = '';
    while ($path != $last) {
      $last = $path;
      $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
    }

    $url = file_create_url($path);

    if ($useCssjsHost && !empty($cssjsHost)) {
      global $base_url;
      $pattern = '#' . $base_url . '#';
      $url = preg_replace($pattern, $cssjsHost, $url);
    }

    // Always use https:// links.
    if ($alwaysSecure) {
      $url = preg_replace('#^http?:#', 'https:', $url);
    }
    else {
      // Strip protocol for protocol independent hyperlinks.
      $url = preg_replace('#^http?:#', '', $url);
    }

    return 'url(' . $url . ')';
  }

}
