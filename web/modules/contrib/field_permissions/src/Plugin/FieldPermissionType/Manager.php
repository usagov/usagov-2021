<?php

namespace Drupal\field_permissions\Plugin\FieldPermissionType;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\Annotation\FieldPermissionType;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;

/**
 * Field permission type plugin manager.
 */
class Manager extends DefaultPluginManager {

  /**
   * Constructs the field permission type plugin manager.
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
    parent::__construct('Plugin/FieldPermissionType', $namespaces, $module_handler, FieldPermissionTypeInterface::class, FieldPermissionType::class);
    $this->setCacheBackend($cache_backend, 'field_permission_type_plugins');
    $this->alterInfo('field_permission_type_plugin');
  }

  /**
   * Allow the field storage to be passed into the plugin.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $configuration
   *   The plugin configuration.
   * @param \Drupal\field\FieldStorageConfigInterface $field_storage
   *   The field storage.
   *
   * @return \Drupal\field_permissions\Plugin\FieldPermissionTypeInterface
   *   The field permission type plugin instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance($plugin_id, array $configuration = [], FieldStorageConfigInterface $field_storage = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, ContainerFactoryPluginInterface::class)) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $field_storage);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $field_storage);
    }
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    $definitions = parent::getDefinitions();

    // Order by weight.
    uasort($definitions, function ($a, $b) {
      if ($a['weight'] == $b['weight']) {
        return 0;
      }
      return $a['weight'] < $b['weight'] ? -1 : 1;
    });

    return $definitions;
  }

}
