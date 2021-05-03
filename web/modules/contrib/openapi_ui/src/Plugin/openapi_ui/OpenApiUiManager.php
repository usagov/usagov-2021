<?php

namespace Drupal\openapi_ui\Plugin\openapi_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Defines the structure for an openapi_ui plugin manager.
 *
 * @see \Drupal\openapi_ui\Plugin\OpenApiUiBase.
 */
class OpenApiUiManager extends DefaultPluginManager {

  /**
   * Constructs a GeneratorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/openapi_ui/OpenApiUi',
      $namespaces,
      $module_handler,
      'Drupal\openapi_ui\Plugin\openapi_ui\OpenApiUiInterface',
      'Drupal\openapi_ui\Annotation\OpenApiUi'
    );
    $this->alterInfo('openapi_ui');
    $this->setCacheBackend($cache_backend, 'openapi_ui_plugins');
  }

}
