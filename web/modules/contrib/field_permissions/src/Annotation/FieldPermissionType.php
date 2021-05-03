<?php

namespace Drupal\field_permissions\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a field permission type plugin.
 *
 * @Annotation
 */
class FieldPermissionType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable title.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The permission type description.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The weight for ordering the plugins on the field settings page.
   *
   * @var int
   */
  public $weight;

}
