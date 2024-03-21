<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyDestinationEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts "letter" query parameters, as used in A-Z directory views,  to static paths.
 * Based on the tome_static PagerEventSubscriber.
 *
 * @internal
 */
class PublishedPagesSubscriber implements EventSubscriberInterface {

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
    $csv = [];
    $fpr = fopen('PublishedPages.csv', 'r');
    if ($fpr != FALSE) {
      while (($line = fgetcsv($fpr)) != FALSE) {
        $csv[] = $line;
      }
      fclose($fpr);
    }
    /** @var \DOMElement $node */
    foreach ($xpath->query('/html/head/script[contains(@id, "taxonomy-data")]') as $node) {
      $script = $node->nodeValue;
      $script = trim($script);
      $script = ltrim($script, "dataLayer = ");
      $script = rtrim($script, ";");
      $decoded = json_decode($script, TRUE);
      $decoded = $decoded[0];

      if (empty($csv)) {
        foreach ($decoded as $name => $term) {
          $csvheader[] = $name;
        }
        $csv[] = $csvheader;
      }

      foreach ($decoded as $name => $term) {
        $csvline[] = $term;
      }
      $csv[] = $csvline;
    }

    $fpw = fopen('PublishedPages.csv', 'c');
    if ($fpw != FALSE) {
      foreach ($csv as $fields) {
          fputcsv($fpw, $fields);
      }
      fclose($fpw);
    }

    $event->setHtml($html);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    return $events;
  }

}
