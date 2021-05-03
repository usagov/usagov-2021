<?php

namespace Drupal\Tests\field_permissions\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;

/**
 * A base class for field permissions web tests to extend.
 */
abstract class FieldPermissionsTestBase extends BrowserTestBase {

  /**
   * An administrative user with permission to configure comment settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * An limit user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $limitedUser;

  /**
   * A normal user with permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * The role for the admin user.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $adminUserRole;

  /**
   * The role for the authenticated user.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $limitUserRole;

  /**
   * The role for the anonymous user.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $webUserRole;

  /**
   * A node to test with.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Field name to test field permissions on.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * Contents of a field with permissions.
   *
   * @var string
   */
  protected $fieldText;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'field',
    'field_ui',
    'user',
    'field_permissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create node type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->checkPermissions(['create article content']);

    $this->adminUser = $this->drupalCreateUser([], NULL, TRUE);
    $this->adminUserRole = Role::load($this->adminUser->getRoles(TRUE)[0]);

    $this->limitedUser = $this->drupalCreateUser([
      'access content',
      'access user profiles',
      'create article content',
      'edit any article content',
    ]);

    $this->webUser = $this->drupalCreateUser([
      'access content',
      'create article content',
      'edit any article content',
    ]);

    $this->limitUserRole = Role::load($this->limitedUser->getRoles(TRUE)[0]);
    $this->webUserRole = Role::load($this->webUser->getRoles(TRUE)[0]);
  }

  /**
   * Fill out a custom permission matrix for a given role.
   *
   * @param string $role
   *   The role to grant permissions for. Other roles will not have any
   *   permissions.
   * @param array $field_perm
   *   Permissions to grant the given role.
   *
   * @return array
   *   The complete custom permissions matrix, keyed by {OP}_{FIELD_NAME} and
   *   then role ID, with a value of TRUE if the permission is granted, FALSE
   *   otherwise.
   */
  protected function getCustomPermissionGrid($role, array $field_perm = []) {
    $custom_perm = [];
    $permission_list = $this->container->get('field_permissions.permissions_service')->getAllPermissions();
    $permission_list = array_keys($permission_list);
    $permission_role = array_keys(user_roles());

    // Set all check to false.
    foreach ($permission_role as $rname) {
      foreach ($permission_list as $perm) {
        $key = 'permissions[' . $perm . '][' . $rname . ']';
        $custom_perm[$key] = FALSE;
      }
    }
    // Set perm check to true.
    foreach ($field_perm as $perm) {
      $key = 'permissions[' . $perm . '][' . $role->id() . ']';
      $custom_perm[$key] = TRUE;
    }
    return $custom_perm;
  }

  /**
   * Appends existing permissions grid with new permissions.
   *
   * @param string $role
   *   The role ID to grant permissions for.
   * @param array $new_permissions
   *   An array of new permissions to grant.
   * @param array $existing_custom_permission
   *   The existing custom permissions grid.
   *
   * @return array
   *   The complete custom permissions matrix.
   */
  protected function grantCustomPermissions($role, array $new_permissions = [], array $existing_custom_permission = []) {
    $permissions_grid = $this->getCustomPermissionGrid($role, $new_permissions);
    foreach ($permissions_grid as $key => $val) {
      if (isset($existing_custom_permission[$key]) && $existing_custom_permission[$key] === TRUE) {
        $permissions_grid[$key] = TRUE;
      }
    }
    return $permissions_grid;
  }

}
