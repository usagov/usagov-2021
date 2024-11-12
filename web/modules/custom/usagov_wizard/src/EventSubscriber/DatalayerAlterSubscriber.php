<?php

namespace Drupal\usagov_wizard\EventSubscriber;

use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Drupal\usagov_wizard\MenuChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add taxonomy scan wizard info to datalayer.
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  public function __construct(
    private MenuChecker $menuChecker,
    private BreadcrumbManager $breadcrumbManager,
    private CurrentRouteMatch $currentRouteMatch,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      DatalayerAlterEvent::EVENT_NAME => 'onDatalayerAlter',
    ];
  }

  /**
   * Adds wizard taxonomy information to the datalayer.
   */
  public function onDatalayerAlter(DatalayerAlterEvent $event): void {
    $term = $this->currentRouteMatch->getParameter('taxonomy_term');
    if (!$term || $term->bundle() !== 'wizard') {
      return;
    }

    $termStorage = $this->entityTypeManager->getStorage('taxonomy_term');

    $isStartPage = FALSE;
    $children = $termStorage->loadChildren($term->id());
    $isResult = empty($children);

    if ($term->hasField('parent')) {
      $parentTID = $term->parent->getValue()[0]['target_id'];
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

    // keep the same order
    unset($event->datalayer['hasBenefitCategory']);
    // make any changes need to $event->datalayer array
    $event->datalayer['taxonomyID'] = $term->id();
    $event->datalayer['contentType'] = $term->bundle();
    $event->datalayer['language'] = $term->language()->getId();
    $event->datalayer['homepageTest'] = 'not_homepage';
    $event->datalayer['basicPagesubType'] = NULL;
    $event->datalayer['Page_Type'] = $page_type;
    $event->datalayer['hasBenefitCategory'] = FALSE;

    $rootTerm = NULL;
    $parents = [];
    if ($term->hasField('parent') && !$term->get('parent')->isEmpty()) {
      $parents = $this->entityTypeManager
        ->getStorage('taxonomy_term')
        ->loadAllParents($term->id());
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
        $data[$crumb['url']] = $crumb['text'];
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
      $data[$termURL] = $parentTerm->getName();
    }

    $count = count($data);

    $i = 0;
    foreach ($data as $url => $text) {
      $i++;
      $urls['Taxonomy_Text_' . $i] = $text;
      $urls['Taxonomy_URL_' . $i] = $url;

      if ($i === 6) {
        break;
      }
    }

    if ($i < 6) {
      $lastURL = array_key_last($data);
      $lastText = $data[$lastURL];

      for ($i = $count; $i < 6; $i++) {
        $urls['Taxonomy_Text_' . ($i + 1)] = $lastText;
        $urls['Taxonomy_URL_' . ($i + 1)] = $lastURL;
      }
    }

    ksort($urls);
    $event->datalayer = array_merge($event->datalayer, $urls);
  }

}
