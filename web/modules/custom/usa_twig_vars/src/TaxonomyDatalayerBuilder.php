<?php

namespace Drupal\usa_twig_vars;

use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Entity\EntityMalformedException;
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

  private const AGENCY_INDEX_URL_EN = '/agency-index';
  private const AGENCY_INDEX_URL_ES = '/es/indice-agencias';

  private const STATE_INDEX_URL_EN = '/state-governments';
  private const STATE_INDEX_URL_ES = '/es/gobiernos-estatales';

  /**
   * Language code for entity.
   *
   * @var 'en'|'es'
   */
  private string $langcode;

  /**
   * The content type identified to use.
   */
  private string $contentType;

  /**
   * String label to send to indicate homepage or not.
   */
  private string $isFront;

  public function __construct(
    private BreadcrumbManager $breadcrumbManager,
    public Node $node,
    bool $isFront,
    public ?string $basicPagesubType,
  ) {
    $this->isFront = $isFront ? 'homepage' : 'not_homepage';
  }

  /**
   * Builds the datalayer array.
   *
   * @return array
   *   Initial datalayer payload.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function build(): array {
    $datalayer = [];
    $this->langcode = $this->node->language()->getId();

    // Check for special cases since contentType isn't always the node type.
    if ($this->isFederalDirectoryIndex()) {
      // Changes "directory_record" to "federal_directory_record".
      $pageType = 'federal_directory_index';
      $taxonomy = $this->fromBreadcrumb();
      $this->contentType = $pageType;
    }
    elseif ($this->isFederalDirectoryRecord()) {
      $pageType = 'federal_directory_record';
      $taxonomy = $this->getFederalAgency();
      $this->contentType = 'federal_directory_record';
    }
    elseif ($this->isStateDirectoryIndex()) {
      $pageType = 'state_directory_index';
      $this->contentType = 'state_directory_index';
      $taxonomy = $this->fromBreadcrumb();
    }
    elseif ($this->isStateDirectoryRecord()) {
      $pageType = 'state_directory_record';
      $this->contentType = 'state_directory_record';
      $taxonomy = $this->getStateDirectory();
    }
    elseif ($this->basicPagesubType === 'Standard Page') {
      $pageType = 'Content Page';
      $taxonomy = $this->fromBreadcrumb();
      $this->contentType = $this->node->getType();
    }
    else {
      $this->contentType = $this->node->getType();
      $pageType = $this->basicPagesubType ?? $this->contentType;

      if ($this->isFront === 'homepage') {
        $taxonomy = $this->getHomepage();
      }
      else {
        $taxonomy = $this->fromBreadcrumb();
      }
    }

    // Basic elements for all pages.
    $datalayer['nodeID'] = $this->node->id();
    $datalayer['language'] = $this->langcode;
    $datalayer['homepageTest'] = $this->isFront;
    $datalayer['basicPagesubType'] = $this->basicPagesubType;
    $datalayer['contentType'] = $this->contentType;
    $datalayer['Page_Type'] = $pageType;

    ksort($taxonomy);
    return array_merge($datalayer, $taxonomy);
  }

  /**
   * Build Taxonomy entries based on menu breadcrumbs.
   *
   * @return array
   *   Breadcrumb info to send.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function fromBreadcrumb(): array {
    // For all other pages, we need the breadcrumb to pass as taxonomy.
    // This mimics the system breadcrumb block plugin, without rendering it.
    $crumbs = $this->breadcrumbManager->build(\Drupal::routeMatch());
    $taxonomy = [];

    foreach ($crumbs->getLinks() as $index => $crumb) {
      $suffix = $index + 1;

      if ($suffix === 1) {
        $taxonomy['Taxonomy_Text_' . $suffix] = match($this->langcode) {
          'en' => self::HOME_TITLE_EN,
          'es' => self::HOME_TITLE_ES,
        };
      }
      elseif ($suffix < 7) {
        $taxonomy['Taxonomy_Text_' . $suffix] = htmlspecialchars($crumb->getText(), ENT_QUOTES, 'UTF-8');
      }
      else {
        // Do not output more than 6 levels.
        break;
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
   * Get Taxonomy Entries for homepage.
   *
   * @return array
   *   Breadcrumb info to send.
   */
  public function getHomepage(): array {
    // Taxonomy for the homepages. These depend on variables
    // that the block view doesn't readily have access to.
    $taxonomy = [];
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
   *   Breadcrumb info to send.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getFederalAgency(): array {
    switch ($this->langcode) {
      case 'en':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_EN;
        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_EN;
        $taxonomy["Taxonomy_Text_3"] = "A-Z index of U.S. government departments and agencies";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_EN;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_EN;
        $taxonomy["Taxonomy_URL_3"] = self::AGENCY_INDEX_URL_EN;
        break;

      case 'es':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_ES;
        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_ES;
        $taxonomy["Taxonomy_Text_3"] = "Agencias federales";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_ES;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_ES;
        $taxonomy["Taxonomy_URL_3"] = self::AGENCY_INDEX_URL_ES;
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
   *   Breadcrumb info to send.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getStateDirectory(): array {
    switch ($this->langcode) {
      case 'en':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_EN;

        $taxonomy["Taxonomy_Text_2"] = self::ABOUT_GOVT_EN;
        $taxonomy["Taxonomy_Text_3"] = "State governments";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_EN;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_EN;
        $taxonomy["Taxonomy_URL_3"] = self::STATE_INDEX_URL_EN;
        break;

      case 'es':
        $taxonomy["Taxonomy_Text_1"] = self::HOME_TITLE_ES;
        // States have a different description in Spanish than agencies.
        $taxonomy["Taxonomy_Text_2"] = "Acerca de EE. UU. y directorios del Gobierno";
        $taxonomy["Taxonomy_Text_3"] = "Gobiernos estatales";

        $taxonomy["Taxonomy_URL_1"] = self::HOME_URL_ES;
        $taxonomy["Taxonomy_URL_2"] = self::ABOUT_URL_ES;
        $taxonomy["Taxonomy_URL_3"] = self::STATE_INDEX_URL_ES;
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
   * Tests if a node is a federal agency record page.
   */
  private function isFederalDirectoryRecord(): bool {
    return $this->node->getType() === 'directory_record';
  }

  /**
   * Tests if a node is a federal directory index page.
   */
  private function isFederalDirectoryIndex(): bool {
    // Check for special nodes by path.
    // These paths are standard pages but should be coded differently.
    try {
      switch ($this->node->toUrl()->toString()) {
        case self::AGENCY_INDEX_URL_EN:
        case self::AGENCY_INDEX_URL_ES:
          return TRUE;
      }
    }
    catch (EntityMalformedException) {
      // Should we report that we couldn't get a URL for a node?
    }

    return FALSE;
  }

  /**
   * Tests if a node is a state directory index page.
   */
  private function isStateDirectoryIndex(): bool {
    // Check for special nodes by path.
    // These paths are also standard pages but should be coded differently.
    try {
      switch ($this->node->toUrl()->toString()) {
        case self::STATE_INDEX_URL_EN:
        case self::STATE_INDEX_URL_ES:
          return TRUE;
      }
    }
    catch (EntityMalformedException) {
      // Should we report that we couldn't get a URL for a node?
    }

    return $this->node->getType() === 'state_directory_index';
  }

  /**
   * Tests if a node is a state directory record.
   */
  private function isStateDirectoryRecord(): bool {
    return $this->node->getType() === 'state_directory_record';
  }

}
