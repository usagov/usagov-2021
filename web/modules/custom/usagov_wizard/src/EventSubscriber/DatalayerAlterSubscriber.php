<?php

namespace Drupal\usagov_wizard\EventSubscriber;

use Drupal\Core\Breadcrumb\BreadcrumbManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add taxonomy scan wizard info to datalayer.
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  public function __construct(
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

    $isStartPage =  FALSE;

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
    } elseif ($isResult) {
      $page_type = 'wizard-result';
    } else {
      $page_type = 'wizard-question';
    }

    // make any changes need to $event->datalayer array
    $event->datalayer['taxonomyID'] = $term->id();
    $event->datalayer['contentType'] = $term->bundle();
    $event->datalayer['language'] = $term->language()->getId();
    $event->datalayer['homepageTest'] = 'not_homepage';
    $event->datalayer['basicPagesubType'] = null;
    $event->datalayer['Page_Type'] = $page_type;
    $event->datalayer['hasBenefitCategory'] = FALSE;

    $crumbs = $this->breadcrumbManager->build($this->currentRouteMatch);
    $links = $crumbs->getLinks();

    $data = [];
    foreach ($links as $i => $link) {
      $data[$link->getUrl()->toString()] = $link->getText();
    }

    // add the parents
    $vocabParents = $termStorage->loadParents($term->id());
    foreach ($vocabParents as $parent) {
      $url = $parent->get('path')->alias;
      $data[$url] = $parent->getName();
    }

    $termURL = $term->get('path')->alias;
    $data[$termURL] = $term->getName();

    $count  = count($data);

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

  private function getParents(Term $node) {

  }

}
