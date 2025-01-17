<?php

namespace Drupal\usa_twig_vars\Event;

use Drupal\Component\EventDispatcher\Event;
// phpcs:ignore
use Drupal\usa_twig_vars\TaxonomyDatalayerBuilder;


/**
 * @phpstan-import-type TaxonomyBreadcrumb from TaxonomyDatalayerBuilder
 */
class DatalayerAlterEvent extends Event {
  const EVENT_NAME = 'usa_twig_vars.datalayer_alter';

  /**
   * @param TaxonomyBreadcrumb $datalayer
   */
  public function __construct(
    public array $datalayer,
  ) {}

}
