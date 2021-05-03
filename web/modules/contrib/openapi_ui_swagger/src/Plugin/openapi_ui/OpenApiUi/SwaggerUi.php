<?php

namespace Drupal\openapi_ui_swagger\Plugin\openapi_ui\OpenApiUi;

use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;

use Drupal\openapi_ui\Plugin\openapi_ui\OpenApiUi;

/**
 * Implements openapi_ui plugin for the swagger-ui library.
 *
 * @OpenApiUi(
 *   id = "swagger",
 *   label = @Translation("Swagger UI"),
 * )
 */
class SwaggerUi extends OpenApiUi {

  /**
   * {@inheritdoc}
   */
  public function build(array $render_element) {
    $schema = $render_element['#openapi_schema'];
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'swagger-ui',
        'class' => [
          'swagger-ui-wrap',
        ],
      ],
      '#attached' => [
        'library' => [
          'openapi_ui_swagger/swagger_ui_integration',
        ],
      ],
    ];
    if ($schema instanceof Url) {
      $build['#attributes']['data-openapi-ui-url'] = $schema->toString();
    }
    else {
      $build['#attributes']['data-openapi-ui-spec'] = Json::encode($schema);
    }
    return $build;
  }

}
