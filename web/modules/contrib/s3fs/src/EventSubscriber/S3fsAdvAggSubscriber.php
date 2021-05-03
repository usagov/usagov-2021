<?php

namespace Drupal\s3fs\EventSubscriber;

use Drupal\advagg\Asset\AssetOptimizationEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\s3fs\Asset\S3fsCssOptimizer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to asset optimization events and update assets urls.
 */
class S3fsAdvAggSubscriber implements EventSubscriberInterface {

  /**
   * Drupal ConfigFactory Service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Base path to use for URI rewrite.
   *
   * @var string
   */
  protected $rewriteFileURIBasePath;

  /**
   * Construct the optimizer instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The optimizer.
   */
  public function __construct(ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [AssetOptimizationEvent::CSS => ['updateUrls', 0]];
  }

  /**
   * Update asset urls to access static files that they aren't in S3 bucket.
   *
   * @param \Drupal\advagg\Asset\AssetOptimizationEvent $asset
   *   The asset optimization event.
   */
  public function updateUrls(AssetOptimizationEvent $asset) {
    $content = $this->processAssetContent($asset);
    $asset->setContent($content);
  }

  /**
   * Process asset content for make urls compatible.
   *
   * @param \Drupal\advagg\Asset\AssetOptimizationEvent $asset
   *
   * @return mixed
   *
   * @see \Drupal\Core\Asset\CssOptimizer::processFile()
   */
  public function processAssetContent(AssetOptimizationEvent $asset) {
    $content = $asset->getContent();
    $css_asset = $asset->getAsset();
    // Get the parent directory of this file, relative to the Drupal root.
    $css_base_path = substr($css_asset['data'], 0, strrpos($css_asset['data'], '/'));
    // Store base path.
    $this->rewriteFileURIBasePath = $css_base_path . '/';
    // Restore asset urls
    $content = str_replace('/' . $this->rewriteFileURIBasePath, "", $content);

    return preg_replace_callback('/url\(\s*[\'"]?(?![a-z]+:|\/+)([^\'")]+)[\'"]?\s*\)/i', [$this, 'rewriteFileURI'], $content);
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
    $reWriter = new S3fsCssOptimizer($this->configFactory);
    $reWriter->rewriteFileURIBasePath = $this->rewriteFileURIBasePath;
    return $reWriter->rewriteFileURI($matches);
  }

}
