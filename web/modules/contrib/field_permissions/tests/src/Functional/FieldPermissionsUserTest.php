<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\user\UserInterface;

/**
 * Test field permissions on users.
 *
 * @group field_permissions
 */
class FieldPermissionsUserTest extends FieldPermissionsTestBase {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->fieldName = mb_strtolower($this->randomMachineName());
    // Remove the '@' symbol so it isn't converted to an email link.
    $this->fieldText = str_replace('@', '', $this->randomString(42));

    // Allow the web user to administer user profiles.
    $this->webUserRole
      ->grantPermission('access user profiles')
      ->grantPermission('administer users')
      ->save();

    $this->entityDisplayRepository = $this->container->get('entity_display.repository');

    $this->addUserField();
  }

  /**
   * Test field permissions on user entities.
   */
  public function testUserFieldPermissions() {

    $this->drupalLogin($this->adminUser);
    // Compila il campo per l'utente admin.
    $this->checkUserFieldEdit($this->adminUser);
    $this->drupalLogout();

    // Controllo che si visibile ad altri utenti.
    $this->drupalLogin($this->limitedUser);
    $this->assertUserFieldAccess($this->adminUser);
    $this->drupalLogout();

    // These are all run within a single test method to avoid unnecessary site
    // installs.
    $this->checkPrivateField();
    $this->checkUserViewEditOwnField();
    $this->checkUserViewEditField();

  }

  /**
   * Adds a text field to the user entity.
   */
  protected function addUserField() {
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'user',
      'type' => 'text',
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'user',
      'label' => 'Textfield',
      'bundle' => 'user',
    ])->save();

    $this->entityDisplayRepository->getFormDisplay('user', 'user', 'default')
      ->setComponent($this->fieldName)
      ->save();

    $this->entityDisplayRepository->getFormDisplay('user', 'user', 'register')
      ->setComponent($this->fieldName)
      ->save();

    $this->entityDisplayRepository->getViewDisplay('user', 'user')
      ->setComponent($this->fieldName)
      ->save();
  }

  /**
   * Tests field permissions on the user edit form for a given account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to edit.
   */
  protected function checkUserFieldEdit(UserInterface $account) {
    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('Textfield');
    $edit = [];
    $edit[$this->fieldName . '[0][value]'] = $this->fieldText;
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->drupalGet($account->toUrl());
    $this->assertSession()->assertEscaped($this->fieldText);
  }

  /**
   * Verify the test field is accessible when viewing the given user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account to verify field permissions for viewing.
   */
  protected function assertUserFieldAccess(UserInterface $account) {
    $this->drupalGet($account->toUrl());
    $this->assertSession()->pageTextContains('Textfield');
  }

  /**
   * Verify the test field is not accessible when viewing the given user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account to verify field permissions for viewing.
   */
  protected function assertUserFieldNoAccess(UserInterface $account) {
    $this->drupalGet($account->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Textfield');
  }

  /**
   * Verifies that the current logged in user can edit the user field.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to edit.
   */
  protected function assertUserEditFieldAccess(UserInterface $account) {
    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('Textfield');
  }

  /**
   * Verifies that the current logged in user cannot edit the user field.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account to edit.
   */
  protected function assertUserEditFieldNoAccess(UserInterface $account) {
    $this->drupalGet($account->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Textfield');
  }

  /**
   * Set user field permissions to the given type.
   *
   * @param string $perm
   *   The permission type to set.
   * @param array $custom_permission
   *   An array of custom permissions.
   */
  private function setUserFieldPermission($perm, array $custom_permission = []) {
    $current_user = $this->loggedInUser;
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/people/accounts/fields/user.user.' . $this->fieldName);
    if ($perm === FieldPermissionTypeInterface::ACCESS_PUBLIC || $perm === FieldPermissionTypeInterface::ACCESS_PRIVATE) {
      $edit = ['type' => $perm];
      $this->drupalPostForm(NULL, $edit, t('Save settings'));
    }
    elseif ($perm === FieldPermissionTypeInterface::ACCESS_CUSTOM && !empty($custom_permission)) {
      $custom_permission['type'] = $perm;
      $this->drupalPostForm(NULL, $custom_permission, t('Save settings'));
    }
    if ($current_user) {
      $this->drupalLogin($current_user);
    }
  }

  /**
   * Test PUBLIC - view_own and edit_own field.
   */
  protected function checkUserViewEditOwnField() {
    $permission = [];
    // AGGIUNGE I PERMESSI DI VIEW_OWN. all'utente limitato.
    $this->drupalLogin($this->webUser);
    $perm = ['view own ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->limitUserRole, $perm, $permission);
    $this->setUserFieldPermission(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);
    // [admin] view/edit profile limit user (false).
    $this->assertUserFieldNoAccess($this->limitedUser);
    $this->assertUserEditFieldNoAccess($this->limitedUser);
    // [admin] view/edit your profile (false).
    $this->assertUserEditFieldNoAccess($this->adminUser);
    $this->assertUserFieldNoAccess($this->adminUser);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // [Limited user] view your profile (true).
    $this->assertUserFieldAccess($this->limitedUser);
    // [Limited user] view admin profile (false).
    $this->assertUserFieldNoAccess($this->adminUser);
    // [Limited user] edit your profile false.
    $this->assertUserEditFieldNoAccess($this->limitedUser);
    $this->drupalLogout();

    // AGGIUNGE I PERMESSI DI EDIT_OWN to limitUserRole.
    $this->drupalLogin($this->webUser);
    $permission = $this->grantCustomPermissions($this->limitUserRole, ['edit own ' . $this->fieldName], $permission);
    $this->setUserFieldPermission(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);
    // [admin] edit your profile (false).
    $this->assertUserEditFieldNoAccess($this->adminUser);
    // [admin] edit limit profile (false).
    $this->assertUserEditFieldNoAccess($this->limitedUser);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // [Limited user] edit your profile (true).
    $this->assertUserEditFieldAccess($this->limitedUser);
    $this->drupalLogout();

  }

  /**
   * Tests custom permissions.
   */
  protected function checkUserViewEditField() {

    $permission = [];
    // AGGIUNGE I PERMESSI DI VIEW_OWN. all'utente limitato.
    $this->drupalLogin($this->webUser);
    $perm = ['view ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->webUserRole, $perm, $permission);
    $this->setUserFieldPermission(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);
    $this->assertUserFieldAccess($this->limitedUser);

    $perm = ['edit ' . $this->fieldName];
    $permission = $this->grantCustomPermissions($this->webUserRole, $perm, $permission);
    $this->setUserFieldPermission(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);
    $this->assertUserEditFieldAccess($this->limitedUser);

    $this->drupalLogout();
  }

  /**
   * Test field access with private permissions.
   */
  protected function checkPrivateField() {
    $this->drupalLogin($this->webUser);
    $this->setUserFieldPermission(FieldPermissionTypeInterface::ACCESS_PRIVATE);
    $this->drupalLogout();

    $this->drupalLogin($this->limitedUser);
    // Controlla il perofilo dell'utente admin e non deve vedere il campo.
    $this->assertUserFieldNoAccess($this->adminUser);
    // Compila il campo per l'utente Limited.
    $this->checkUserFieldEdit($this->limitedUser);
    // Controlla che sia visibile.
    $this->assertUserFieldAccess($this->limitedUser);
    $this->drupalLogout();

    $this->drupalLogin($this->webUser);
    $this->assertUserFieldNoAccess($this->limitedUser);
    $this->assertUserEditFieldNoAccess($this->limitedUser);
    $this->drupalLogout();
  }

}
