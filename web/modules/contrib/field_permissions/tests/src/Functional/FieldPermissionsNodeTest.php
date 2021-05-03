<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\Core\Url;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;

/**
 * Test field permissions on nodes.
 *
 * @group field_permissions
 */
class FieldPermissionsNodeTest extends FieldPermissionsTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Grant the web user permission to administer node fields.
    $this->webUserRole
      ->grantPermission('administer content types')
      ->grantPermission('administer node fields')
      ->save();
  }

  /**
   * Test field permissions on nodes.
   */
  public function testNodeFieldPermissions() {
    // These are all run within a single test method to avoid unnecessary site
    // installs.
    $this->checkPermissionPage();
    $this->checkFieldPermissionConfigurationEdit();
    $this->checkInitAddNode();
    $this->checkChengeToPrivateField();
    $this->checkViewOwnField();
    $this->checkViewEditOwnField();
    $this->checkViewEditAllField();
  }

  /**
   * Set the bode body field permissions to the given type.
   *
   * @param string $perm
   *   The permission type.
   * @param array $custom_permission
   *   An array of custom permissions.
   *
   * @todo Directly set the field permissions rather than using the UI.
   */
  protected function setNodeFieldPermissions($perm, array $custom_permission = []) {
    $current_user = $this->loggedInUser;
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.body');
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
   * Create a node directly via the API.
   */
  protected function addNode() {
    $this->node = $this->drupalCreateNode(['type' => 'article', 'uid' => $this->limitedUser->id()]);
    $this->drupalGet('node/' . $this->node->id());
    $node_body = $this->node->getFields()['body']->getValue();
    $this->assertSession()->responseContains($node_body[0]['value']);
  }

  /**
   * Create a node through the UI.
   */
  protected function addNodeUi() {
    $this->drupalGet('node/add/article');
    $this->assertSession()->pageTextContains('Body');
    $edit = [];
    $node_name = $this->randomMachineName();
    $edit['body[0][value]'] = $this->randomString();
    $edit['title[0][value]'] = $node_name;
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertSession()->pageTextContains(t('Article @name has been created.', ['@name' => $node_name]));
  }

  /**
   * Asserts that the body field is visible.
   */
  protected function assertNodeFieldVisible() {
    $field_value = $this->node->getFields()['body']->getValue();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->pageTextContains($field_value[0]['value']);
  }

  /**
   * Asserts that the body field is not visible.
   */
  protected function assertNodeFieldHidden() {
    $field_value = $this->node->getFields()['body']->getValue();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains($field_value[0]['value']);
  }

  /**
   * Asserts that the node field is editable.
   */
  protected function assertNodeFieldEditAccess() {
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->pageTextContains('Title');
    $this->assertSession()->pageTextContains('Body');
  }

  /**
   * Asserts that the node field is not editable.
   */
  protected function assertNodeFieldEditNoAccess() {
    $this->drupalGet('node/' . $this->node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Title');
    $this->assertSession()->pageTextNotContains('Body');
  }

  /**
   * Test field permission configuration access.
   */
  protected function checkFieldPermissionConfigurationEdit() {
    $this->drupalLogin($this->webUser);
    // Test page without admin field permission.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.body');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Field visibility and permissions');
    $this->webUserRole->grantPermission('administer field permissions')->save();
    // Test page with admin field permission.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.body');
    $this->assertSession()->pageTextContains('Field visibility and permissions');
    $this->drupalLogout();
  }

  /**
   * Test permissions page.
   */
  protected function checkPermissionPage() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet(Url::fromRoute('user.admin_permissions'));
    $this->assertSession()->pageTextContains('Access other users private fields');
    $this->assertSession()->pageTextContains('Administer field permissions');
    $this->drupalLogout();
  }

  /**
   * Test create content.
   */
  protected function checkInitAddNode() {
    $this->drupalLogin($this->limitedUser);
    $this->addNodeUi();
    $this->addNode();
    $this->drupalLogout();
  }

  /**
   * Test PUBLIC - PRIVATE EDIT - VIEW.
   */
  protected function checkChengeToPrivateField() {
    $this->drupalLogin($this->webUser);

    $this->assertNodeFieldVisible();

    $this->webUserRole->grantPermission('administer field permissions')->save();
    $this->setNodeFieldPermissions(FieldPermissionTypeInterface::ACCESS_PRIVATE);
    $this->assertNodeFieldHidden();

    $this->webUserRole->grantPermission('access private fields')->save();
    $this->assertNodeFieldVisible();
    $this->drupalLogout();
  }

  /**
   * Test PUBLIC - view own field.
   */
  protected function checkViewOwnField() {
    $permission = [];
    $permission = $this->grantCustomPermissions($this->limitUserRole, ['view own body'], $permission);
    $this->setNodeFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);

    // Login width author node.
    $this->drupalLogin($this->limitedUser);
    $this->assertNodeFieldVisible();
    $this->assertNodeFieldEditNoAccess();
    $this->drupalLogout();

    // Login webuser.
    $this->drupalLogin($this->webUser);
    $this->assertNodeFieldHidden();
    $this->assertNodeFieldEditNoAccess();
    $this->drupalLogout();
  }

  /**
   * Test PUBLIC - view own field.
   */
  protected function checkViewEditOwnField() {
    $permission = [];
    $permission = $this->grantCustomPermissions($this->limitUserRole, ['view own body', 'edit own body'], $permission);
    $this->setNodeFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);

    // Login width author node.
    $this->drupalLogin($this->limitedUser);
    $this->assertNodeFieldVisible();
    $this->assertNodeFieldEditAccess();
    $this->drupalLogout();

    // Login webuser.
    $this->drupalLogin($this->webUser);
    $this->assertNodeFieldHidden();
    $this->assertNodeFieldEditNoAccess();
    $this->drupalLogout();

  }

  /**
   * Test - view edit all field.
   */
  protected function checkViewEditAllField() {
    $this->drupalLogin($this->webUser);
    $this->assertNodeFieldHidden();
    $this->assertNodeFieldEditNoAccess();
    $this->drupalLogout();
    $permission = [];
    $permission = $this->grantCustomPermissions($this->webUserRole, ['view body', 'edit body'], $permission);
    $this->setNodeFieldPermissions(FieldPermissionTypeInterface::ACCESS_CUSTOM, $permission);

    $this->drupalLogin($this->webUser);
    $this->assertNodeFieldVisible();
    $this->assertNodeFieldEditAccess();
    $this->drupalLogout();
  }

}
