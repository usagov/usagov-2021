<?php

namespace Drupal\field_permissions\Plugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\user\RoleStorageInterface;

/**
 * Indicates that the plugin implementing this has field config form settings.
 */
interface AdminFormSettingsInterface {

  /**
   * Build or alter the field admin form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The user role storage.
   */
  public function buildAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage);

  /**
   * Allows the plugin to react to the field settings form submission.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\user\RoleStorageInterface $role_storage
   *   The user role storage.
   */
  public function submitAdminForm(array &$form, FormStateInterface $form_state, RoleStorageInterface $role_storage);

}
