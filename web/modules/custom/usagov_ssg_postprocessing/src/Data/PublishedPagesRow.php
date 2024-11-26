<?php

namespace Drupal\usagov_ssg_postprocessing\Data;

use Drupal\Core\Language\Language;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;

/**
 * Data structure describing the columns of the Published Pages CSV
 */
final class PublishedPagesRow {
  public function __construct(
    // depths in menu tree
    public readonly int $hierarchy,
    public readonly string $pageType,
    // for basic pages, the type of basic page selected
    public readonly ?string $pageSubType,
    // node bundle
    public readonly string $contentType,
    // path alias
    public readonly string $friendlyURL,
    // node ID
    public readonly string $pageID,
    public readonly string $pageTitle,
    // complete URL including domain name
    public readonly string $fullURL,
    public readonly string $TaxonomyText1,
    public readonly string $TaxonomyText2,
    public readonly string $TaxonomyText3,
    public readonly string $TaxonomyText4,
    public readonly string $TaxonomyText5,
    public readonly string $TaxonomyText6,
    public readonly string $TaxonomyURL1,
    public readonly string $TaxonomyURL2,
    public readonly string $TaxonomyURL3,
    public readonly string $TaxonomyURL4,
    public readonly string $TaxonomyURL5,
    public readonly string $TaxonomyURL6,
    public readonly string $isHomePage,
    // language toggle
    public readonly string $toggleURL,
    public readonly string $hasBenefitCategory,
    public readonly string $benefitCategories,
  ) {}

  private static function getTaxLevel1(Language $language): string {
    return match ($language->getId()) {
      'es' => "USAGov Español",
      'en' => "USAGov English",
      default => "USAGov English",
    };
  }

  private static function getHierarchy(array $data): int {
    $texts = array_filter($data, fn($key) => str_starts_with($key, 'Taxonomy_URL_'), ARRAY_FILTER_USE_KEY);
    return count(array_unique($texts));
  }

  public function toArray(): array {
    $array = [
      $this->hierarchy,
      $this->pageType,
      $this->pageSubType ?? '',
      $this->contentType,
      $this->friendlyURL,
      $this->pageID,
      $this->pageTitle,
      $this->fullURL,
      $this->TaxonomyText1,
      $this->TaxonomyText2,
      $this->TaxonomyText3,
      $this->TaxonomyText4,
      $this->TaxonomyText5,
      $this->TaxonomyText6,
      $this->TaxonomyURL1,
      $this->TaxonomyURL2,
      $this->TaxonomyURL3,
      $this->TaxonomyURL4,
      $this->TaxonomyURL5,
      $this->TaxonomyURL6,
      $this->isHomePage,
      $this->toggleURL,
      $this->hasBenefitCategory,
    ];

    // Keeps existing behavior of only including these columns if they have something
    if ($this->hasBenefitCategory !== "") {
      $array[] = $this->benefitCategories;
    }
    return $array;
  }

  public static function datalayerForNode(array $data, Node $node, string $baseURL): self {
    $title = $node->getTitle();

    if ($data['homepageTest'] === 'homepage' && $data['language'] === 'en') {
      $friendlyURL = TaxonomyDatalayerBuilder::HOME_URL_EN;
      $fullURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_EN;
    }
    else if ($data['homepageTest'] === 'homepage' && $data['language'] === 'es') {
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
    if ($node->field_language_toggle[0]->target_id) {
      if ($data['homepageTest'] === 'homepage' && $data['language'] === 'en') {
        $toggleURL = $baseURL . TaxonomyDatalayerBuilder::HOME_URL_ES;
      }
      else if ($data['homepageTest'] === 'homepage' && $data['language'] === 'es') {
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
      pageSubType: $data['basicPagesubType'],
      contentType: $data['contentType'],
      friendlyURL: $friendlyURL,
      pageID: $data['nodeID'],
      pageTitle: $title,
      fullURL: $fullURL,
      TaxonomyText1: self::getTaxLevel1($node->language()),
      TaxonomyText2: $data['Taxonomy_Text_2'],
      TaxonomyText3: $data['Taxonomy_Text_3'],
      TaxonomyText4: $data['Taxonomy_Text_4'],
      TaxonomyText5: $data['Taxonomy_Text_5'],
      TaxonomyText6: $data['Taxonomy_Text_6'],
      TaxonomyURL1: $data['Taxonomy_URL_1'],
      TaxonomyURL2: $data['Taxonomy_URL_2'],
      TaxonomyURL3: $data['Taxonomy_URL_3'],
      TaxonomyURL4: $data['Taxonomy_URL_4'],
      TaxonomyURL5: $data['Taxonomy_URL_5'],
      TaxonomyURL6: $data['Taxonomy_URL_6'],
      isHomePage: $data['homepageTest'],
      toggleURL: $toggleURL ?? 'None',
      hasBenefitCategory: $data['hasBenefitCategory'] ? '1' : '',
      benefitCategories: $data['benefitCategories'] ?: '',
    );
  }

  public static function datalayerForWizard(array $data, Term $term, string $baseURL): self {
    $title  = $term->getName();

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
      pageSubType: $data['basicPagesubType'],
      contentType: $data['contentType'],
      friendlyURL: $friendlyURL,
      pageID: 't_' . $data['taxonomyID'],
      pageTitle: $title,
      fullURL: $fullURL,
      TaxonomyText1: self::getTaxLevel1($term->language()),
      TaxonomyText2: $data['Taxonomy_Text_2'],
      TaxonomyText3: $data['Taxonomy_Text_3'],
      TaxonomyText4: $data['Taxonomy_Text_4'],
      TaxonomyText5: $data['Taxonomy_Text_5'],
      TaxonomyText6: $data['Taxonomy_Text_6'],
      TaxonomyURL1: $data['Taxonomy_URL_1'],
      TaxonomyURL2: $data['Taxonomy_URL_2'],
      TaxonomyURL3: $data['Taxonomy_URL_3'],
      TaxonomyURL4: $data['Taxonomy_URL_4'],
      TaxonomyURL5: $data['Taxonomy_URL_5'],
      TaxonomyURL6: $data['Taxonomy_URL_6'],
      isHomePage: $data['homepageTest'],
      toggleURL: $toggleURL,
      hasBenefitCategory: $data['hasBenefitCategory'] ? '1' : '',
      benefitCategories: $data['benefitCategories'] ?: '',
    );
  }
}
