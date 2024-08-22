<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Scrapes information about the page and writes it to a CSV file.
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

    // LIBXML_SCHEMA_CREATE fixes a problem wherein DOMDocument would remove closing HTML
    // tags within quoted text in a script element. See https://bugs.php.net/bug.php?id=74628
    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);
    $xpath = new \DOMXPath($document);

    $csv_path = "modules/custom/usagov_ssg_postprocessing/files/published-pages.csv";
    $csv = [];
    $fp = fopen($csv_path, 'c+');
    if ($fp != FALSE) {
      flock($fp, LOCK_EX);
      while (($line = fgetcsv($fp)) != FALSE) {
        $csv[] = $line;
      }
    }

    // Set the pointer to the end of the array by default.
    end($csv);
    $pointer = (key($csv) == FALSE) ? 0 : key($csv) + 1;

    /** @var \DOMElement $node */
    foreach ($xpath->query('/html/head/script[contains(@id, "taxonomy-data")]') as $node) {
      $script = $node->nodeValue;
      $script = trim($script);
      $script = ltrim($script, "dataLayer = ");
      $script = rtrim($script, ";");
      $script = rtrim($script, ",");
      $decoded = json_decode($script, TRUE);
      $decoded = $decoded[0];

      $url_replace = [
        "Taxonomy_URL_1",
        "Taxonomy_URL_2",
        "Taxonomy_URL_3",
        "Taxonomy_URL_4",
        "Taxonomy_URL_5",
        "Taxonomy_URL_6"
      ];

      $header_replace = [
        "nodeID" => "Page ID",
        "language" => "Taxonomy Level 1",
        "Taxonomy_Text_2" => "Taxonomy Level 2",
        "Taxonomy_Text_3" => "Taxonomy Level 3",
        "Taxonomy_Text_4" => "Taxonomy Level 4",
        "Taxonomy_Text_5" => "Taxonomy Level 5",
        "Taxonomy_Text_6" => "Taxonomy Level 6",
        "Taxonomy_URL_1" => "Taxonomy URL Level 1",
        "Taxonomy_URL_2" => "Taxonomy URL Level 2",
        "Taxonomy_URL_3" => "Taxonomy URL Level 3",
        "Taxonomy_URL_4" => "Taxonomy URL Level 4",
        "Taxonomy_URL_5" => "Taxonomy URL Level 5",
        "Taxonomy_URL_6" => "Taxonomy URL Level 6",
        "Page_Type" => "Page Type",
        "basicPagesubType" => "Page Sub Type",
        "contentType" => "Content Type",

        "homepageTest" => "Homepage?",
      ];

      $content_replace = [
        "en" => "USAGov English",
        "es" => "USAGov Español",
      ];

      $order_map = [
        "Hierarchy Level",
        "Page Type",
        "Page Sub Type",
        "Content Type",
        "Friendly URL",
        "Page ID",
        "Page Title",
        "Full URL",
        "Taxonomy Level 1",
        "Taxonomy Level 2",
        "Taxonomy Level 3",
        "Taxonomy Level 4",
        "Taxonomy Level 5",
        "Taxonomy Level 6",
        "Taxonomy URL Level 1",
        "Taxonomy URL Level 2",
        "Taxonomy URL Level 3",
        "Taxonomy URL Level 4",
        "Taxonomy URL Level 5",
        "Taxonomy URL Level 6",
        "Homepage?",
        "Toggle URL",
      ];

      // If the nodeID matches a line in the csv array, set the pointer to that element.
      // TODO: this might be fragile.
      if (!empty($csv)) {
        $nodeIDElement = array_search("Page ID", $csv[0]);
        $languageElement = array_search("Taxonomy Level 1", $csv[0]);
        foreach ($csv as $key => $line) {
          if ($line[$nodeIDElement] == $decoded["nodeID"]) {
            if ($line[$languageElement] == $content_replace[$decoded["language"]]) {
              $pointer = $key;
            }
          }
        }
      }

      $host = \Drupal::request()->getSchemeAndHttpHost();

      $title = $xpath->query('/html/head/title')->item(0)->nodeValue;
      $title = (!empty($title)) ? str_replace(" | USAGov", "", $title) : "Not Found";

      $decoded["Page Title"] = $title;

      $toggle_url = $xpath->query('/html/head/link[contains(@data-type, "altlang")]/@href')->item(0)->nodeValue;
      $decoded["Toggle URL"] = ($toggle_url) ? $toggle_url : "None";

      $hierarchy = 0;
      $prev = "";
      foreach ($decoded as $key => $term) {
        if (in_array($key, $url_replace)) {
          if ($term != $prev) {
            $hierarchy++;
            $prev = $term;
          }
        }
      }
      $decoded["Hierarchy Level"] = $hierarchy;

      $url = "";
      foreach ($decoded as $name => $term) {
        if ($name == "Taxonomy_Text_1") {
          unset($decoded[$name]);
        }
        if ($name == "Taxonomy_URL_6") {
          $url = $term;
        }
        if ($name == "language") {
          $term = $content_replace[$term];
        }
        foreach ($header_replace as $key => $item) {
          if ($name == $key) {
            $decoded[$item] = $term;
            unset($decoded[$name]);
          }
        }
      }
      // Tome can end up requesting existing URLs with the raw `/node/NID` path
      // if a redirect to a node is set to the wrong language. It then proceeds
      // which retrieves the wrong taxonomy info, which we should discard.
      if (str_starts_with($url, '/node/') || str_starts_with($url, '/es/node/')) {
        if ($fp != FALSE) {
          fclose($fp);
        }
        return;
      }

      // If this page is more than 5 levels deep in the taxonomy hierarchy,
      // Then we may not be able to reconstruct its URL from the taxonomy URL.
      // We can get reliably get it from the node. We could do this for all
      // nodes, but that could negatively impact export performance.
      if ($decoded['Page ID'] && $hierarchy > 5) {
        $nid = $decoded['Page ID'];
        $nodeEntity = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
        $url = $nodeEntity->toUrl()->toString();
      }

      $decoded["Friendly URL"] = (empty($url)) ? "/" : $url;
      $decoded["Full URL"] = (empty($url)) ? $host . "/" : $host . $url;

      $orderedArray = array_merge(array_flip($order_map), $decoded);

      if (empty($csv)) {
        foreach ($orderedArray as $name => $term) {
          $csvheader[] = $name;
        }
        $csv[$pointer] = $csvheader;
        $pointer++;
      }

      foreach ($orderedArray as $name => $term) {
        $csvline[] = $term;
      }

      $csv[$pointer] = $csvline;

      if ($fp != FALSE) {
        ftruncate($fp, 0);
        rewind($fp);
        foreach ($csv as $fields) {
          fputcsv($fp, $fields);
        }
        fclose($fp);
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
