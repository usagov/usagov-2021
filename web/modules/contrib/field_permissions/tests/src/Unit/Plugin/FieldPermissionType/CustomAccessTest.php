<?php

namespace Drupal\Tests\field_permissions\Unit\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Tests for the custom access permission type plugin.
 *
 * @coversDefaultClass \Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess
 *
 * @group field_permissions
 */
class CustomAccessTest extends UnitTestCase {

  /**
   * The custom access plugin.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\CustomAccess
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $storage = $this->prophesize(FieldStorageConfigInterface::class);
    $storage->getName()->willReturn('foo_field');

    $this->plugin = new CustomAccess([], 'custom', [], $storage->reveal());
  }

  /**
   * Test for `hasFieldAccess`.
   *
   * @covers ::hasFieldAccess
   *
   * @dataProvider providerTestHasFieldAccess
   */
  public function testHasFieldAccess($operation, EntityInterface $entity, AccountInterface $account, $access) {
    $this->assertEquals($access, $this->plugin->hasFieldAccess($operation, $entity, $account));
  }

  /**
   * Test an invalid operation.
   *
   * @covers ::hasFieldAccess
   */
  public function testInvalidOperation() {
    // Edit|view access allowed.
    $account = $this->prophesize(AccountInterface::class);
    $entity = $this->prophesize(EntityInterface::class);
    $this->expectException(\AssertionError::class, 'The operation is either "edit" or "view", "bad operation" given instead.');
    $this->plugin->hasFieldAccess('bad operation', $entity->reveal(), $account->reveal());
  }

  /**
   * Data provider for ::testHasFieldAccess.
   */
  public function providerTestHasFieldAccess() {
    $cases = [];

    // Create access allowed.
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('create foo_field')->willReturn(TRUE);
    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(TRUE);
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), TRUE];

    // Create access denied.
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('create foo_field')->willReturn(FALSE);
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), FALSE];

    // Add edit and view.
    foreach (['edit', 'view'] as $operation) {
      // Edit|view access allowed.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(TRUE);
      $entity = $this->prophesize(EntityInterface::class);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), TRUE];

      // Edit|view access denied.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $entity = $this->prophesize(EntityInterface::class);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), FALSE];

      // User entity, edit|view own allowed.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(TRUE);
      $account->id()->willReturn(42);
      $entity = $this->prophesize(UserInterface::class);
      $entity->id()->willReturn(42);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), TRUE];

      // User entity, edit|view own denied.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(FALSE);
      $account->id()->willReturn(42);
      $entity = $this->prophesize(UserInterface::class);
      $entity->id()->willReturn(42);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), FALSE];

      // User entity, edit|view own allowed, non-matching entity.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(TRUE);
      $account->id()->willReturn(42);
      $entity = $this->prophesize(UserInterface::class);
      $entity->id()->willReturn(27);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), FALSE];

      // Entity implementing EntityOwnerInterface, edit|view own allowed.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(TRUE);
      $account->id()->willReturn(42);
      $entity = $this->prophesize(EntityInterface::class);
      $entity->willImplement(EntityOwnerInterface::class);
      $entity->getOwnerId()->willReturn(42);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), TRUE];

      // Entity implementing EntityOwnerInterface, edit|view own denied.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(FALSE);
      $account->id()->willReturn(42);
      $entity = $this->prophesize(EntityInterface::class);
      $entity->willImplement(EntityOwnerInterface::class);
      $entity->getOwnerId()->willReturn(42);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), FALSE];

      // Entity implementing EntityOwnerInterface, edit|view own allowed, but
      // non-matching entity owner.
      $account = $this->prophesize(AccountInterface::class);
      $account->hasPermission($operation . ' foo_field')->willReturn(FALSE);
      $account->hasPermission($operation . ' own foo_field')->willReturn(TRUE);
      $account->id()->willReturn(27);
      $entity = $this->prophesize(EntityInterface::class);
      $entity->willImplement(EntityOwnerInterface::class);
      $entity->getOwnerId()->willReturn(42);
      $entity->isNew()->willReturn(FALSE);
      $cases[] = [$operation, $entity->reveal(), $account->reveal(), FALSE];
    }

    return $cases;
  }

}
