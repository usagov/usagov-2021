<?php

namespace Drupal\openapi_ui\Plugin\openapi_ui;

/**
 * Defines the structure for an openapi_ui plugin.
 */
interface OpenApiUiInterface {

  /**
   * Render the tree for an openapi doc library.
   *
   * The callback is trigged to allow the plugin to define the plugin or library
   * specific rendering which needs to happen in order to construct the element.
   *
   * @param array $render_element
   *   The render array structure for the element which is triggering the build
   *   of this plugin's rendering.
   *
   * @return array
   *   The processed render array.
   */
  public function build(array $render_element);

}
