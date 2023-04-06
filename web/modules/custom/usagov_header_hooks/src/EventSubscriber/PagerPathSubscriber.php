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
   * Reacts to a modify destination event. This converts "/es/" to "/es", because
   * Drupal will always redirect a path with a trailing / to its equivalent without,
   * and we do not want to create a redirect at /es/index.html!
   * This catches cases where text content includes links to "/es/".
   * Content based on entity references will never have the trailing /, which is
   * why we have to add it in an EventSubscriber.
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
    $changes = FALSE;
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
        $changes = TRUE;
        $node->setAttribute('href', $new_href);
      }
    }
    if ($changes) {
      $html = $document->saveHTML();
      $event->setHtml($html);
    }
  }

  /**
   * Modifies a URL to replace "/es/" with "/es".
   *
   * @param string $url
   *   A URL.
   *
   * @return string
   *   The modified URL.
   */
  protected function modifyUrl($url) {
    $url_parts = parse_url($url);
    // TODO: Also check that this URL is on-site
    if ($url_parts['path'] == '/es/') {
      $url_parts['path'] = '/es';
      $url = unparse_url($url_parts);
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
