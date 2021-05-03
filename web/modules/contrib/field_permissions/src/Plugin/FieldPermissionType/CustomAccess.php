<?php

namespace Drupal\field_permissions\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field_permissions\FieldPermissionsService;
use Drupal\field_permissions\Plugin\AdminFormSettingsInterface;
use Drupal\field_permissions\Plugin\CustomPermissionsInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserInterface;

/**
 * Defines custom access for fields.
 *
 * @FieldPermissionType(
 *   id = "custom",
 *   title = @Translation("Custom permissions"),
 *   description = @Translation("Define custom permissions for this field."),
 *   weight = 50
 * )
 */
class CustomAccess extends Base implements CustomPermissionsInterface, AdminFormSettingsInterface {

  /**
   * {@inheritdoc}
   */
  public function hasFieldAccess($operation, EntityInterface $entity, AccountInterface $account) {
    assert(in_array($operation, ["edit", "view"]), 'The operation is either "edit" or "view", "' . $operation . '" given instead.');

    $field_name = $this->fieldStorage->getName();
    if ($operation === 'edit' && $entity->isNew()) {
      return $account->hasPermission('create ' . $field_name);
    }
    if ($account->hasPermission($operation . ' ' . $field_name)) {
      return TRUE;
    }
    else {
      // User entities don't implement `EntityOwnerInterface`.
      if ($entity instanceof UserInterface) {
        return $entity->id() == $account->id() && $account->hasPermission($operation . ' own ' . $field_name);
      }
      elseif ($entity instanceof EntityOwnerInterface) {
        return $entity->getOwnerId() == $account->id() && $account->hasPermission($operation . ' own ' . $field_name);
      }
    }

    // Default to deny since access can be explicitly granted (edit field_name),
    // even if this entity type doesn't implement the EntityOwnerInterface.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasFieldViewAccessForEveryEntity(AccountInterface $account) {
    $field_name = $this->fieldStorage->getName();
    return $account->hasPermission('view ' . $field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function buildAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    $this->addPermissionsGrid($form, $form_state, $role_storage);

    // Only display the permissions matrix if this type is selected.
    $form['#attached']['library'][] = 'field_permissions/field_permissions';
  }

  /**
   * {@inheritdoc}
   */
  public function submitAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    $custom_permissions = $form_state->getValue('permissions');
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = [];
    foreach ($custom_permissions as $permission_name => $field_perm) {
      foreach ($field_perm as $role_name => $role_permission) {
        $roles[$role_name] = $role_storage->load($role_name);
        // If using this plugin, set permissions to the value submitted in the
        // form. Otherwise remove all permissions as they will no longer exist.
        $role_permission = $form_state->getValue('type') === $this->getPluginId() ? $role_permission : FALSE;
        if ($role_permission) {
          $roles[$role_name]->grantPermission($permission_name);
        }
        else {
          $roles[$role_name]->revokePermission($permission_name);
        }
      }
    }
    // Save all roles.
    foreach ($roles as $role) {
      $role->trustData()->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $permissions = [];
    $field_name = $this->fieldStorage->getName();
    $permission_list = FieldPermissionsService::getList($field_name);
    $perms_name = array_keys($permission_list);
    foreach ($perms_name as $perm_name) {
      $name = $perm_name . ' ' . $field_name;
      $permissions[$name] = $permission_list[$perm_name];
    }
    return $permissions;
  }

  /**
   * Attach a permissions grid to the field edit form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The user role storage.
   */
  protected function addPermissionsGrid(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage) {
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $role_storage->loadMultiple();
    $permissions = $this->getPermissions();
    $options = array_keys($permissions);

    // The permissions table.
    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [$this->t('Permission')],
      '#id' => 'permissions',
      '#attributes' => ['class' => ['permissions', 'js-permissions']],
      '#sticky' => TRUE,
    ];
    foreach ($roles as $role) {
      $form['permissions']['#header'][] = [
        'data' => $role->label(),
        'class' => ['checkbox'],
      ];
    }
    // @todo Remove call to global service.
    $test = \Drupal::service('field_permissions.permissions_service')->getPermissionsByRole();
    foreach ($permissions as $provider => $permission) {
      $form['permissions'][$provider]['description'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="permission"><span class="title">{{ title }}</span>{% if description or warning %}<div class="description">{% if warning %}<em class="permission-warning">{{ warning }}</em> {% endif %}{{ description }}</div>{% endif %}</div>',
        '#context' => [
          'title' => $permission["title"],
        ],
      ];
      $options[$provider] = '';
      foreach ($roles as $name => $role) {
        $form['permissions'][$provider][$name] = [
          '#title' => $name . ': ' . $permission["title"],
          '#title_display' => 'invisible',
          '#type' => 'checkbox',
          '#attributes' => ['class' => ['rid-' . $name, 'js-rid-' . $name]],
          '#wrapper_attributes' => [
            'class' => ['checkbox'],
          ],
        ];
        if (!empty($test[$name]) && in_array($provider, $test[$name])) {
          $form['permissions'][$provider][$name]['#default_value'] = in_array($provider, $test[$name]);
        }
        if ($role->isAdmin()) {
          $form['permissions'][$provider][$name]['#disabled'] = TRUE;
          $form['permissions'][$provider][$name]['#default_value'] = TRUE;
        }
      }
    }
    // Attach the Drupal user permissions library.
    $form['#attached']['library'][] = 'user/drupal.user.permissions';
  }

}
