<?php

namespace Drupal\Tests\field_permissions\Unit;

use Drupal\comment\CommentManagerInterface;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\FieldPermissionsService;
use Drupal\field_permissions\Plugin\FieldPermissionType\Manager;
use Drupal\field_permissions\Plugin\FieldPermissionTypeInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * Tests the field permissions service.
 *
 * @group field_permissions
 *
 * @coversDefaultClass \Drupal\field_permissions\FieldPermissionsService
 */
class FieldPermissionsServiceTest extends UnitTestCase {

  /**
   * Mock entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Mock permission type manager.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\Manager
   */
  protected $permissionTypeManager;

  /**
   * The field permissions service.
   *
   * @var \Drupal\field_permissions\FieldPermissionsServiceInterface
   */
  protected $fieldPermissionsService;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->entityTypeManager = $entity_type_manager->reveal();

    $permission_type_manager = $this->prophesize(Manager::class);
    $this->permissionTypeManager = $permission_type_manager->reveal();

    $this->fieldPermissionsService = new FieldPermissionsService($this->entityTypeManager, $this->permissionTypeManager);
  }

  /**
   * Test field access method.
   *
   * @covers ::getFieldAccess
   *
   * @dataProvider providerTestGetFieldAccess
   */
  public function testGetFieldAccess($operation, FieldItemListInterface $items, AccountInterface $account, FieldDefinitionInterface $field_definition, $expected_access) {
    $this->assertEquals($expected_access, $this->fieldPermissionsService->getFieldAccess($operation, $items, $account, $field_definition));
  }

  /**
   * Data provider for ::testGetFieldAccess.
   */
  public function providerTestGetFieldAccess() {
    $cases = [];

    $field_item_list = $this->prophesize(FieldItemListInterface::class)->reveal();

    // Administrator role.
    $account = $this->prophesize(AccountInterface::class);
    $account->getRoles()->willReturn(['administrator']);
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $storage = $this->prophesize(FieldStorageConfigInterface::class);
    $storage->getThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_PUBLIC)->willReturn('foo');
    $field_definition->getFieldStorageDefinition()->willReturn($storage->reveal());
    $cases[] = [
      'view',
      $field_item_list,
      $account->reveal(),
      $field_definition->reveal(),
      TRUE,
    ];

    // No admin roles, but public access.
    $account = $this->prophesize(AccountInterface::class);
    $account->getRoles()->willReturn(['blah']);
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $storage = $this->prophesize(FieldStorageConfigInterface::class);
    $storage->getThirdPartySetting('field_permissions', 'permission_type', FieldPermissionTypeInterface::ACCESS_PUBLIC)->willReturn(FieldPermissionTypeInterface::ACCESS_PUBLIC);
    $field_definition->getFieldStorageDefinition()->willReturn($storage->reveal());
    $cases[] = [
      'view',
      $field_item_list,
      $account->reveal(),
      $field_definition->reveal(),
      TRUE,
    ];

    return $cases;
  }

  /**
   * Test the comment field method.
   *
   * @covers ::isCommentField
   */
  public function testIsCommentField() {
    $field_definition = $this->prophesize(FieldDefinitionInterface::class);

    // Comment module not enabled.
    $container = new Container();
    \Drupal::setContainer($container);
    $this->assertFalse($this->fieldPermissionsService->isCommentField($field_definition->reveal()));

    // Comment module enabled, no comment fields.
    $comment_manager = $this->prophesize(CommentManagerInterface::class);
    $comment_manager->getFields(Argument::any())->willReturn([]);
    $container->set('comment.manager', $comment_manager->reveal());
    $this->assertFalse($this->fieldPermissionsService->isCommentField($field_definition->reveal()));

    // Comment module enabled, no matching fields.
    $field_definition->getName()->willReturn('foo_field');
    $field_definition->getTargetEntityTypeId()->willReturn('foo');
    $comment_manager->getFields('foo')->willReturn(['bar_field' => 'bar']);
    $container->set('comment.manager', $comment_manager->reveal());
    $this->assertFalse($this->fieldPermissionsService->isCommentField($field_definition->reveal()));

    // A comment field!
    $comment_manager->getFields('foo')->willReturn(['bar_field' => 'bar', 'foo_field' => 'foo']);
    $container->set('comment.manager', $comment_manager->reveal());
    $this->assertTrue($this->fieldPermissionsService->isCommentField($field_definition->reveal()));
  }

}
