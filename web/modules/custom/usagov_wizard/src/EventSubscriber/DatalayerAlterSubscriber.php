<?php

namespace Drupal\usagov_wizard\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\usa_twig_vars\Event\DatalayerAlterEvent;
use Drupal\usagov_wizard\WizardDataLayer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add taxonomy scan wizard info to datalayer.
 */
class DatalayerAlterSubscriber implements EventSubscriberInterface {

  public function __construct(
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

    $builder = new WizardDataLayer($term, $this->entityTypeManager);
    $event->datalayer = $builder->getData($event->datalayer);
  }

}
