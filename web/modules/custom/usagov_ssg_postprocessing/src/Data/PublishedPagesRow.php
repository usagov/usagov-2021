<?php

namespace Drupal\usagov_ssg_postprocessing\Data;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
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
    public readonly bool $isHomePage,
    // language toggle
    public readonly string $toggleURL,
  ) {}

  public function toArray(): array {
    return [
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
      $this->isHomePage ? 'homepage' : 'not_homepage',
      $this->toggleURL,
    ];
  }

  public static function datalayerForNode(TaxonomyDatalayerBuilder $dl, Node $node, string $baseURL): self {
    $data = $dl->build();

    if ($data['homepageTest'] === 'homepage') {
      $friendlyURL = '/';
      $fullURL = $baseURL . '/';
    } else {
      $friendlyURL = $node->toUrl('canonical',
        options: ['language' => $node->language()]
      )->toString();
      $fullURL = $node->toUrl(
        options: ['absolute' => TRUE, 'language' => $node->language()]
      )->toString();
    }
    $title = $node->getTitle();

    $toggleURL = NULL;
    if ($node->field_language_toggle[0]->target_id) {
      $toggleNode = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->load($node->field_language_toggle[0]->target_id);
      $url = Url::fromRoute(
        'entity.node.canonical',
        ['node' => $toggleNode->id()],
        ['absolute' => TRUE, 'language' => $toggleNode->language()]
      );
      $toggleURL = $url->toString();


      if ($data['homepageTest'] === 'homepage') {
        // TODO figure out linking for homepage
      }
    }

    $taxLevel1 = match($node->language()->getId()) {
      'es' => TaxonomyDatalayerBuilder::HOME_TITLE_ES,
      'en' => TaxonomyDatalayerBuilder::HOME_URL_EN,
      default => TaxonomyDatalayerBuilder::HOME_URL_EN,
    };
//    $toggle_url = $xpath->query('/html/head/link[contains(@data-type, "altlang")]/@href')->item(0)->nodeValue;
//    $decoded["Toggle URL"] = ($toggle_url) ? $toggle_url : "None";

    $texts = array_filter($data, fn($key) => str_starts_with($key, 'Taxonomy_Text_'), ARRAY_FILTER_USE_KEY);
    $hierarchy = count(array_unique($texts));

    return new self(
      hierarchy: $hierarchy,
      pageType: $data['Page_Type'],
      pageSubType: $data['basicPagesubType'],
      contentType: $data['contentType'],
      friendlyURL: $friendlyURL,
      pageID: $data['nodeID'],
      pageTitle: $title,
      fullURL: $fullURL,
      TaxonomyText1: $taxLevel1,
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
    );
  }
}
