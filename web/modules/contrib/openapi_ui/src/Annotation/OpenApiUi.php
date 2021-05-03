<?php

namespace Drupal\openapi_ui\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an annotation object for OpenApiUi plugins.
 *
 * Plugin Namespace: Plugin\openapi_ui\Plugin\openapi_ui.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class OpenApiUi extends Plugin {

  /**
   * The plugin id.
   *
   * @var string
   */
  public $id;

  /**
   * The plugin label.
   *
   * @var string
   */
  public $label;

}
