<?php

namespace Drupal\scanner\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a reusable form plugin annotation object.
 *
 * @Annotation
 */
class Scanner extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The entity type (ex: node, user, paragraph, etc).
   *
   * @var string
   */
  public $type;

}
