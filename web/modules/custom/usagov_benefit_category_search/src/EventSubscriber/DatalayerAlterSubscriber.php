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

    // This listener is only interested in nodes, which have numeric IDs.
    // Taxonomy terms IDs are prefixed with "t_"
    if (!isset($event->datalayer['nodeID']) || !is_numeric($event->datalayer['nodeID'])) {
      return;
    }

    $node = Node::load($event->datalayer['nodeID']);
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
