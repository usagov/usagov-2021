<?php

namespace Drupal\Tests\field_permissions\Kernel\Plugin\FieldPermissionType;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Integration tests for the field permission type plugin manager.
 *
 * @group field_permissions
 *
 * @coversDefaultClass \Drupal\field_permissions\Plugin\FieldPermissionType\Manager
 */
class ManagerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'field',
    'field_permissions',
    'field_permissions_test',
    'system',
    'user',
  ];

  /**
   * The field permission plugin manager service.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\Manager
   */
  protected $fieldPermissionTypeManager;

  /**
   * A user to test with.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');

    $this->fieldPermissionTypeManager = $this->container->get('plugin.field_permissions.types.manager');
    $this->account = $this->createUser(['cancel account']);
  }

  /**
   * Test that plugin instances can be created.
   *
   * @covers ::createInstance
   *
   * @see \Drupal\field_permissions_test\Plugin\FieldPermissionType\TestAccess
   */
  public function testCreateInstance() {
    $entity = EntityTest::create();
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_foo',
      'type' => 'text',
      'entity_type' => 'entity_test',
    ]);
    $plugin = $this->fieldPermissionTypeManager->createInstance('test_access', [], $field_storage);

    // All 'view' operations are accessible.
    $this->assertTrue($plugin->hasFieldAccess('view', $entity, $this->account));

    // Edit access is only granted if the field doesn't start with 'edit_'.
    $this->assertFalse($plugin->hasFieldAccess('edit', $entity, $this->account));
  }

  /**
   * Tests that plugin sorting is working.
   *
   * @covers ::getDefinitions
   */
  public function testGetDefinitions() {
    $definitions = $this->fieldPermissionTypeManager->getDefinitions();

    // There should be 3 (one test plugin from the testing module).
    $this->assertEquals(3, count($definitions));

    // The test plugin should be between the private and custom.
    $expected = ['private', 'test_access', 'custom'];
    $this->assertSame($expected, array_keys($definitions));
  }

}
