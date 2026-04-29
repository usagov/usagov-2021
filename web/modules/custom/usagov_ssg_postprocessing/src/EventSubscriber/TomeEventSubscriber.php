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

    // QuickFix for USAGOV-2312. We want to remove this meta-data.
    $html = str_replace('xmlns:xlink="http://www.w3.org/1999/xlink"', '', $html);
    // Set stripped HTML in case later DOM changes do not run.
    $event->setHtml($html);
    // LIBXML_SCHEMA_CREATE fixes a problem wherein DOMDocument would remove closing HTML
    // tags within quoted text in a script element. See https://bugs.php.net/bug.php?id=74628
    $document = new \DOMDocument();
    @$document->loadHTML($html, LIBXML_SCHEMA_CREATE);

    $xpath = new \DOMXPath($document);
    $changes = FALSE;

    // USAGOV-2705: Blog body content may still contain CKEditor paste artifacts
    // with low-contrast inline text colors. Strip color declarations during
    // static export so Tome cannot publish failing contrast markup.
    if (self::isBlogPath($event->getPath())) {
      $changes = self::stripBlogInlineColorStyles($xpath) || $changes;
    }

    // --- USAGOV-2515: Replace spaces with '+' in image/media src and srcset attributes ---
    // img[src], source[src], and any srcset attributes
    $img_nodes = $xpath->query('//*[@src]');
    foreach ($img_nodes as $node) {
      if ($node instanceof \DOMElement) {
        $src = $node->getAttribute('src');
        if (strpos($src, ' ') !== FALSE || strpos($src, '%20') !== FALSE) {
          $new_src = str_replace([' ', '%20'], '+', $src);
          $node->setAttribute('src', $new_src);
          $changes = TRUE;
          file_put_contents('/tmp/tome-img-path-debug.log', '[IMG] Replaced src: ' . $src . ' -> ' . $new_src . "\n", FILE_APPEND);
        }
        else {
          file_put_contents('/tmp/tome-img-path-debug.log', '[IMG] No change src: ' . $src . "\n", FILE_APPEND);
        }
      }
    }

    // Existing logic: fix /es links
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
      // QuickFix for USAGOV-2312. We want to remove this meta-data.
      $modifiedHtml = str_replace(
        'xmlns:xlink="http://www.w3.org/1999/xlink"',
        '',
        $modifiedHtml
      );
      $event->setHtml($modifiedHtml);
    }
  }

  /**
   * Checks whether a Tome path is in the blog section.
   *
   * @param string $path
   *   The path currently being exported.
   *
   * @return bool
   *   TRUE when the path is /blog or a child path.
   */
  private static function isBlogPath(string $path): bool {
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    $path = trim($path, '/');

    return $path === 'blog' || str_starts_with($path, 'blog/');
  }

  /**
   * Removes inline color declarations from blog HTML.
   *
   * @param \DOMXPath $xpath
   *   An XPath instance for the page document.
   *
   * @return bool
   *   TRUE if any style attribute was changed.
   */
  private static function stripBlogInlineColorStyles(\DOMXPath $xpath): bool {
    $changed = FALSE;
    $to_unwrap = [];

    $content_style_query = implode('', [
      '//*[@style and (',
      'contains(concat(" ", normalize-space(@class), " "), " blog-post ") or ',
      'contains(concat(" ", normalize-space(@class), " "), " blog-view ") or ',
      'ancestor::*[',
      'contains(concat(" ", normalize-space(@class), " "), " blog-post ") or ',
      'contains(concat(" ", normalize-space(@class), " "), " blog-view ")',
      ']',
      ')]',
    ]);

    foreach ($xpath->query($content_style_query) as $node) {
      if (!$node instanceof \DOMElement || !$node->parentNode) {
        continue;
      }

      [$style_changed, $filtered_style] = self::filterInlineColorStyle($node->getAttribute('style'));
      if (!$style_changed) {
        continue;
      }

      $changed = TRUE;

      if ($filtered_style === '') {
        $node->removeAttribute('style');
        if ($node->nodeName === 'span' && !$node->hasAttributes()) {
          $to_unwrap[] = $node;
        }
      }
      else {
        $node->setAttribute('style', $filtered_style);
      }
    }

    foreach (array_reverse($to_unwrap) as $span) {
      if ($span->parentNode) {
        self::unwrapElement($span);
      }
    }

    return $changed;
  }

  /**
   * Filters contrast-breaking CSS declarations from a style attribute.
   *
   * @param string $style
   *   The original style attribute.
   *
   * @return array{0: bool, 1: string}
   *   Whether the style changed and the filtered style value.
   */
  private static function filterInlineColorStyle(string $style): array {
    $changed = FALSE;
    $declarations = [];

    foreach (explode(';', $style) as $declaration) {
      $declaration = trim($declaration);
      if ($declaration === '' || !str_contains($declaration, ':')) {
        continue;
      }

      [$property, $value] = array_map('trim', explode(':', $declaration, 2));
      if ($property === '' || $value === '') {
        continue;
      }

      if (in_array(strtolower($property), ['color', 'background-color'], TRUE)) {
        $changed = TRUE;
        continue;
      }

      $declarations[] = $property . ': ' . $value;
    }

    return [$changed, implode('; ', $declarations)];
  }

  /**
   * Replaces an element with its children.
   *
   * @param \DOMElement $element
   *   The element to unwrap.
   */
  private static function unwrapElement(\DOMElement $element): void {
    $parent = $element->parentNode;
    if (!$parent) {
      return;
    }

    while ($element->firstChild) {
      $parent->insertBefore($element->firstChild, $element);
    }
    $parent->removeChild($element);
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
   * Clear the cache for the blog listing. This isn't "collecting paths," but
   * it is a thing that we want to get done once before we start processing
   * the blog pages.
   *
   * @param CollectPathsEvent $event
   * @return void
   */
  public function clearBlogViewCache(CollectPathsEvent $event): void {
    $view = Views::getView('blog_menu');
    $view->storage->invalidateCaches();
  }

  /**
   * Add blog year and month archive paths to be exported.
   *
   * The blog view has optional contextual filters (year/month) which means
   * Tome doesn't automatically discover the base /blog path. We explicitly
   * add it here to ensure /blog is generated.

   *
   * Also, year and month archive pages (e.g. /blog/2018, /blog/2018/03) are served
   * by the blog view with contextual filters, so Tome cannot reliably discover them
   * automatically. We read the paths directly from the usagov_blog_menu
   * menu link entities — which are only created for years/months that have
   * published content — and register them explicitly.
   */
  public function addBlogPaths(CollectPathsEvent $event): void {
    $metadata = ['language_processed' => TRUE, 'langcode' => 'en'];

    // Add the base /blog path.
    $event->addPath('/blog', $metadata);

    $links = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => 'usagov_blog_menu', 'enabled' => 1]);

    foreach ($links as $link) {
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $link */
      $uri = $link->get('link')->uri ?? '';
      // URIs are in the form "internal:/blog/YYYY" or "internal:/blog/YYYY/MM".
      // Skip node links (individual blog posts) which Tome discovers normally.
      if (!str_starts_with($uri, 'internal:/blog/')) {
        continue;
      }
      $path = substr($uri, strlen('internal:'));
      // Only register year (/blog/YYYY) and month (/blog/YYYY/MM) paths.
      if (preg_match('#^/blog/\d{4}(/\d{2})?$#', $path)) {
        $event->addPath($path, $metadata);
      }
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
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['clearBlogViewCache'];
    $events[TomeStaticEvents::COLLECT_PATHS][] = ['addBlogPaths'];
    $events[TomeStaticEvents::PATH_PLACEHOLDER][] = ['excludeInvalidPaths'];
    return $events;
  }

}
