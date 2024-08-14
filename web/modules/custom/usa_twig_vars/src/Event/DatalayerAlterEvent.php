<?php

namespace Drupal\usa_twig_vars\Event;

use Drupal\Component\EventDispatcher\Event;

class DatalayerAlterEvent extends Event {
  const EVENT_NAME = 'usa_twig_vars.datalayer_alter';

  public function __construct(
    public array $datalayer,
  ) {}

}
