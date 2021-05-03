<?php

namespace Drupal\field_permissions\Plugin\FieldPermissionType;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An abstract implementation of FieldPermissionTypeInterface.
 */
abstract class Base extends PluginBase implements FieldPermissionTypeInterface, ContainerFactoryPluginInterface {

  /**
   * The field storage.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * Constructs the plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\field\FieldStorageConfigInterface $field_storage
   *   The field storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FieldStorageConfigInterface $field_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldStorage = $field_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, FieldStorageConfigInterface $field_storage = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $field_storage
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * Determines if the given account may view the field, regardless of entity.
   *
   * This should only return TRUE if
   * @code
   * $this->hasFieldAccess('view', $entity, $account);
   * @endcode
   * returns TRUE for all possible $entity values.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to check access for.
   *
   * @return bool
   *   The access result.
   *
   * @todo Move this to an interface: either FieldPermissionTypeInterface or a
   *   new one.
   */
  public function hasFieldViewAccessForEveryEntity(AccountInterface $account) {
    return FALSE;
  }
}
