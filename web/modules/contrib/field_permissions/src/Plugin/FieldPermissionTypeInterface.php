<?php

namespace Drupal\field_permissions\Plugin;

use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * A field permission type plugin interface.
 */
interface FieldPermissionTypeInterface extends PluginInspectionInterface, DerivativeInspectionInterface {

  /**
   * Indicates that a field does not have field-specific access control.
   *
   * Public field access is not implemented as a plugin because it effectively
   * means this module does not process any access control for fields with this
   * type of permission.
   */
  const ACCESS_PUBLIC = 'public';

  /**
   * Indicates that a field is using the private access permission type.
   *
   * Private fields are never displayed, and are only editable by the author
   * (and by site administrators with the 'access private fields' permission).
   *
   * @internal
   *
   * This is here as a helper since there are still special handling of the
   * various plugins throughout this module.
   *
   * @see \Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess
   */
  const ACCESS_PRIVATE = 'private';

  /**
   * Indicates that a field is using the custom permission type.
   *
   * @internal
   *
   * This is here as a helper since there are still special handling of the
   * various plugins throughout this module.
   *
   * @see \Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess
   */
  const ACCESS_CUSTOM = 'custom';

  /**
   * The permission type label.
   *
   * @return string
   *   The field permission type label.
   */
  public function getLabel();

  /**
   * The permission type description.
   *
   * @return string
   *   The field permission type description.
   */
  public function getDescription();

  /**
   * Determine if access to the field is granted for a given account.
   *
   * @param string $operation
   *   The operation to check. Either 'view' or 'edit'.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the field is attached to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user to check access for.
   *
   * @return bool
   *   The access result.
   */
  public function hasFieldAccess($operation, EntityInterface $entity, AccountInterface $account);

}
