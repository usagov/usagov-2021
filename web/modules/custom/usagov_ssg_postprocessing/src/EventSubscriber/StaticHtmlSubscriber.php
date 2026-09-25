<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\usagov_ssg_postprocessing\SsgMetricTrait;
use Drupal\usagov_ssg_postprocessing\StaticUrlTransformer;
use Masterminds\HTML5;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Applies USAGov-specific HTML changes during static generation.
 */
final class StaticHtmlSubscriber implements EventSubscriberInterface {

  use SsgMetricTrait;

  /**
   * Reacts to an HTML modification event.
   */
  public function modifyHtml(ModifyHtmlEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $html = $event->getHtml();
    $event->addExcludePath('/es/');

    $removed_xlink_namespace = str_contains($html, 'xmlns:xlink="http://www.w3.org/1999/xlink"');
    if ($removed_xlink_namespace) {
      $html = str_replace('xmlns:xlink="http://www.w3.org/1999/xlink"', '', $html);
    }

    $needs_image_normalization = $this->needsImageNormalization($html);
    $needs_spanish_link_rewrite = $this->needsSpanishLinkRewrite($html);
    $needs_letter_rewrite = str_contains($html, '?letter=') || str_contains($html, '&letter=');
    if (!$needs_image_normalization && !$needs_spanish_link_rewrite && !$needs_letter_rewrite) {
      if ($removed_xlink_namespace) {
        $event->setHtml($html);
      }
      $this->ssgMetricEnd('tome_modify_html', $metric_start, 'skipped', [
        'path' => $event->getPath(),
        'xlink_namespace_removed' => $removed_xlink_namespace,
      ]);
      return;
    }

    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);
    $xpath = new \DOMXPath($document);
    $dom_changes = FALSE;
    $image_node_count = 0;
    $image_src_rewrite_count = 0;
    $spanish_link_count = 0;
    $spanish_link_rewrite_count = 0;
    $letter_link_count = 0;
    $letter_replacements = [];

    if ($needs_image_normalization) {
      $debug_image_paths = $this->debugImagePathsEnabled();
      $img_nodes = $xpath->query('//*[@src]');
      $image_node_count = $img_nodes ? $img_nodes->length : 0;
      foreach ($img_nodes as $node) {
        if (!$node instanceof \DOMElement) {
          continue;
        }
        $src = $node->getAttribute('src');
        if (str_contains($src, ' ') || str_contains($src, '%20')) {
          $new_src = str_replace([' ', '%20'], '+', $src);
          $node->setAttribute('src', $new_src);
          $dom_changes = TRUE;
          $image_src_rewrite_count++;
          if ($debug_image_paths) {
            file_put_contents('/tmp/tome-img-path-debug.log', '[IMG] Replaced src: ' . $src . ' -> ' . $new_src . "\n", FILE_APPEND);
          }
        }
        elseif ($debug_image_paths) {
          file_put_contents('/tmp/tome-img-path-debug.log', '[IMG] No change src: ' . $src . "\n", FILE_APPEND);
        }
      }
    }
    else {
      $debug_image_paths = FALSE;
    }

    if ($needs_spanish_link_rewrite) {
      $nodes = $xpath->query('//a[starts-with(@href,"/es")]');
      $spanish_link_count = $nodes ? $nodes->length : 0;
      foreach ($nodes as $node) {
        if (!$node instanceof \DOMElement) {
          continue;
        }
        $original_href = $node->getAttribute('href');
        $new_href = NULL;
        if ($original_href === '/es') {
          $new_href = '/es/';
        }
        elseif (isset($original_href[3]) && ($original_href[3] === '?' || $original_href[3] === '#')) {
          $new_href = '/es/' . substr($original_href, 3);
        }
        if ($new_href) {
          $node->setAttribute('href', $new_href);
          $event->addExcludePath($new_href);
          $dom_changes = TRUE;
          $spanish_link_rewrite_count++;
        }
      }
    }

    if ($needs_letter_rewrite) {
      foreach ($xpath->query('//a[(contains(@href,"?letter=") or contains(@href,"&letter="))]') as $node) {
        if (!$node instanceof \DOMElement) {
          continue;
        }
        $letter_link_count++;
        $original_href = $node->getAttribute('href');
        $path = $original_href[0] === '?' ? strtok($event->getPath(), '?') . $original_href : $original_href;
        $new_href = StaticUrlTransformer::rewriteLetterPath($path);
        $event->addInvokePath($path);
        $event->addExcludePath($new_href);
        $letter_replacements[] = [$original_href, $new_href];
      }
    }

    $output_html = $dom_changes ? (new HTML5())->saveHTML($document) : $html;
    if ($dom_changes) {
      $output_html = str_replace('xmlns:xlink="http://www.w3.org/1999/xlink"', '', $output_html);
    }
    foreach ($letter_replacements as [$original_href, $new_href]) {
      $output_html = str_replace($original_href, $new_href, $output_html);
      $output_html = str_replace(htmlentities($original_href), $new_href, $output_html);
    }
    if ($output_html !== $event->getHtml()) {
      $event->setHtml($output_html);
    }

    $this->ssgMetricEnd('tome_modify_html', $metric_start, 'end', [
      'path' => $event->getPath(),
      'html_bytes' => strlen($html),
      'xlink_namespace_removed' => $removed_xlink_namespace,
      'image_node_count' => $image_node_count,
      'image_src_rewrite_count' => $image_src_rewrite_count,
      'debug_image_paths' => $debug_image_paths,
      'spanish_link_count' => $spanish_link_count,
      'spanish_link_rewrite_count' => $spanish_link_rewrite_count,
      'letter_link_count' => $letter_link_count,
      'letter_rewrite_count' => count($letter_replacements),
    ]);
  }

  /**
   * Determines whether an HTML string could contain a src URL to normalize.
   */
  private function needsImageNormalization(string $html): bool {
    return str_contains($html, '%20') || (bool) preg_match('/\bsrc\s*=\s*(["\'])[^"\']*\s[^"\']*\1/i', $html);
  }

  /**
   * Determines whether an HTML string could contain an /es link to rewrite.
   */
  private function needsSpanishLinkRewrite(string $html): bool {
    return (bool) preg_match('/\bhref\s*=\s*(["\'])\/es(?:\1|[?#])/i', $html);
  }

  /**
   * Determines whether verbose image path logging is enabled.
   */
  private function debugImagePathsEnabled(): bool {
    return filter_var(getenv('SSG_DEBUG_IMAGE_PATHS') ?: FALSE, FILTER_VALIDATE_BOOLEAN);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TomeStaticEvents::MODIFY_HTML => ['modifyHtml'],
    ];
  }

}
