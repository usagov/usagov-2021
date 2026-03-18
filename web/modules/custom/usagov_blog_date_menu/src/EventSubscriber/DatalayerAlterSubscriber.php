<?php

namespace Drupal\usagov_blog_date_menu\EventSubscriber;

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

    [$homeText, $homeUrl] = $langcode === 'es'
      ? [TaxonomyDatalayerBuilder::HOME_TITLE_ES, TaxonomyDatalayerBuilder::HOME_URL_ES]
      : [TaxonomyDatalayerBuilder::HOME_TITLE_EN, TaxonomyDatalayerBuilder::HOME_URL_EN];

    // Taxonomy slots 1–4 are the same for all blog view pages.
    $taxonomy = [
      'Taxonomy_Text_1' => $homeText,
      'Taxonomy_URL_1'  => $homeUrl,
      'Taxonomy_Text_2' => 'About USAGov',
      'Taxonomy_URL_2'  => '/about',
      'Taxonomy_Text_3' => 'Products and programs',
      'Taxonomy_URL_3'  => '/products-programs',
      'Taxonomy_Text_4' => 'Our blog',
      'Taxonomy_URL_4'  => '/blog',
    ];

    if (preg_match('#^/blog/(\d{4})/(\d{1,2})$#', $currentPath, $matches)) {
      // /blog/2025/01 — year + month archive.
      $year = $matches[1];
      $month = $matches[2];
      $monthName = date('F', mktime(0, 0, 0, (int) $month, 1));
      $taxonomy['Taxonomy_Text_5'] = $year;
      $taxonomy['Taxonomy_URL_5']  = '/blog/' . $year;
      $taxonomy['Taxonomy_Text_6'] = $monthName;
      $taxonomy['Taxonomy_URL_6']  = '/blog/' . $year . '/' . $month;
    }
    elseif (preg_match('#^/blog/(\d{4})$#', $currentPath, $matches)) {
      // /blog/2025 — year archive. Repeat year for slots 5–6 (no deeper crumb).
      $year = $matches[1];
      $taxonomy['Taxonomy_Text_5'] = $year;
      $taxonomy['Taxonomy_URL_5']  = '/blog/' . $year;
      $taxonomy['Taxonomy_Text_6'] = $year;
      $taxonomy['Taxonomy_URL_6']  = '/blog/' . $year;
    }
    else {
      // /blog — main listing. Repeat last crumb to fill slots 5–6.
      $taxonomy['Taxonomy_Text_5'] = 'Our blog';
      $taxonomy['Taxonomy_URL_5']  = '/blog';
      $taxonomy['Taxonomy_Text_6'] = 'Our blog';
      $taxonomy['Taxonomy_URL_6']  = '/blog';
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
