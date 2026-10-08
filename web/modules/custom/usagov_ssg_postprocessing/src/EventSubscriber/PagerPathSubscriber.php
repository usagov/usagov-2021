<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\usagov_ssg_postprocessing\SsgMetricTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts "letter" query parameters, as used in A-Z directory views,  to static paths.
 * Based on the tome_static PagerEventSubscriber.
 *
 * @internal
 */
class PagerPathSubscriber implements EventSubscriberInterface {

  use SsgMetricTrait;

  /**
   * Reacts to a modify destination event.
   *
   * @param \Drupal\tome_static\Event\ModifyDestinationEvent $event
   *   The event.
   */
  public function modifyDestination(ModifyDestinationEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $destination = $event->getDestination();
    $new_destination = $this->modifyUrl($destination);
    if ($destination != $new_destination) {
      $event->setDestination($new_destination);
      $this->ssgMetricEnd('tome_letter_modify_destination', $metric_start, 'end', [
        'changed' => TRUE,
        'destination' => $destination,
        'new_destination' => $new_destination,
      ]);
    }
  }

  /**
   * Reacts to a modify HTML event.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The event.
   */
  public function modifyHtml(ModifyHtmlEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $html = $event->getHtml();
    $path = $event->getPath();

    // LIBXML_SCHEMA_CREATE fixes a problem wherein DOMDocument would remove closing HTML
    // tags within quoted text in a script element. See https://bugs.php.net/bug.php?id=74628
    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);
    $xpath = new \DOMXPath($document);
    $letter_link_count = 0;
    $letter_rewrite_count = 0;
    /** @var \DOMElement $node */
    foreach ($xpath->query('//a[(contains(@href,"?letter=") or contains(@href,"&letter="))]') as $node) {
      $letter_link_count++;
      $original_href = $node->getAttribute('href');
      if ($original_href[0] === '?') {
        $new_href = strtok($path, '?') . $original_href;
      }
      else {
        $new_href = $original_href;
      }
      $event->addInvokePath($new_href);
      $new_href = $this->modifyUrl($new_href);
      $event->addExcludePath($new_href);
      $html = str_replace($original_href, $new_href, $html);
      $html = str_replace(htmlentities($original_href), $new_href, $html);
      $letter_rewrite_count++;
    }
    $event->setHtml($html);
    $this->ssgMetricEnd('tome_letter_modify_html', $metric_start, 'end', [
      'path' => $path,
      'letter_link_count' => $letter_link_count,
      'letter_rewrite_count' => $letter_rewrite_count,
      'html_bytes' => strlen($html),
    ]);
  }

  /**
   * Modifies a URL to replace pager query parameters with paths.
   *
   * @param string $url
   *   A URL.
   *
   * @return string
   *   The modified URL.
   */
  protected function modifyUrl($url) {
    $parsed_url = parse_url($url, PHP_URL_QUERY) ?? '';
    $fragment = parse_url($url, PHP_URL_FRAGMENT);
    parse_str($parsed_url, $query);
    if ($query && isset($query['letter'])) {
      $base_path = preg_replace('/\?.*/', '', $url);
      if ($base_path === '/') {
        $base_path = '';
      }
      $url = $base_path . '/' . $query['letter'];
      if ($fragment) {
        $url .= '#' . $fragment;
      }
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_DESTINATION][] = ['modifyDestination'];
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    return $events;
  }

}
