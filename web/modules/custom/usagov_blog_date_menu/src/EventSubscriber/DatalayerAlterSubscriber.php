<?php

namespace Drupal\usagov_blog_date_menu\EventSubscriber;

use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds datalayer data for blog listing and archive view pages.
 *
 * Blog post nodes are already handled by the node-based datalayer builder in
 * usa_twig_vars. This subscriber handles the Views-based blog routes:
 *   /blog              — main blog listing
 *   /blog/{year}       — year archive
 *   /blog/{year}/{mm}  — month archive
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  public function __construct(
    private CurrentRouteMatch $routeMatch,
    private CurrentPathStack $pathCurrent,
    private LanguageManagerInterface $languageManager,
    private ChainBreadcrumbBuilderInterface $breadcrumbManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      DatalayerAlterEvent::EVENT_NAME => 'onDatalayerAlter',
    ];
  }

  /**
   * Populates the datalayer for blog listing and archive pages.
   */
  public function onDatalayerAlter(DatalayerAlterEvent $event): void {
    // Only act on the blog_menu view route; node pages (blog_post) are handled
    // by the node-based datalayer builder in usa_twig_vars.
    $routeName = $this->routeMatch->getRouteName() ?? '';
    if (!str_contains($routeName, 'view.blog_menu')) {
      return;
    }

    $currentPath = $this->pathCurrent->getPath();
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Derive taxonomy slots directly from the breadcrumb so the datalayer
    // stays in sync with the breadcrumb structure automatically. The blog
    // module's hook_system_breadcrumb_alter() is the single source of truth
    // for link text and hierarchy; we just read from it here.
    $crumbs = $this->breadcrumbManager->build($this->routeMatch);
    $links = $crumbs->getLinks();
    $taxonomy = [];

    foreach ($links as $index => $link) {
      $slot = $index + 1;
      if ($slot > 6) {
        break;
      }

      if ($slot === 1) {
        // Slot 1 is always home; use the canonical langcode-specific title/URL
        // regardless of what the breadcrumb link says.
        $taxonomy['Taxonomy_Text_1'] = $langcode === 'es'
          ? TaxonomyDatalayerBuilder::HOME_TITLE_ES
          : TaxonomyDatalayerBuilder::HOME_TITLE_EN;
        $taxonomy['Taxonomy_URL_1'] = $langcode === 'es'
          ? TaxonomyDatalayerBuilder::HOME_URL_ES
          : TaxonomyDatalayerBuilder::HOME_URL_EN;
        continue;
      }

      $taxonomy['Taxonomy_Text_' . $slot] = htmlspecialchars($link->getText(), ENT_QUOTES, 'UTF-8');

      // <none> routes are non-clickable "current page" breadcrumb items;
      // substitute the current path as the real URL.
      $linkUrl = $link->getUrl();
      $taxonomy['Taxonomy_URL_' . $slot] = ($linkUrl->isRouted() && $linkUrl->getRouteName() === '<none>')
        ? $currentPath
        : $linkUrl->toString();
    }

    // Pad any remaining slots (up to 6) by repeating the last entry.
    $count = count($links);
    if ($count < 6) {
      $lastText = $taxonomy['Taxonomy_Text_' . $count];
      $lastUrl  = $taxonomy['Taxonomy_URL_' . $count];
      for ($i = $count + 1; $i <= 6; $i++) {
        $taxonomy['Taxonomy_Text_' . $i] = $lastText;
        $taxonomy['Taxonomy_URL_' . $i]  = $lastUrl;
      }
    }

    $event->datalayer = array_merge($event->datalayer, [
      'language'         => $langcode,
      'homepageTest'     => 'not_homepage',
      'basicPagesubType' => NULL,
      'contentType'      => 'blog_listing',
      'Page_Type'        => 'blog_listing',
    ], $taxonomy);
  }

}
