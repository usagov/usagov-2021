<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Masterminds\HTML5;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This event subscriber modifies static site generation:
 *  - During path collection, removes excluded directories -- allowing us to
 *    specify the omission of entire directories like jsonapi, node, etc.
 *  - During HTML modification, adds a trailing slash to links to the "/es" path.
 *
 * @internal
 */
class TomeEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs the EntityPathSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
  }

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
      /**
       * We are going to spend the time here to get the "real" paths for any
       * placeholder-ed paths, so we can identify and exclude what we want to skip.
       * Tome would normally do this later in its process.
       */
      $path_parts = explode(':', $path);
      if ($path_parts[0] == '_entity') {
        $entity_type = $path_parts[1];
        $langcode = $path_parts[2];
        $entity_id = $path_parts[3];

        $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
        if (!$entity | (!$entity instanceof ContentEntityInterface) || !$entity->hasTranslation($langcode)) {
          continue;
        }
        $entity = $entity->getTranslation($langcode);
        $url = $entity->toUrl('canonical');
        if (!$entity->access('view') || ($entity->isDefaultTranslation() && !$url->access())) {
          continue;
        }
        if ($newpath = parse_url($url->toString(), PHP_URL_PATH)) {
          unset($paths[$path]);
          $metadata['original_path'] = $path;
          $path = $newpath; // Next block tests $path against excluded directories.
          $paths[$path] = $metadata;
        }
      }
      // Get the base path without trailing slash if we're exporting to a sub-directory
      $base_path = rtrim(trim(base_path()), '/');
      foreach ($excluded_directories as $excluded_directory_path) {
        $excluded_directory = $excluded_directory_path . '/';
        if (($path == $excluded_directory_path) ||
            ($path == $base_path . $excluded_directory_path) ||
            (isset($metadata['original_path']) && ($metadata['original_path'] == $excluded_directory_path))) {
          unset($paths[$path]);
        }
        elseif (str_starts_with($path, $excluded_directory) ||
            (str_starts_with($path, $base_path . $excluded_directory)) ||
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
    $html5 = new HTML5();

    // LIBXML_SCHEMA_CREATE fixes a problem wherein DOMDocument would remove closing HTML
    // tags within quoted text in a script element. See https://bugs.php.net/bug.php?id=74628
    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);

    $xpath = new \DOMXPath($document);
    $changes = FALSE;
    $nodes = $xpath->query('//a[starts-with(@href,"/es")]');

    /** @var \DOMElement $node */
    foreach ($nodes as $node) {
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

    // Never crawl the rewritten Spanish path. It might be treated like a redirect by
    // Tome and overwrite the original homepage HTML
    $event->addExcludePath('/es/');

    if ($changes) {
      // Render it as HTML5:
      $modifiedHtml = $html5->saveHTML($document);
      $event->setHtml($modifiedHtml);
    }
  }

  /**
   * Prevent exporting paths Tome might discover after the collect paths event.
   *
   * @param PathPlaceholderEvent $event
   * @return void
   */
  public function excludeInvalidPaths(PathPlaceholderEvent $event) {
    $path = $event->getPath();

    if (preg_match('/(es\/)?node\/\d+$/', $path)) {
      $event->setInvalid();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['excludeDirectories'];
    $events[TomeStaticEvents::PATH_PLACEHOLDER][] = ['excludeInvalidPaths'];
    return $events;
  }

}
