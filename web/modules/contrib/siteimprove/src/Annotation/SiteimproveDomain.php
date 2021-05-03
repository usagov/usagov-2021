<?php

namespace Drupal\siteimprove\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a Siteimprove domain item annotation object.
 *
 * @see \Drupal\siteimprove\Plugin\SiteimproveDomainManager
 * @see plugin_api
 *
 * @Annotation
 */
class SiteimproveDomain extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Description of what this plugin instance does.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
