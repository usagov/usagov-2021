<?php

namespace Drupal\workbench_email\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Recipient type annotation object.
 *
 * Plugin Namespace: Plugin\workbench_email\RecipientType.
 *
 * @see \Drupal\workbench_email\RecipientTypePluginManager
 * @see \Drupal\workbench_email\Plugin\RecipientTypeInterface
 * @see \Drupal\workbench_email\Plugin\RecipientTypeBase
 * @see plugin_api
 *
 * @Annotation
 */
class RecipientType extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The name of the provider that owns the recipient type.
   *
   * @var string
   */
  public $provider;

  /**
   * The human-readable name of the recipient type.
   *
   * This is used as an administrative summary of what the recipient type does.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * Additional administrative information about the recipient type's behavior.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * Whether this recipient type is enabled or disabled by default.
   *
   * @var bool
   */
  public $status = FALSE;

  /**
   * The default settings for the recipient type.
   *
   * @var array
   */
  public $settings = [];

}
