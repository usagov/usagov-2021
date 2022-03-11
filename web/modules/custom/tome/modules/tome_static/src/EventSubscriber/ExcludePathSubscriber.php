<?php

namespace Drupal\tome_static\EventSubscriber;

use Drupal\Core\Site\Settings;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Excludes a paths from static generation.
 *
 * @internal
 */
class ExcludePathSubscriber implements EventSubscriberInterface {

  /**
   * A hard-coded list of paths to always exclude.
   *
   * @var array
   */
  const EXCLUDED_PATHS = [
    '/big_pipe/no-js',
    '/filter/tips',
    '/history/get_node_read_timestamps',
    '/machine_name/transliterate',
    '/system/files',
    '/system/temporary',
    '/system/timezone',
    '/batch',
    '/session/token',
    '/user/password',
    '/user/login',
    '/user/login_status',
    '/views/ajax',
    '/user/register',
    '/system/4xx',
    '/system/404',
    '/system/403',
    '/system/401',
    '/comments/render_new_comments_node_links',
    '/media/oembed',
  ];

  /**
   * Reacts to a collect paths event.
   *
   * @param \Drupal\tome_static\Event\CollectPathsEvent $event
   *   The collect paths event.
   */
  public function collectPaths(CollectPathsEvent $event) {
    $excluded_paths = self::getExcludedPaths();
    $paths = $event->getPaths(TRUE);
    foreach ($paths as $path => $metadata) {
      if (in_array($path, $excluded_paths, TRUE) || (isset($metadata['original_path']) && in_array($metadata['original_path'], $excluded_paths, TRUE))) {
        unset($paths[$path]);
      }
    }
    $event->replacePaths($paths);
  }

  /**
   * Returns paths excluded globally and per site.
   *
   * @return array
   *   An array of excluded paths.
   */
  public static function getExcludedPaths() {
    $excluded_paths = self::EXCLUDED_PATHS;
    $site_paths = Settings::get('tome_static_path_exclude', []);
    if (is_array($site_paths)) {
      $excluded_paths = array_merge(self::EXCLUDED_PATHS, $site_paths);
    }
    return $excluded_paths;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['collectPaths', -2];
    return $events;
  }

}
