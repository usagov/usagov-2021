<?php

namespace Drupal\usagov_ssg_postprocessing\Data;

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

  public static function datalayerForNode(TaxonomyDatalayerBuilder $dl, Node $node, string $base_url): self {
    $data = $dl->build();

    $hierarchy = 1;

    $friendlyURL = $node->toUrl()->toString();
    $fullURL = $base_url . $node->toUrl()->toString();
    $title = $node->getTitle();

    $toggleAlias = NULL;
    if ($node->field_language_toggle[0]->target_id) {

      $toggleLang = match($node->language()->getId()) {
        'en' => 'es',
        'es' => 'en'
      };

      $toggleAlias = \Drupal::service('path_alias.manager')
        ->getAliasByPath('/node/' . $node->field_language_toggle[0]->target_id, $toggleLang);
      if ($data['homepageTest'] === 'homepage') {
        // TODO figure out linking for homepage
      }
    }
    $toggleURL = $toggleAlias;

//    $toggle_url = $xpath->query('/html/head/link[contains(@data-type, "altlang")]/@href')->item(0)->nodeValue;
//    $decoded["Toggle URL"] = ($toggle_url) ? $toggle_url : "None";


    return new self(
      hierarchy: $hierarchy,
      pageType: $data['Page_Type'],
      pageSubType: $data['basicPagesubType'],
      contentType: $data['contentType'],
      friendlyURL: $friendlyURL,
      pageID: $data['nodeID'],
      pageTitle: $title,
      fullURL: $fullURL,
      TaxonomyText1: $data['Taxonomy_Text_1'],
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
      toggleURL: $toggleURL ?? '',
    );
  }
}
