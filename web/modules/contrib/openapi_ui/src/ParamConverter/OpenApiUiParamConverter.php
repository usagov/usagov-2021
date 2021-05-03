<?php

namespace Drupal\openapi_ui\ParamConverter;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a ParamConverter for openapi_ui Plugins.
 */
class OpenApiUiParamConverter implements ParamConverterInterface {

  /**
   * Current openapi generator plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  public $openApiUiManager;

  /**
   * Creates a new OpenApiUiParamConverter.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $openapi_ui_manager
   *   The current openapi ui plugin manager instance.
   */
  public function __construct(PluginManagerInterface $openapi_ui_manager) {
    $this->openApiUiManager = $openapi_ui_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    try {
      $plugin = $this->openApiUiManager->createInstance($value);
    }
    catch (PluginNotFoundException $e) {
      // Plugin Not found, we can't convert it the param.
      return NULL;
    }
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (!empty($definition['type']) && $definition['type'] == 'openapi_ui');
  }

}
