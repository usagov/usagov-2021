<?php

namespace Drupal\usagov_wizard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;

/**
 * @phpstan-import-type TaxonomyBreadcrumb from TaxonomyDatalayerBuilder
 */
class WizardDataLayer {

  public function __construct(
    private Term $term,
    private EntityTypeManagerInterface $typeManager,
  ) {
  }

  /**
   * @param array{}|TaxonomyBreadcrumb $data
   * @return TaxonomyBreadcrumb
   */
  public function getData(array $data): array {
    $termStorage = $this->typeManager->getStorage('taxonomy_term');

    $isStartPage = FALSE;
    $children = $termStorage->loadChildren($this->term->id());
    $isResult = empty($children);

    if ($this->term->hasField('parent')) {
      $parentTID = $this->term->parent->getValue()[0]['target_id'];
      if ($parentTID === '0') {
        $isStartPage = TRUE;
      }
    }

    if ($isStartPage) {
      $page_type = 'wizard-start';
    }
    elseif ($isResult) {
      $page_type = 'wizard-result';
    }
    else {
      $page_type = 'wizard-question';
    }

    // make any changes need to $event->datalayer array
    $data['taxonomyID'] = $this->term->id();
    $data['contentType'] = $this->term->bundle();
    $data['language'] = $this->term->language()->getId();
    $data['homepageTest'] = 'not_homepage';
    $data['basicPagesubType'] = NULL;
    $data['Page_Type'] = $page_type;
    $data['hasBenefitCategory'] = FALSE;

    $rootTerm = NULL;
    $parents = [];
    $raw = [];

    if (
      $this->term->hasField('parent')
      && !$this->term->get('parent')->isEmpty()
    ) {
      $parents = $this->typeManager
        ->getStorage('taxonomy_term')
        ->loadAllParents($this->term->id());
      // Sort parents so "oldest ancestor" is first.
      $parents = array_reverse($parents);
      $rootTerm = $parents[array_key_first($parents)];
    }

    if ($rootTerm) {
      $crumbs = usagov_wizard_get_term_breadcrumb($rootTerm);
      // Here the first two items will give us the home page
      // and the main scam page
      $crumbs = array_slice($crumbs, 0, 2);
      foreach ($crumbs as $crumb) {
        $raw[$crumb['url']] = $crumb['text'];
      }
    }

    // the rest comes from the parents of this term
    foreach ($parents as $parentTerm) {
      $path = $parentTerm->get('path');
      $termURL = $path->alias;
      // pathalias field items don't prepend the language code for Spanish terms
      if ($parentTerm->language()->getId() === 'es') {
        $termURL = '/es' . $termURL;
      }
      $raw[$termURL] = $parentTerm->getName();
    }

    $count = count($raw);
    $urls = [];
    $i = 0;
    foreach ($raw as $url => $text) {
      $i++;
      $urls['Taxonomy_Text_' . $i] = $text;
      $urls['Taxonomy_URL_' . $i] = $url;

      if ($i === 6) {
        break;
      }
    }

    if ($i < 6) {
      $lastURL = array_key_last($raw);
      $lastText = $raw[$lastURL];

      for ($i = $count; $i < 6; $i++) {
        $urls['Taxonomy_Text_' . ($i + 1)] = $lastText;
        $urls['Taxonomy_URL_' . ($i + 1)] = $lastURL;
      }
    }

    ksort($urls);
    return array_merge($data, $urls);
  }

}
