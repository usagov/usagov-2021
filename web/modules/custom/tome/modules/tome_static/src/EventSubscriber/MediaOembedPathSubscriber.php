<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts Media OEmbed query parameters to static paths.
 *
 * @internal
 */
class MediaOembedPathSubscriber implements EventSubscriberInterface {

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
    $document = new \DOMDocument();
    @$document->loadHTML($html);
    $xpath = new \DOMXPath($document);
    /** @var \DOMElement $node */
    foreach ($xpath->query('//iframe[contains(@src,"/media/oembed?url=")]') as $node) {
      $original_src = $node->getAttribute('src');
      $new_src = $this->modifyUrl($original_src);
      $event->addInvokePath($original_src);
      $event->addExcludePath($new_src);
      $html = str_replace($original_src, $new_src, $html);
      $html = str_replace(htmlentities($original_src), $new_src, $html);
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
    $query = parse_url($url, PHP_URL_QUERY);
    if (!empty($query) && preg_match('|.*/media/oembed\?.*|', $url)) {
      $base_path = preg_replace('/\?.*/', '', $url);
      $url = "$base_path/" . md5(urldecode($query));
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
