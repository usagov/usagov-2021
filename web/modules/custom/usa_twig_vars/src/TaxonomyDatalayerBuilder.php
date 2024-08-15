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

  public function __construct(
    private Node $node,
    private string $isFront,
    private string $contentType,
    private ?string $basicPagesubType,
    private string $pageType,
  ) {}

  /**
   * @return array
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function build(): array {
    $datalayer = [];
    $langcode = $this->node->language()->getId();

    // Basic elements for all pages
    $datalayer['nodeID'] = $this->node->id();
    $datalayer['contentType'] = $this->contentType;
    $datalayer['language'] = $langcode;
    $datalayer['homepageTest'] = $this->isFront === 'homepage' ? 'homepage' : 'not_homepage';
    $datalayer['basicPagesubType'] = $this->basicPagesubType;
    $datalayer['Page_Type'] = $this->pageType;

    $taxonomy = [];
    if ($this->isFront === 'homepage') {
      $taxonomy = $this->getHomepage($langcode);
    }
    elseif ($this->pageType === 'federal_directory_record') {
      $taxonomy = $this->getFederalAgency($langcode);
    }
    elseif ($this->pageType === 'state_directory_record') {
      $taxonomy = $this->getStateDirectory($langcode);
    }
    else {
      $taxonomy = $this->fromBreadcrumb($langcode);
    }

    ksort($taxonomy);
    return array_merge($datalayer, $taxonomy);
  }

  /**
   * @param 'en'|'es' $langcode
   * @return array
   */
  public function fromBreadcrumb(string $langcode): array {
    // For all other pages, we need the breadcrumb to pass as taxonomy.
    // This mimics the system breadcrumb block plugin, without rendering it.
    $breadcrumb = \Drupal::service('breadcrumb');
    $crumbs = $breadcrumb->build(\Drupal::routeMatch());

    /**
     * @var \Drupal\Core\Link $crumb
     */
    foreach ($crumbs->getLinks() as $index => $crumb) {
      $suffix = $index + 1;

      if ($suffix === 1) {
        $taxonomy['Taxonomy_Text_' . $suffix] = match($langcode) {
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
    // if a node doesn't provide a menu link, the breadcrumb will be just the homepage
    if ($count === 1) {
      for ($i = $count + 1; $i < 7; $i++) {
        $taxonomy['Taxonomy_Text_' . $i] = $this->node->getTitle();
        $taxonomy['Taxonomy_URL_' . $i] = $this->node->toUrl()->toString();
      }
    }
    elseif ($count < 6) {
      $lastText = $taxonomy['Taxonomy_Text_' . $count];
      $lastURL = $taxonomy['Taxonomy_URL_' . $count];
      for ($i = $count + 1; $i < 7; $i++) {
        $taxonomy['Taxonomy_Text_' . $i] = $lastText;
        $taxonomy['Taxonomy_URL_' . $i] = $lastURL;
      }
    }

    return $taxonomy;
  }

  /**
   * @param 'en'|'es' $langcode
   * @return array
   */
  public function getHomepage(string $langcode): array {
    // Taxonomy for the homepages. These depend on variables
    // that the block view doesn't readily have access to.
    for ($i = 1; $i < 7; $i++) {
      $taxonomy["Taxonomy_Text_" . $i] = ($langcode === 'en' ? "Home" : "Página principal");
      $taxonomy["Taxonomy_URL_" . $i] = ($langcode === 'en' ? self::HOME_URL_EN : "/es/");
    }
    return $taxonomy;
  }

  /**
   * @param 'en'|'es' $langcode
   * @return array
   */
  public function getFederalAgency(string $langcode): array {
    switch ($langcode) {
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
   * @param 'en'|'es' $langcode
   * @return array
   */
  public function getStateDirectory(string $langcode): array {
    switch ($langcode) {
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
