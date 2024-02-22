<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts "letter" query parameters, as used in A-Z directory views,  to static paths.
 * Based on the tome_static PagerEventSubscriber.
 *
 * @internal
 */
class PagerPathSubscriber implements EventSubscriberInterface {

  /**
   * Reacts to a modify destination event.
   *
   * @param \Drupal\tome_static\Event\ModifyDestinationEvent $event
   *   The event.
   */
  public function modifyDestination(ModifyDestinationEvent $event) {
    $destination = $event->getDestination();
    $new_destination = $this->modifyUrl($destination);
    if ($destination != $new_destination) {
      $event->setDestination($new_destination);
    }
  }

  /**
   * Reacts to a modify HTML event.
   *
   * @param \Drupal\tome_static\Event\ModifyHtmlEvent $event
   *   The event.
   */
  public function modifyHtml(ModifyHtmlEvent $event) {
    $html = $event->getHtml();
    $path = $event->getPath();

    // LIBXML_SCHEMA_CREATE fixes a problem wherein DOMDocument would remove closing HTML
    // tags within quoted text in a script element. See https://bugs.php.net/bug.php?id=74628
    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);
    $xpath = new \DOMXPath($document);
    /** @var \DOMElement $node */
    foreach ($xpath->query('//a[(contains(@href,"?letter=") or contains(@href,"&letter="))]') as $node) {
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
    }
    $event->setHtml($html);
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
