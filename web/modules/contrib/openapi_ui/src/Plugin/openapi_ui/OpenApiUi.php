<?php

namespace Drupal\openapi_ui\Plugin\openapi_ui;

use Drupal\Component\Plugin\PluginBase;

/**
 * Defines the structure for an openapi_ui plugin.
 */
class OpenApiUi extends PluginBase implements OpenApiUiInterface {

  /**
   * {@inheritdoc}
   */
  public function build(array $render_element) {
    return [];
  }

}
