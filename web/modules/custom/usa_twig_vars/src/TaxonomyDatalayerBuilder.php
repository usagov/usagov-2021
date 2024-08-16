<?php

namespace Drupal\usa_twig_vars;

use Drupal\node\Entity\Node;

/**
 * Builds a datalayer array we can serialize to JSON in our twig template.
 */
class TaxonomyDatalayerBuilder {

  private const HOME_TITLE_EN = "Home";
  private const HOME_URL_EN = "/";

  private const HOME_TITLE_ES = "Página principal";
  private const HOME_URL_ES = "/es/";

  private const ABOUT_GOVT_EN = "About the U.S. and its government";
  private const ABOUT_URL_EN = "/about-the-us";

  private const ABOUT_GOVT_ES = "Acerca de EE. UU. y su Gobierno";
  private const ABOUT_URL_ES = "/es/acerca-de-estados-unidos";

  /**
   * Language code for entity.
   *
   * @var 'en'|'es'
   */
  private string $langcode;

  public function __construct(
    private Node $node,
    private string $isFront,
    private string $contentType,
    private ?string $basicPagesubType,
    private string $pageType,
  ) {}

  /**
   * Builds the datalayer array.
   *
   * @return array
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function build(): array {
    $datalayer = [];
    $this->langcode = $this->node->language()->getId();

    // Basic elements for all pages.
    $datalayer['nodeID'] = $this->node->id();
    $datalayer['contentType'] = $this->contentType;
    $datalayer['language'] = $this->langcode;
    $datalayer['homepageTest'] = $this->isFront === 'homepage' ? 'homepage' : 'not_homepage';
    $datalayer['basicPagesubType'] = $this->basicPagesubType;
    $datalayer['Page_Type'] = $this->pageType;

    if ($this->isFront === 'homepage') {
      $taxonomy = $this->getHomepage();
    }
    elseif ($this->pageType === 'federal_directory_record') {
      $taxonomy = $this->getFederalAgency();
    }
    elseif ($this->pageType === 'state_directory_record') {
      $taxonomy = $this->getStateDirectory();
    }
    else {
      $taxonomy = $this->fromBreadcrumb();
    }

    ksort($taxonomy);
    return array_merge($datalayer, $taxonomy);
  }

  /**
   * Build Taxonomy entries based on menu breadcrumbs.
   *
   * @return array
   */
  public function fromBreadcrumb(): array {
    // For all other pages, we need the breadcrumb to pass as taxonomy.
    // This mimics the system breadcrumb block plugin, without rendering it.
    $breadcrumb = \Drupal::service('breadcrumb');
    $crumbs = $breadcrumb->build(\Drupal::routeMatch());
    $taxonomy = [];
    /**
     * @var \Drupal\Core\Link $crumb
     */
    foreach ($crumbs->getLinks() as $index => $crumb) {
      $suffix = $index + 1;

      if ($suffix === 1) {
        $taxonomy['Taxonomy_Text_' . $suffix] = match($this->langcode) {
          'en' => self::HOME_TITLE_EN,
          'es' => self::HOME_TITLE_ES,
        };
      }
      else {
        $taxonomy['Taxonomy_Text_' . $suffix] = htmlspecialchars($crumb->getText(), ENT_QUOTES, 'UTF-8');
      }

      $url = $crumb->getUrl()->toString() ?: $this->node->toUrl()->toString();

      if ($url === '/es') {
        $url = self::HOME_URL_ES;
      }
      $taxonomy['Taxonomy_URL_' . $suffix] = $url;
    }

    $count = count($crumbs->getLinks());
    if ($count < 6) {
      // Keeping behavior for pages that don't provide a menu link (1 item in
      // the breadcrumb). The Taxonomy Texts are all set to the homepage
      // but the URL paths after the first is the node's path.
      $lastText = $taxonomy['Taxonomy_Text_' . $count];
      $lastURL = $count === 1 ? $this->node->toUrl()->toString() : $taxonomy['Taxonomy_URL_' . $count];
      for ($i = $count + 1; $i < 7; $i++) {
        $taxonomy['Taxonomy_Text_' . $i] = $lastText;
        $taxonomy['Taxonomy_URL_' . $i] = $lastURL;
      }
    }

    return $taxonomy;
  }

  /**
   * Get Taxonomy Entriess for homepage.
   *
   * @return array
   */
  public function getHomepage(): array {
    // Taxonomy for the homepages. These depend on variables
    // that the block view doesn't readily have access to.
    for ($i = 1; $i < 7; $i++) {
      switch ($this->langcode) {
        case 'en':
          $taxonomy["Taxonomy_Text_" . $i] = self::HOME_TITLE_EN;
          $taxonomy["Taxonomy_URL_" . $i] = self::HOME_URL_EN;
          break;

        case 'es':
          $taxonomy["Taxonomy_Text_" . $i] = self::HOME_TITLE_ES;
          $taxonomy["Taxonomy_URL_" . $i] = self::HOME_URL_ES;
          break;
      }
    }

    return $taxonomy;
  }

  /**
   * Get Taxonomy info for a Federal Agency node.
   *
   * @return array
   */
  public function getFederalAgency(): array {
    switch ($this->langcode) {
      case 'en':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_EN;
        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_EN;
        $taxonomy["Taxonomy_Text_3"] = "A-Z index of U.S. government departments and agencies";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_EN;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_EN;
        $taxonomy["Taxonomy_URL_3"] = "/agency-index";
        break;

      case 'es':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_ES;
        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_ES;
        $taxonomy["Taxonomy_Text_3"] = "Agencias federales";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_ES;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_ES;
        $taxonomy["Taxonomy_URL_3"] = "/es/indice-agencias";
    }

    $agencyName = htmlspecialchars($this->node->getTitle(), ENT_QUOTES, 'UTF-8');
    $path = $this->node->toUrl()->toString();

    $taxonomy["Taxonomy_Text_4"] = $agencyName;
    $taxonomy["Taxonomy_Text_5"] = $agencyName;
    $taxonomy["Taxonomy_Text_6"] = $agencyName;

    $taxonomy["Taxonomy_URL_4"] = $path;
    $taxonomy["Taxonomy_URL_5"] = $path;
    $taxonomy["Taxonomy_URL_6"] = $path;

    return $taxonomy;
  }

  /**
   * Get Taxonomy info for a Sate Agency node.
   *
   * @return array
   */
  public function getStateDirectory(): array {
    switch ($this->langcode) {
      case 'en':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_EN;

        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_EN;
        $taxonomy["Taxonomy_Text_3"] = "State governments";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_EN;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_EN;
        $taxonomy["Taxonomy_URL_3"] = "/state-governments";
        break;

      case 'es':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_ES;
        // States have a different description in Spanish.
        $taxonomy["Taxonomy_Text_2"] = "Acerca de EE. UU. y directorios del Gobierno";
        $taxonomy["Taxonomy_Text_3"] = "Gobiernos estatales";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_ES;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_ES;
        $taxonomy["Taxonomy_URL_3"] = "/es/gobiernos-estatales";
    }

    $agencyName = htmlspecialchars($this->node->getTitle(), ENT_QUOTES, 'UTF-8');
    $path = $this->node->toUrl()->toString();

    $taxonomy["Taxonomy_Text_4"] = $agencyName;
    $taxonomy["Taxonomy_Text_5"] = $agencyName;
    $taxonomy["Taxonomy_Text_6"] = $agencyName;

    $taxonomy["Taxonomy_URL_4"] = $path;
    $taxonomy["Taxonomy_URL_5"] = $path;
    $taxonomy["Taxonomy_URL_6"] = $path;

    return $taxonomy;
  }

}
