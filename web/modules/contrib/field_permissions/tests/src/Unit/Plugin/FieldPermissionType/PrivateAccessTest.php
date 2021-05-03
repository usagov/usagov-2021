<?php

namespace Drupal\Tests\field_permissions\Unit\Plugin\FieldPermissionType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess;
use Drupal\Tests\UnitTestCase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Tests the private access plugin.
 *
 * @coversDefaultClass \Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess
 *
 * @group field_permissions
 */
class PrivateAccessTest extends UnitTestCase {

  /**
   * The private access plugin.
   *
   * @var \Drupal\field_permissions\Plugin\FieldPermissionType\PrivateAccess
   */
  protected $plugin;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $storage = $this->prophesize(FieldStorageConfigInterface::class);

    $this->plugin = new PrivateAccess([], 'private', [], $storage->reveal());
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
   * Data provider for ::testHasFieldAccess.
   */
  public function providerTestHasFieldAccess() {
    $cases = [];

    // Has 'access private fields' permission.
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('access private fields')->willReturn(TRUE);
    $entity = $this->prophesize(EntityInterface::class)->reveal();
    $cases[] = ['view', $entity, $account->reveal(), TRUE];
    $cases[] = ['edit', $entity, $account->reveal(), TRUE];

    // New entities always grant permission.
    $account = $this->prophesize(AccountInterface::class)->reveal();
    $entity = $this->prophesize(EntityInterface::class);
    $entity->isNew()->willReturn(TRUE);
    $cases[] = ['view', $entity->reveal(), $account, TRUE];
    $cases[] = ['edit', $entity->reveal(), $account, TRUE];

    // Special handling for user entities.
    // Account same as user entity.
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(42);
    $account->hasPermission('access private fields')->willReturn(FALSE);
    $entity = $this->prophesize(UserInterface::class);
    $entity->id()->willReturn(42);
    $entity->isNew()->willReturn(FALSE);
    $cases[] = ['view', $entity->reveal(), $account->reveal(), TRUE];
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), TRUE];

    // Different user than account will deny access.
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(27);
    $account->hasPermission('access private fields')->willReturn(FALSE);
    $entity = $this->prophesize(UserInterface::class);
    $entity->id()->willReturn(42);
    $entity->isNew()->willReturn(FALSE);
    $cases[] = ['view', $entity->reveal(), $account->reveal(), FALSE];
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), FALSE];

    // EntityOwnerInterface entities with access.
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(42);
    $account->hasPermission('access private fields')->willReturn(FALSE);
    $entity = $this->prophesize(EntityInterface::class)
      ->willImplement(EntityOwnerInterface::class);
    $entity->getOwnerId()->willReturn(42);
    $entity->isNew()->willReturn(FALSE);
    $cases[] = ['view', $entity->reveal(), $account->reveal(), TRUE];
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), TRUE];

    // EntityOwnerInterface entities without access.
    $account = $this->prophesize(AccountInterface::class);
    $account->id()->willReturn(42);
    $account->hasPermission('access private fields')->willReturn(FALSE);
    $entity = $this->prophesize(EntityInterface::class)
      ->willImplement(EntityOwnerInterface::class);
    $entity->getOwnerId()->willReturn(27);
    $entity->isNew()->willReturn(FALSE);
    $cases[] = ['view', $entity->reveal(), $account->reveal(), FALSE];
    $cases[] = ['edit', $entity->reveal(), $account->reveal(), FALSE];

    // Non-user, or none owner interface entity should always have access.
    $account = $this->prophesize(AccountInterface::class)->reveal();
    $entity = $this->prophesize(EntityInterface::class)->reveal();
    $cases[] = ['view', $entity, $account, TRUE];
    $cases[] = ['edit', $entity, $account, TRUE];

    return $cases;
  }

}
