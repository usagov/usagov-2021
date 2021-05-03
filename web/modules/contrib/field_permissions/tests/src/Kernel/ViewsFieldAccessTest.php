<?php

namespace Drupal\Tests\field_permissions\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Test that custom and private field access works with views.
 *
 * @group field_permissions
 *
 * The normal field access base class is not extended because it hard-codes
 * some assumptions about checking field access (it relies on logic in the
 * entity_test.module hooks).
 *
 * @see \Drupal\Tests\views\Kernel\Handler\FieldFieldAccessTestBase
 */
class ViewsFieldAccessTest extends ViewsKernelTestBase {

  /**
   * Stores an user entity with access to fields.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userWithAccess;

  /**
   * Stores an user entity without access to fields.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $userWithoutAccess;

  /**
   * Role with access.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $roleWithAccess;

  /**
   * Role without access.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $roleWithoutAccess;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field_permissions',
    'entity_test',
    'text',
    'field',
    'filter',
  ];

  /**
   * A field to test with.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * Field config on entity_test.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $field;

  /**
   * An entity to test with.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig('filter');

    // Add a field to test, with default permissions initially.
    $this->fieldStorage = FieldStorageConfig::create([
      'field_name' => 'test_field',
      'type' => 'text',
      'entity_type' => 'entity_test',
    ]);
    $this->fieldStorage->setThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_PUBLIC);
    $this->fieldStorage->save();
    $this->field = FieldConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $this->field->save();

    // The roles are identical to start. Individual test methods will grant and
    // revoke permissions as needed.
    $role_with_access = Role::create([
      'id' => 'with_access',
      'permissions' => ['view test entity'],
    ]);
    $role_with_access->save();
    $this->roleWithAccess = $role_with_access;

    $role_without_access = Role::create([
      'id' => 'without_access',
      'permissions' => ['view test entity'],
    ]);
    $role_without_access->save();
    $this->roleWithoutAccess = $role_without_access;

    $this->userWithAccess = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$role_with_access->id()],
    ]);
    $this->userWithAccess->save();
    $this->userWithoutAccess = User::create([
      'name' => $this->randomMachineName(),
      'roles' => [$role_without_access->id()],
    ]);
    $this->userWithoutAccess->save();

    $this->entity = EntityTest::create([
      $this->fieldStorage->getName() => [
        'value' => 'Test value',
        'format' => filter_default_format(),
      ],
    ]);
    $this->entity->save();
  }

  /**
   * Test default (public) permissions.
   */
  public function testPublicPermissions() {
    $this->assertFieldAccess();
  }

  /**
   * Tests custom permissions.
   */
  public function testCustomPermissions() {
    $this->fieldStorage->setThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_CUSTOM)->save();
    $this->roleWithAccess->grantPermission('view ' . $this->fieldStorage->getName())->save();
    $this->assertFieldAccess();
  }

  /**
   * Tests private permissions.
   */
  public function testPrivatePermissions() {
    $this->fieldStorage->setThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_PRIVATE)->save();

    // First check with the dedicated permission.
    $this->roleWithAccess->grantPermission('access private fields')->save();
    $this->assertFieldAccess();

    // Now check with actual ownership.
    $this->roleWithAccess->revokePermission('access private fields')->save();
    $this->entity->setOwner($this->userWithAccess)->save();
    $this->assertFieldAccess();
  }

  /**
   * Checks views field access on the test entity and field.
   */
  protected function assertFieldAccess() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('entity_test');
    $view_id = $this->randomMachineName();
    $data_table = $entity_type->getDataTable();
    $base_table = $data_table ?: $entity_type->getBaseTable();
    $field_name = $this->fieldStorage->getName();
    $field_content = $this->entity->{$field_name}->value;
    $entity = View::create([
      'id' => $view_id,
      'base_table' => $base_table,
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              $field_name => [
                'table' => $base_table . '__' . $field_name,
                'field' => $field_name,
                'id' => $field_name,
                'plugin_id' => 'field',
                'type' => 'text_default',
              ],
            ],
          ],
        ],
      ],
    ]);
    $entity->save();

    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = $this->container->get('account_switcher');

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $account_switcher->switchTo($this->userWithAccess);
    $executable = Views::getView($view_id);
    $build = $executable->preview();
    $this->setRawContent($renderer->renderRoot($build));

    $this->assertText($field_content);
    $this->assertTrue(isset($executable->field[$field_name]));

    $account_switcher->switchTo($this->userWithoutAccess);
    $executable = Views::getView($view_id);
    $build = $executable->preview();
    $this->setRawContent($renderer->renderRoot($build));

    // If this is a public permission, then the no access user can see it too.
    if ($this->fieldStorage->getThirdPartySetting('field_permissions', 'permission_type') === FieldPermissionTypeInterface::ACCESS_PUBLIC) {
      $this->assertText($field_content);
    }
    else {
      $this->assertNoText($field_content);
    }
  }

}
