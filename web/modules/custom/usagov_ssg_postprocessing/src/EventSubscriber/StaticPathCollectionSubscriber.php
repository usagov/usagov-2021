<?php

namespace Drupal\usagov_ssg_postprocessing\EventSubscriber;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\tome_static\Event\CollectPathsEvent;
use Drupal\tome_static\Event\TomeStaticEvents;
use Drupal\usagov_ssg_postprocessing\SsgMetricTrait;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Collects and filters USAGov-specific static site paths.
 */
final class StaticPathCollectionSubscriber implements EventSubscriberInterface {

  use SsgMetricTrait;

  public function __construct(private EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Applies USAGov path collection rules in their existing order.
   */
  public function collectPaths(CollectPathsEvent $event): void {
    $this->excludeDirectories($event);
    $this->addAgencyIndexes($event);
    $this->clearBlogViewCache();
    $this->addBlogPaths($event);
  }

  /**
   * Logs the final path collection after all default-priority subscribers run.
   */
  public function logCollectedPathCounts(CollectPathsEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $paths = $event->getPaths(TRUE);
    $counts = [
      'total_count' => count($paths),
      'entity_placeholder_count' => 0,
      'benefit_search_count' => 0,
      'agency_index_count' => 0,
      'blog_count' => 0,
      'json_count' => 0,
      'english_count' => 0,
      'spanish_count' => 0,
      'language_processed_count' => 0,
    ];

    foreach ($paths as $path => $metadata) {
      if (str_starts_with($path, '_entity:')) {
        $counts['entity_placeholder_count']++;
      }
      if (str_contains($path, '/_data/benefits-search/')) {
        $counts['benefit_search_count']++;
      }
      if (str_starts_with($path, '/agency-index') || str_starts_with($path, '/es/indice-agencias')) {
        $counts['agency_index_count']++;
      }
      if ($path === '/blog' || str_starts_with($path, '/blog/')) {
        $counts['blog_count']++;
      }
      if (str_ends_with(parse_url($path, PHP_URL_PATH) ?? $path, '.json')) {
        $counts['json_count']++;
      }
      if (($metadata['langcode'] ?? NULL) === 'en') {
        $counts['english_count']++;
      }
      if (($metadata['langcode'] ?? NULL) === 'es') {
        $counts['spanish_count']++;
      }
      if (!empty($metadata['language_processed'])) {
        $counts['language_processed_count']++;
      }
    }

    $this->ssgMetricEnd('tome_collected_path_counts', $metric_start, 'end', $counts);
  }

  /**
   * Removes paths in configured excluded directories.
   */
  private function excludeDirectories(CollectPathsEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $excluded_directories = Settings::get('usagov_tome_static_path_exclude_directories', []);
    $excluded_directories = is_array($excluded_directories) ? $excluded_directories : [];
    $paths = $event->getPaths(TRUE);
    $initial_count = count($paths);
    $placeholder_count = 0;
    $excluded_count = 0;
    $base_path = rtrim(trim(base_path()), '/');

    foreach ($paths as $path => $metadata) {
      $path_parts = explode(':', $path);
      if ($path_parts[0] === '_entity') {
        $placeholder_count++;
        $entity = $this->entityTypeManager->getStorage($path_parts[1])->load($path_parts[3]);
        if (!$entity instanceof ContentEntityBase || !$entity->hasTranslation($path_parts[2])) {
          continue;
        }
        $entity = $entity->getTranslation($path_parts[2]);
        $url = $entity->toUrl('canonical');
        if (!$entity->access('view') || ($entity->isDefaultTranslation() && !$url->access())) {
          continue;
        }
        $new_path = parse_url($url->toString(), PHP_URL_PATH);
        if ($new_path) {
          unset($paths[$path]);
          $metadata['original_path'] = $path;
          $path = $new_path;
          $paths[$path] = $metadata;
        }
      }

      foreach ($excluded_directories as $excluded_directory_path) {
        $excluded_directory = $excluded_directory_path . '/';
        $exact_match = $path === $excluded_directory_path || $path === $base_path . $excluded_directory_path || (isset($metadata['original_path']) && $metadata['original_path'] === $excluded_directory_path);
        $directory_match = str_starts_with($path, $excluded_directory) || str_starts_with($path, $base_path . $excluded_directory) || (isset($metadata['original_path']) && str_starts_with($metadata['original_path'], $excluded_directory));
        if (($exact_match || $directory_match) && isset($paths[$path])) {
          unset($paths[$path]);
          $excluded_count++;
        }
      }
    }
    $event->replacePaths($paths);
    $this->ssgMetricEnd('tome_exclude_directories', $metric_start, 'end', [
      'initial_count' => $initial_count,
      'final_count' => count($paths),
      'excluded_count' => $excluded_count,
      'placeholder_count' => $placeholder_count,
      'excluded_directory_count' => count($excluded_directories),
    ]);
  }

  /**
   * Adds agency index letter paths.
   */
  private function addAgencyIndexes(CollectPathsEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $metadata = ['language_processed' => TRUE, 'langcode' => 'en'];
    $view = Views::getView('federal_agencies');
    $view->setDisplay('attachment_1');
    $english_letters = $this->getLetters($view);
    foreach ($english_letters as $letter) {
      $event->addPath('/agency-index?letter=' . $letter, $metadata);
    }

    $metadata['langcode'] = 'es';
    $view = Views::getView('federal_agencies');
    $view->setDisplay('attachment_2');
    $spanish_letters = $this->getLetters($view);
    foreach ($spanish_letters as $letter) {
      $event->addPath('/es/indice-agencias?letter=' . $letter, $metadata);
    }

    $this->ssgMetricEnd('tome_add_agency_indexes', $metric_start, 'end', [
      'english_count' => count($english_letters),
      'spanish_count' => count($spanish_letters),
    ]);
  }

  /**
   * Clears the cached blog view before archive paths are added.
   */
  private function clearBlogViewCache(): void {
    $metric_start = $this->ssgMetricStart();
    $view = Views::getView('blog_menu');
    $view->storage->invalidateCaches();
    $this->ssgMetricEnd('tome_clear_blog_view_cache', $metric_start);
  }

  /**
   * Adds the blog listing and archive paths.
   */
  private function addBlogPaths(CollectPathsEvent $event): void {
    $metric_start = $this->ssgMetricStart();
    $metadata = ['language_processed' => TRUE, 'langcode' => 'en'];
    $added_count = 1;
    $event->addPath('/blog', $metadata);

    $links = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties([
      'menu_name' => 'usagov_blog_menu',
      'enabled' => 1,
    ]);
    foreach ($links as $link) {
      if (!$link instanceof MenuLinkContent) {
        continue;
      }
      $uri = $link->get('link')->uri ?? '';
      if (!str_starts_with($uri, 'internal:/blog/')) {
        continue;
      }
      $path = substr($uri, strlen('internal:'));
      if (preg_match('#^/blog/\d{4}(/\d{2})?$#', $path)) {
        $event->addPath($path, $metadata);
        $added_count++;
      }
    }
    $this->ssgMetricEnd('tome_add_blog_paths', $metric_start, 'end', [
      'added_count' => $added_count,
      'menu_link_count' => count($links),
    ]);
  }

  /**
   * Returns the available letters from a view attachment.
   *
   * @return string[]
   *   The unique, non-default letters.
   */
  private function getLetters(ViewExecutable $view): array {
    $view->execute();
    $letters = [];
    foreach ($view->result as $result) {
      $result_values = get_object_vars($result);
      $letter = strtolower((string) ($result_values['title_truncated'] ?? ''));
      if ($letter !== '' && $letter !== 'a') {
        $letters[] = $letter;
      }
    }

    return array_unique($letters);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      TomeStaticEvents::COLLECT_PATHS => [
        ['collectPaths'],
        ['logCollectedPathCounts', -1000],
      ],
    ];
  }

}
