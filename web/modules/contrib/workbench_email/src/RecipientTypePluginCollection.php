<?php

namespace Drupal\workbench_email;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;

/**
 * A collection of recipient types.
 */
class RecipientTypePluginCollection extends DefaultLazyPluginCollection {

  /**
   * All possible recipient type plugin IDs.
   *
   * @var array
   */
  protected $definitions;

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\workbench_email\Plugin\RecipientTypeInterface
   *   The recipient type.
   */
  public function &get($instance_id) {
    return parent::get($instance_id);
  }

  /**
   * Retrieves plugin definitions and creates an instance for each.
   */
  public function getAll() {
    // Retrieve all available behavior plugin definitions.
    if (!$this->definitions) {
      $this->definitions = $this->manager->getDefinitions();
    }
    // Ensure that there is an instance of all available behavior plugins.
    foreach ($this->definitions as $plugin_id => $definition) {
      if (!isset($this->pluginInstances[$plugin_id])) {
        $this->initializePlugin($plugin_id);
      }
    }
    return $this->pluginInstances;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializePlugin($instance_id) {
    $configuration = $this->manager->getDefinition($instance_id);
    // Merge the actual configuration into the default configuration.
    if (isset($this->configurations[$instance_id])) {
      $configuration = NestedArray::mergeDeep($configuration, $this->configurations[$instance_id]);
    }
    $this->configurations[$instance_id] = $configuration;
    parent::initializePlugin($instance_id);
  }

  /**
   * {@inheritdoc}
   */
  public function sort() {
    $this->getAll();
    return parent::sort();
  }

}
