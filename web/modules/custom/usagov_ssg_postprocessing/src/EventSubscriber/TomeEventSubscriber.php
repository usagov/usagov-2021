<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\ModifyHtmlEvent;
use Drupal\tome_static\Event\PathPlaceholderEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
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
  public function excludeDirectories(CollectPathsEvent $event): void {
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
        // ContentEntityBase interface require the getTranslation()/hasTranslation() methods
        if (!$entity || (!$entity instanceof ContentEntityBase) || !$entity->hasTranslation($langcode)) {
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
   * @return array<mixed>
   *   An array of excluded paths.
   */
  public static function getExcludedDirectories(): array {
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
  public function modifyHtml(ModifyHtmlEvent $event): void {
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

    if ($path !== '/' && str_ends_with($path, '/')) {
      // Tome should never request the Spanish homepage or any other local path
      // with a trailing-slash. If it does request it, that is because the path
      // was found in the content of a node or term.
      // For example, when tome runs and it finds a link to `/es/`, Drupal will
      // redirect the request for `/es/` to `/es`. The response causes Tome to
      // save it in  the contents of `es/index.html` with an refresh redirect.
      $event->setInvalid();
      return;
    }

    if (preg_match('/(es\/)?node\/\d+$/', $path)) {
      $event->setInvalid();
    }
  }

  /**
   * Add agency index paths to be exported instead of relying on Tome discovering the path
   */
  public function addAgencyIndexes(CollectPathsEvent $event): void {
    $metadata = ['language_processed' => TRUE];
    // Get the English letters to output from the pager view
    $view = Views::getView('federal_agencies');
    $view->setDisplay('attachment_1');

    $metadata['langcode'] = 'en';
    foreach ($this->getLetters($view) as $letter) {
      $event->addPath('/agency-index?letter=' . $letter, $metadata);
    }

    // Get the Spanish letters
    $view = Views::getView('federal_agencies');
    $view->setDisplay('attachment_2');

    $metadata['langcode'] = 'es';
    foreach ($this->getLetters($view) as $letter) {
      $event->addPath('/es/indice-agencias?letter=' . $letter, $metadata);
    }

  }

  /**
   * @return string[]
   */
  private function getLetters(ViewExecutable $view): array {
    $view->execute();
    $letters = [];
    foreach ($view->result as $result) {
      // Tome must create folders that are lower-cased
      $letter = strtolower($result->title_truncated);
      // The "A" page is the default agency-index page, no need to export.
      if ($letter !== 'a') {
        $letters[] = $letter;
      }
    }

    return array_unique($letters);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeStaticEvents::MODIFY_HTML][] = ['modifyHtml'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['excludeDirectories'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['addAgencyIndexes'];
    $events[TomeStaticEvents::PATH_PLACEHOLDER][] = ['excludeInvalidPaths'];
    return $events;
  }

}
