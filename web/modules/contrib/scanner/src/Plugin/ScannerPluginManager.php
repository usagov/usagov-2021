<?php

namespace Drupal\scanner\Plugin;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a Scanner plugin manager.
 *
 * @see \Drupal\scanner\Annotation\Scanner
 * @see \Drupal\scanner\Plugin\ScannerPluginInterface
 * @see plugin_api
 */
class ScannerPluginManager extends DefaultPluginManager {

  /**
   * Default values for each tax type plugin.
   *
   * @var array
   */
  protected $defaults = [
    'id' => '',
    'type' => '',
  ];

  /**
   * Constructs a ScannerPluginManager object.
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
    parent::__construct('Plugin/Scanner', $namespaces, $module_handler, 'Drupal\scanner\Plugin\ScannerPluginInterface', 'Drupal\scanner\Annotation\Scanner');
    $this->alterInfo('scanner_info');
    $this->setCacheBackend($cache_backend, 'scanner');
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    parent::processDefinition($definition, $plugin_id);

    foreach (['id', 'type'] as $required_property) {
      if (empty($definition[$required_property])) {
        throw new InvalidPluginDefinitionException(
          $plugin_id,
          sprintf('The scanner %s must define the %s property.', $plugin_id, $required_property)
        );
      }
    }
  }

}
