<?php

namespace Drupal\usagov_ssg_postprocessing\Data;

use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;

/**
 * Data structure describing the columns of the Published Pages CSV
 *
 * @phpstan-import-type TaxonomyBreadcrumb from TaxonomyDatalayerBuilder
 */
final class PublishedPagesRow {

  public function __construct(
    // depths in menu tree
    public readonly int $hierarchy,
    public readonly string $pageType,
    // path alias
    public readonly string $friendlyURL,
    // node ID
    public readonly string $pageID,
    public readonly string $pageTitle,
    // complete URL including domain name
    public readonly string $fullURL,
    // We call the pages and URLs here "Taxonomy" but they aren't Drupal taxonomy terms.
    // The following properties come from home page + menu-driven breadcrumbs.
    public readonly string $taxonomyText1,
    public readonly string $taxonomyText2,
    public readonly string $taxonomyText3,
    public readonly string $taxonomyText4,
    public readonly string $taxonomyText5,
    public readonly string $taxonomyText6,
    public readonly string $taxonomyURL1,
    public readonly string $taxonomyURL2,
    public readonly string $taxonomyURL3,
    public readonly string $taxonomyURL4,
    public readonly string $taxonomyURL5,
    public readonly string $taxonomyURL6,
    // language toggle
    public readonly string $toggleURL,
    public readonly string $hasBenefitCategory,
    public readonly string $benefitCategories,
    public readonly string $pageLanguage,
  ) {}

  /**
   * @param array<mixed> $data
   */
  private static function getHierarchy(array $data): int {
    $texts = array_filter($data, fn($key) => str_starts_with($key, 'Taxonomy_URL_'), ARRAY_FILTER_USE_KEY);
    return count(array_unique($texts));
  }

  /**
   * @return array<mixed>
   */
  public function toArray(): array {
    $array = [
      $this->hierarchy,
      $this->pageType,
      $this->friendlyURL,
      $this->pageID,
      $this->pageTitle,
      $this->fullURL,
      $this->taxonomyText1,
      $this->taxonomyText2,
      $this->taxonomyText3,
      $this->taxonomyText4,
      $this->taxonomyText5,
      $this->taxonomyText6,
      $this->taxonomyURL1,
      $this->taxonomyURL2,
      $this->taxonomyURL3,
      $this->taxonomyURL4,
      $this->taxonomyURL5,
      $this->taxonomyURL6,
      $this->toggleURL ?? ' ',
      $this->hasBenefitCategory ?? ' ',
      $this->pageLanguage ?? ' ',
    ];

    // Keeps existing behavior of only including these columns if they have something
    if ($this->hasBenefitCategory !== "") {
      $array[] = $this->benefitCategories;
    }
    return $array;
  }

  /**
   * @param TaxonomyBreadcrumb $data
   */
  public static function datalayerForNode(array $data, NodeInterface $node, string $baseURL): self {
    $title = $node->getTitle();

    // Federal Agency nodes tack on the acronym because the original implementation
    // pulled the title from the HTML
    if ($node->getType() === 'directory_record') {
      $acronym = $node->get('field_acronym')->getValue();
      if ($acronym) {
        $title = sprintf("%s (%s)", trim($title), trim($acronym[0]['value']));
      }
    }

    if ($data['homepageTest'] === 'homepage' && $data['language'] === 'en') {
      $friendlyURL = TaxonomyDatalayerBuilder::HOME_URL_EN;
      $fullURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_EN;
    }
    elseif ($data['homepageTest'] === 'homepage' && $data['language'] === 'es') {
      $friendlyURL = TaxonomyDatalayerBuilder::HOME_URL_ES;
      $fullURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_ES;
    }
    else {
      $friendlyURL = $node->toUrl('canonical',
        options: ['language' => $node->language()]
      )->toString();
      $fullURL = $node->toUrl(
        options: ['absolute' => TRUE, 'language' => $node->language()]
      )->toString();
    }

    $toggleURL = NULL;
    if (isset($node->field_language_toggle[0]) && $node->field_language_toggle[0]->target_id) {
      if ($data['homepageTest'] === 'homepage' && $data['language'] === 'en') {
        $toggleURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_ES;
      }
      elseif ($data['homepageTest'] === 'homepage' && $data['language'] === 'es') {
        $toggleURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_EN;
      }
      else {
        $toggleNode = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->load($node->field_language_toggle[0]->target_id);
        $url = Url::fromRoute(
          'entity.node.canonical',
          ['node' => $toggleNode->id()],
          ['absolute' => TRUE, 'language' => $toggleNode->language()]
        );
        $toggleURL = $url->toString();
      }
    }

    return new self(
      hierarchy: self::getHierarchy($data),
      pageType: $data['Page_Type'],
      friendlyURL: $friendlyURL,
      pageID: $data['nodeID'],
      pageTitle: $title,
      fullURL: $fullURL,
      taxonomyText1: $data['Taxonomy_Text_1'],
      taxonomyText2: $data['Taxonomy_Text_2'],
      taxonomyText3: $data['Taxonomy_Text_3'],
      taxonomyText4: $data['Taxonomy_Text_4'],
      taxonomyText5: $data['Taxonomy_Text_5'],
      taxonomyText6: $data['Taxonomy_Text_6'],
      taxonomyURL1: $data['Taxonomy_URL_1'],
      taxonomyURL2: $data['Taxonomy_URL_2'],
      taxonomyURL3: $data['Taxonomy_URL_3'],
      taxonomyURL4: $data['Taxonomy_URL_4'],
      taxonomyURL5: $data['Taxonomy_URL_5'],
      taxonomyURL6: $data['Taxonomy_URL_6'],
      toggleURL: $toggleURL ?? 'None',
      hasBenefitCategory: $data['hasBenefitCategory'] ? '1' : '',
      benefitCategories: $data['benefitCategories'] ?? '',
      pageLanguage: (($node->language()->getId() === 'en') ? 'USAGov English' : 'USAGov en Español'),
    );
  }

  /**
   * @param TaxonomyBreadcrumb $data
   */
  public static function datalayerForWizard(array $data, Term $term, string $baseURL): self {
    if ($heading = $term->get('field_heading')->getValue()) {
      $title = $heading[0]['value'];
    }
    else {
      $title = $term->getName();
    }

    $friendlyURL = $term->toUrl('canonical',
      options: ['language' => $term->language()]
    )->toString();
    $fullURL = $term->toUrl(
      options: ['absolute' => TRUE, 'language' => $term->language()]
    )->toString();

    $toggleURL = 'None';
    if (isset($term->field_language_toggle[0])) {
      $toggleTerm = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($term->field_language_toggle[0]->target_id);
      $url = Url::fromRoute(
        'entity.taxonomy_term.canonical',
        ['taxonomy_term' => $toggleTerm->id()],
        ['absolute' => TRUE, 'language' => $toggleTerm->language()]
      );
      $toggleURL = $url->toString();
    }
    return new self(
      hierarchy: self::getHierarchy($data),
      pageType: $data['Page_Type'],
      friendlyURL: $friendlyURL,
      pageID: 't_' . $data['taxonomyID'],
      pageTitle: $title,
      fullURL: $fullURL,
      taxonomyText1: $data['Taxonomy_Text_1'],
      taxonomyText2: $data['Taxonomy_Text_2'],
      taxonomyText3: $data['Taxonomy_Text_3'],
      taxonomyText4: $data['Taxonomy_Text_4'],
      taxonomyText5: $data['Taxonomy_Text_5'],
      taxonomyText6: $data['Taxonomy_Text_6'],
      taxonomyURL1: $data['Taxonomy_URL_1'],
      taxonomyURL2: $data['Taxonomy_URL_2'],
      taxonomyURL3: $data['Taxonomy_URL_3'],
      taxonomyURL4: $data['Taxonomy_URL_4'],
      taxonomyURL5: $data['Taxonomy_URL_5'],
      taxonomyURL6: $data['Taxonomy_URL_6'],
      toggleURL: $toggleURL,
      hasBenefitCategory: $data['hasBenefitCategory'] ? '1' : '',
      benefitCategories: $data['benefitCategories'] ?? '',
      pageLanguage: (($term->language()->getId() === 'en') ? 'USAGov English' : 'USAGov en Español'),
    );
  }

}
