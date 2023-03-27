<?php

namespace Drupal\usagov_header_hooks\EventSubscriber;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts links to "/es" to "/es/".
 * Based on the tome_static PagerEventSubscriber.
 *
 * @internal
 */
class PagerPathSubscriber implements EventSubscriberInterface {

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
    foreach ($xpath->query('//a[(starts-with(@href,"/es"))]') as $node) {
      $original_href = $node->getAttribute('href');
      $new_href = NULL;
      if ($original_href === '/es') {
        $new_href = '/es/';
      }
      elseif ($original_href[3] === '?') {
        $new_href = '/es/' . substr($original_href, 3);
      }
      elseif ($original_href[3] === '#') {
        $new_href = '/es/' . substr($original_href, 3);
      }
      if ($new_href) {
        $html = str_replace($original_href, $new_href, $html);
        $event->setHtml($html);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    return $events;
  }

}
