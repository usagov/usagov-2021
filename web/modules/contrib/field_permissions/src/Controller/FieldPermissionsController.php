<?php

namespace Drupal\field_permissions\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\FieldPermissionsServiceInterface;
use Drupal\field_permissions\Plugin\FieldPermissionType\Manager;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\field_permissions\Plugin\CustomPermissionsInterface;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Define FieldPermissionsController.
 */
class FieldPermissionsController extends ControllerBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The field permission service.
   *
   * @var \Drupal\field_permissions\FieldPermissionsServiceInterface
   */
  protected $fieldPermissions;

  /**
   * The field permission type plugin manager.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\Manager
   */
  protected $permissionTypeManager;

  /**
   * Construct the field permission controller.
   *
   * @param \Drupal\field_permissions\FieldPermissionsServiceInterface $field_permissions_service
   *   Field permissions services.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\field_permissions\Plugin\FieldPermissionType\Manager $permission_type_manager
   *   The permission type plugin manager.
   */
  public function __construct(FieldPermissionsServiceInterface $field_permissions_service, EntityTypeManagerInterface $entity_type_manager, Manager $permission_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldPermissions = $field_permissions_service;
    $this->permissionTypeManager = $permission_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * Uses late static binding to create an instance of this class with
   * injected dependencies.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('field_permissions.permissions_service'),
      $container->get('entity_type.manager'),
      $container->get('plugin.field_permissions.types.manager')
    );
  }

  /**
   * Content to page report all field permissions settings.
   *
   * Build table to Path: 'admin/reports/fields/permissions'.
   */
  public function content() {
    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->getTitle(),
      '#rows' => $this->buildRows(),
    ];
    $build['#attached']['library'] = 'field_permissions/field_permissions';
    return $build;
  }

  /**
   * Build the table header for the report.
   */
  public function buildHeader() {
    $headers = [
      $this->t('Field name'),
      $this->t('Field type'),
      $this->t('Entity type'),
      $this->t('Used in'),
    ];
    $permissions_list = $this->fieldPermissions->getList();
    foreach ($permissions_list as $permission_type => $permission_info) {
      $headers[] = ['data' => $permission_info['label'], 'class' => 'field-permissions-header'];
    }
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->t('Field permissions');
  }

  /**
   * Build table rows.
   */
  protected function buildRows() {
    /** @var \Drupal\field\FieldStorageConfigInterface $instances */
    $instances = $this->entityTypeManager->getStorage('field_storage_config')->loadMultiple();
    $rows = [];
    foreach ($instances as $key => $instance) {
      $rows[] = $this->buildRow($instance);
    }
    return $rows;
  }

  /**
   * Build a single row.
   *
   * @param \Drupal\field\FieldStorageConfigInterface $field_storage
   *   Field to populate row.
   *
   * @return array
   *   Build row.
   */
  protected function buildRow(FieldStorageConfigInterface $field_storage) {
    $row = [];
    if ($field_storage->isLocked()) {
      $row[0]['class'] = ['menu-disabled'];
      $row[0]['data'] = $this->t('@field_name (Locked)', ['@field_name' => $field_storage->getName()]);
    }
    else {
      $row[0]['data'] = $field_storage->getName();
    }
    $row[1]['data'] = $field_storage->getType();
    $row[2]['data'] = $field_storage->getTargetEntityTypeId();
    $row[3]['data'] = implode(",", $field_storage->getBundles());

    $default_type = $this->fieldPermissions->fieldGetPermissionType($field_storage);
    $field_permissions = $this->fieldPermissions->getPermissionsByRole();
    if ($default_type === FieldPermissionTypeInterface::ACCESS_PUBLIC) {
      $row[4]['data'] = $this->t('Not set (Field inherits content permissions.)');
      $row[4]['colspan'] = 5;
    }
    else {
      $plugin = $this->permissionTypeManager->createInstance($default_type, [], $field_storage);
      if ($plugin instanceof CustomPermissionsInterface) {
        // This is a field with custom permissions. Link the field to the
        // appropriate row of the permissions page, and theme it based on
        // whether all users have access.
        foreach (array_keys($plugin->getPermissions()) as $index => $permission) {
          $all_access = in_array($permission, $field_permissions[RoleInterface::ANONYMOUS_ID]) && in_array($permission, $field_permissions[RoleInterface::AUTHENTICATED_ID]);
          $class = $all_access ? 'field-permissions-status-on' : 'field-permissions-status-off';
          $text = $all_access ? $this->t('All users have this permission') : $this->t('Not all users have this permission');
          $link = Link::createFromRoute($text, 'user.admin_permissions', [], ['fragment' => 'module-field_permissions'])->toRenderable();
          $link['#options']['attributes']['title'] = $text;
          $row[4 + $index]['data'] = $link;
          $row[4 + $index]['class'] = [$class];
        }

      }
      else {
        // Use the label and description.
        $row[4]['data'] = $this->t('@label (@description)', ['@label' => $plugin->getLabel(), '@description' => $plugin->getDescription()]);
        $row[4]['colspan'] = 5;
      }
    }
    return $row;
  }

}
