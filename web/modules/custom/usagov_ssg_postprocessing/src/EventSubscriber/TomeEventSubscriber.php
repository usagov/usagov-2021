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
   * Reacts to a collect paths event. Replaces any '/es/' (without sub-path) with '/es'
   * Purpose: prevent a condition where Tome creates a redirect page at /es/index.html,
   * presumably due to Drupal and the redirect module making a redirect from /es/ to /es.
   *
   * We don't need to replace /es/ with /es, because Tome will find the /es page as a node
   * to process regardless.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function excludeEsSlash(CollectPathsEvent $event) {
    $paths = $event->getPaths(TRUE);
    foreach ($paths as $path => $metadata) {
      $url_parts = parse_url($path);
      // Redirect module produces paths like "_redirect:1234", hence check for path:
      if (array_key_exists('path', $url_parts) && ($url_parts['path'] == '/es/')) {
        unset($paths[$path]);
      }
    }
    $event->replacePaths($paths);
  }

  /**
   * Reacts to a collect paths event. Deletes any paths that start with specified strings.
   * Intended to exclude whole directories, which should end with "/".
   * Set tome_static_path_include in settings to exclude individual paths; it's built in.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function excludeDirectories(CollectPathsEvent $event) {
    $excluded_directories = self::getExcludedDirectories();
    $paths = $event->getPaths(TRUE);
    foreach ($paths as $path => $metadata) {
      foreach ($excluded_directories as $excluded_directory) {
        if (str_starts_with($path, $excluded_directory) ||
            (isset($metadata['original_path']) && str_starts_with($metadata['original_path'], $excluded_directory))) {
          unset($paths[$path]);
        }
      }
    }
    $event->replacePaths($paths);
  }

  /**
   * Returns per-site excluded paths that look like directories.
   *
   * @return array
   *   An array of excluded paths.
   */
  public static function getExcludedDirectories() {
    $excluded_paths = [];
    $site_paths = Settings::get('tome_static_path_exclude', []);
    if (is_array($site_paths)) {
      foreach ($site_paths as $path) {
        if (str_ends_with($path, '/')) {
          $excluded_paths[] = $path;
        }
      }
    }
    return $excluded_paths;
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['excludeEsSlash'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['excludeDirectories'];
    return $events;
  }

}
