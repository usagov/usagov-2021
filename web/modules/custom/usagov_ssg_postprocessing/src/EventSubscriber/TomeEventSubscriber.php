<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Site\Settings;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Converts links to "/es" to "/es/".
 *
 * @internal
 */
class TomeEventSubscriber implements EventSubscriberInterface {

  /**
   * Reacts to a collect paths event. Excludes entire directories by
   * deleting any paths that match the specified string,
   * or that start with the string and a /.
   * Such directories come from the setting usagov_tome_static_path_exclude_directories.
   *
   * (To exclude individual paths, set tome_static_path_exclude -- it's built in.)
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function excludeDirectories(CollectPathsEvent $event) {
    $excluded_directories = self::getExcludedDirectories();
    $paths = $event->getPaths(TRUE);
    foreach ($paths as $path => $metadata) {
      foreach ($excluded_directories as $excluded_directory_path) {
        $excluded_directory = $excluded_directory_path . '/';
        if (($path == $excluded_directory_path) ||
            (isset($metadata['original_path']) && ($metadata['original_path'] == $excluded_directory_path))) {
          unset($paths[$path]);
        }
        elseif (str_starts_with($path, $excluded_directory) ||
            (isset($metadata['original_path']) && str_starts_with($metadata['original_path'], $excluded_directory))) {
          unset($paths[$path]);
        }
      }
    }
    $event->replacePaths($paths);
  }

  /**
   * Returns per-site excluded directory paths.
   *
   * @return array
   *   An array of excluded paths.
   */
  public static function getExcludedDirectories() {
    $excluded_paths = [];
    $site_paths = Settings::get('usagov_tome_static_path_exclude_directories', []);
    if (is_array($site_paths)) {
      foreach ($site_paths as $path) {
        $excluded_paths[] = $path;
      }
    }
    return $excluded_paths;
  }

  /**
   * Reacts to a modify HTML event; replacing links to /es (possibly with a query or
   * fragment appended) with /es/.
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
        $event->addExcludePath($new_href);
      }
    }
    if ($changes) {
      $html = $document->saveHTML();
      $event->setHtml($html);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['excludeDirectories'];
    return $events;
  }

}
