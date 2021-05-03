<?php

namespace Drupal\message_notify\Plugin\Notifier;

use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\message\MessageInterface;

/**
 * Notifier plugin manager.
 */
class Manager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   *
   * Allow the message entity to be passed to the constructor.
   */
  public function createInstance($plugin_id, array $configuration = [], MessageInterface $message = NULL) {
    $plugin_definition = $this->getDefinition($plugin_id);
    $plugin_class = DefaultFactory::getPluginClass($plugin_id, $plugin_definition);
    // If the plugin provides a factory method, pass the container to it.
    if (is_subclass_of($plugin_class, ContainerFactoryPluginInterface::class)) {
      $plugin = $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition, $message);
    }
    else {
      $plugin = new $plugin_class($configuration, $plugin_id, $plugin_definition, $message);
    }
    return $plugin;
  }

}
