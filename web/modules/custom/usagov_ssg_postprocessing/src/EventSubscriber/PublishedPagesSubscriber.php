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
      $decoded = json_decode($script, TRUE);
      $decoded = $decoded[0];

      // If the nodeID matches a line in the csv array, set the pointer to that element. TODO: this might be fragile.
      if (!empty($csv)) {
        $nodIDElement = array_search("Page ID", $csv[0]);
        foreach ($csv as $key => $line) {
          if ($line[$nodIDElement] == $decoded["nodeID"]) {
            $pointer = $key;
          }
        }
      }

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
        "Taxonomy_Text_1" => "Level 1",
        "Taxonomy_Text_2" => "Level 2",
        "Taxonomy_Text_3" => "Level 3",
        "Taxonomy_Text_4" => "Level 4",
        "Taxonomy_Text_5" => "Level 5",
        "Taxonomy_Text_6" => "Level 6",
        "Page_Type" => "Page Type",
        "basicPagesubType" => "Page Sub Type",
        "contentType" => "Content Type",
        "language" => "Top Level",
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
        "Level 1",
        "Level 2",
        "Level 3",
        "Level 4",
        "Level 5",
        "Level 6",
        "Top Level",
        "Homepage?",
        "Toggle URL",
      ];

      $host = \Drupal::request()->getSchemeAndHttpHost();

      $title = $xpath->query('/html/head/title')->item(0)->nodeValue;
      $title = (!empty($title)) ? str_replace(" | USAGov", "", $title) : "Not Found";

      $decoded["Page Title"] = $title;

      $toggle_url = $xpath->query('/html/body/header/div[1]/div/div/ul/li/a/@href')->item(0)->nodeValue;
      $decoded["Toggle URL"] = ($toggle_url) ? $host . $toggle_url : "None";

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

      foreach ($decoded as $name => $term) {
        if (in_array($name, $url_replace)) {
          $urlArray[] = $term;
          unset($decoded[$name]);
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

      if (!empty($urlArray)) {
        foreach ($urlArray as $key => $item) {
          $term = str_replace("/", "", $item);
          $urlArray[$key] = $term;
        }
        $urlArray = array_unique($urlArray);
        $url = implode("/", $urlArray);
        if ($decoded["Top Level"] == "USAGov Español") {
          $url = "/" . $url;
        }
        $decoded["Friendly URL"] = (empty($url)) ? "/" : $url;
        $decoded["Full URL"] = (empty($url)) ? $host . "/" : $host . $url;
      }

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
