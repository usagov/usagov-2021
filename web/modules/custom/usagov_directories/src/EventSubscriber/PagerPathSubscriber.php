<?php

namespace Drupal\usagov_directories\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts "letter" query parameters, as used in A-Z directorie views,  to static paths.
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
    $destination = $this->modifyUrl($destination);
    $event->setDestination($destination);
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
    $document = new \DOMDocument();
    @$document->loadHTML($html);
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
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    if ($query && isset($query['letter'])) {
      $base_path = preg_replace('/\?.*/', '', $url);
      if ($base_path === '/') {
        $base_path = '';
      }
      $url = $base_path . '/' . $query['letter'];
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
