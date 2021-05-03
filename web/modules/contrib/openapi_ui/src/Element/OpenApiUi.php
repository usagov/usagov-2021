<?php

namespace Drupal\openapi_ui\Element;

use Drupal\Core\Url;
use Drupal\openapi_ui\Plugin\openapi_ui\OpenApiUiInterface;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Defines a render element for OpenApi Doc display librarys.
 *
 * This display element will act as a warpper for the ui element. The rendering
 * process will use the library's plugin in order to allow for the plugin to
 * control and extend the way the docs are being rendered.
 *
 * The api docs can be supplied to the render element in a few different ways to
 * the `#openapi_schema` property.
 * They can either be supplied as any of the following.
 * - An array: An array containing the docs of the module which conforms to the
 *   OpenAPI schema. The array will be converted to json and exported during
 *   rendering.
 * - A url object: The url or path to a complient schema document.
 * - A url string: If a string is given, it will be converted to a url object.
 * - A file object: A drupal file object may be passed here. Checks will be
 *   performed to ensure that the doc is accessible to the user. The private url
 *   of the doc will ultimately be used by the ui, effectivly having the file
 *   object converted into its private url.
 * - A callback function: The docs can be implemented via a callback. The
 *   callback will be passed the render element and as a result should take
 *   exactly one paramter, an array, and return either an array with openapi
 *   complient schema or a url for the schema.
 *
 * Doc library plugins may only support specific formats for the openapi docs.
 * They may also support additional properties on this render element which can
 * be used to control the display of the ui and allow for configuration of the
 * resulting element.
 *
 * Properties:
 * - #openapi_ui_plugin: A plugin instance, or the machine name of the desired
 *   plugin.
 * - #openapi_schema: The api specifications to use when building the docs ui.
 *   This property can have a few options. See the above for details.
 *
 * Usage example:
 * @code
 * $form['api_docs'] = [
 *   '#type' => 'openapi_ui',
 *   '#openapi_ui_plugin' => 'swagger',
 *   '#openapi_schema' => 'https://example.com/openapi',
 * ];
 * @endcode
 *
 * @RenderElement("openapi_ui")
 */
class OpenApiUi extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#pre_render' => [
        [
          $class,
          'preRenderOpenApiUi',
        ],
      ],
    ];
  }

  /**
   * Provides markup for associating a tray trigger with a tray element.
   *
   * A tray is a responsive container that wraps renderable content. Trays
   * present content well on small and large screens alike.
   *
   * @param array $element
   *   A renderable array.
   *
   * @return array
   *   A renderable array.
   */
  public static function preRenderOpenApiUi(array $element) {
    $messenger = \Drupal::service('messenger');
    $plugin = $element['#openapi_ui_plugin'];
    // If the plugin id was passed, get the plugin instance.
    if (is_string($plugin) && !empty($plugin)) {
      $ui_plugin_manager = \Drupal::service('plugin.manager.openapi_ui.ui');
      $plugin = $ui_plugin_manager->createInstance($plugin);
      $element['#openapi_ui_plugin'] = $plugin;
    }
    if (!($plugin instanceof OpenApiUiInterface)) {
      $messenger->addError(t('Unknown OpenAPI UI plugin being used.'));
      return $element;
    }

    $schema = $element['#openapi_schema'];
    // If a callback was passed, execute it to get a string, array, or url.
    if (is_callable($schema)) {
      $schema = call_user_func($schema, $element);
      $element['#openapi_schema'] = $schema;
    }
    // If the schema is a string, convert it to a URL object.
    if (is_string($schema)) {
      $schema = Url::fromUri($schema);
      $element['#openapi_schema'] = $schema;
    }
    // If schema is not a complient array or a URL, quit rendering.
    if (!(is_array($schema) || $schema instanceof Url)) {
      $messenger->addError(t('Invalid schema source provided.'));
      return $element;
    }

    $element['#tree'] = TRUE;
    $element['ui'] = $plugin->build($element);

    return $element;
  }

}
