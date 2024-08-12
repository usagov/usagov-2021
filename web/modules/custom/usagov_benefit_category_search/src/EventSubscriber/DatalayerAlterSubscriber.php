<?php

namespace Drupal\usagov_benefit_category_search\EventSubscriber;

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add benefit category info to datalayer.
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      DatalayerAlterEvent::EVENT_NAME => 'onDatalayerAlter',
    ];
  }

  /**
   * Adds category information to the datalayer.
   */
  public function onDatalayerAlter(DatalayerAlterEvent $event): void {
    $node = \Drupal::routeMatch()->getParameter('node');
    $event->datalayer['hasBenefitCategory'] = FALSE;

    if ($node instanceof Node && $node->getType() === 'basic_page') {
      $pageType = $node->field_page_type->referencedEntities()[0]->getName();
      if ($pageType === 'Standard Page') {
        // Provide list of categories for this page to GTM via taxonomy-data.
        if ($terms = $node->field_benefits_category->referencedEntities()) {
          $event->datalayer['hasBenefitCategory'] = TRUE;
          $benefitCategories = array_map(fn(Term $term) => $term->getName(), $terms);
          $event->datalayer['benefitCategories'] = implode('; ', $benefitCategories);
        }
      }
    }
  }

}
