<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Unit;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm;
use Drupal\scheduled_transitions\ScheduledTransitionsEntityHooks;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions;
use Drupal\Tests\UnitTestCase;

/**
 * Tests entity hooks.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsEntityHooks
 * @group scheduled_transitions
 */
class ScheduledTransitionsEntityHooksUnitTest extends UnitTestCase {

  /**
   * A test config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $testConfigFactory;

  /**
   * A test entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $testEntityTypeManager;

  /**
   * A test moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $testModerationInformation;

  /**
   * ScheduledTransitionsEntityHooksUnitTest constructor.
   */
  public function __construct() {
    parent::__construct();
    $this->testConfigFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->testEntityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->testModerationInformation = $this->createMock(ModerationInformationInterface::class);

    $cacheContextsManager = $this->getMockBuilder(CacheContextsManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $cacheContextsManager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cacheContextsManager);
    $this->testContainer = $container;
  }

  /**
   * Tests operation not handled by hook.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessUnhandled(): void {
    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $operation = $this->randomMachineName();
    $account = $this->createMock(AccountInterface::class);

    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultNeutral::class, $access);
  }

  /**
   * Tests view operation, and when user doesn't have permission.
   *
   * No permission returns neutral with correct cacheabiltiy.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessViewOperationNoPermission(): void {
    \Drupal::setContainer($this->testContainer);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_VIEW_TRANSITIONS;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('view scheduled transitions foo bar')
      ->willReturn(FALSE);

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertEquals("The 'view scheduled transitions foo bar' permission is required.", $access->getReason());
  }

  /**
   * Tests add operation, and when user doesn't have permission.
   *
   * No permission returns neutral with correct cacheabiltiy.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessAddOperationNoPermission(): void {
    \Drupal::setContainer($this->testContainer);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_ADD_TRANSITION;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('add scheduled transitions foo bar')
      ->willReturn(FALSE);

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertEquals("The 'add scheduled transitions foo bar' permission is required.", $access->getReason());
  }

  /**
   * Tests view operation, and when user has permission, but no mirroring.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessViewOperationHasPermissionNoMirroring(): void {
    \Drupal::setContainer($this->testContainer);

    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('mirror_operations.' . ScheduledTransitionsPermissions::ENTITY_OPERATION_VIEW_TRANSITIONS)
      ->willReturn(NULL);

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_VIEW_TRANSITIONS;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('view scheduled transitions foo bar')
      ->willReturn(TRUE);

    $entity->expects($this->never())
      ->method('access');

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultNeutral::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertNull($access->getReason());
    $this->assertEquals([ScheduledTransitionsSettingsForm::SETTINGS_TAG], $access->getCacheTags());
  }

  /**
   * Tests add operation, and when user has permission, but no mirroring.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessAddOperationHasPermissionNoMirroring(): void {
    \Drupal::setContainer($this->testContainer);

    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('mirror_operations.' . ScheduledTransitionsPermissions::ENTITY_OPERATION_ADD_TRANSITION)
      ->willReturn(NULL);

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_ADD_TRANSITION;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('add scheduled transitions foo bar')
      ->willReturn(TRUE);

    $entity->expects($this->never())
      ->method('access');

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultNeutral::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertNull($access->getReason());
    $this->assertEquals([ScheduledTransitionsSettingsForm::SETTINGS_TAG], $access->getCacheTags());
  }

  /**
   * Tests view operation, and when user has permission, but no mirroring.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessViewOperationHasPermissionWithMirroring(): void {
    \Drupal::setContainer($this->testContainer);

    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('mirror_operations.' . ScheduledTransitionsPermissions::ENTITY_OPERATION_VIEW_TRANSITIONS)
      ->willReturn('testop');

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_VIEW_TRANSITIONS;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('view scheduled transitions foo bar')
      ->willReturn(TRUE);

    $entity->expects($this->once())
      ->method('access')
      ->with('testop', $account, TRUE)
      ->willReturn(AccessResult::forbidden('no can do'));

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertEquals('no can do', $access->getReason());
    $this->assertEquals([ScheduledTransitionsSettingsForm::SETTINGS_TAG], $access->getCacheTags());
  }

  /**
   * Tests add operation, and when user has permission, but no mirroring.
   *
   * @covers ::entityAccess
   */
  public function testEntityAccessAddOperationHasPermissionWithMirroring(): void {
    \Drupal::setContainer($this->testContainer);

    $testConfig = $this->createMock(ImmutableConfig::class);
    $testConfig->expects($this->once())
      ->method('get')
      ->with('mirror_operations.' . ScheduledTransitionsPermissions::ENTITY_OPERATION_ADD_TRANSITION)
      ->willReturn('testop');

    $this->testConfigFactory->expects($this->once())
      ->method('get')
      ->with('scheduled_transitions.settings')
      ->willReturn($testConfig);

    $entityHooks = new ScheduledTransitionsEntityHooks($this->testConfigFactory, $this->testEntityTypeManager, $this->testModerationInformation);

    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('getEntityTypeId')
      ->willReturn('foo');
    $entity->expects($this->once())
      ->method('bundle')
      ->willReturn('bar');

    $operation = ScheduledTransitionsPermissions::ENTITY_OPERATION_ADD_TRANSITION;
    $account = $this->createMock(AccountInterface::class);
    $account->expects($this->once())
      ->method('hasPermission')
      ->with('add scheduled transitions foo bar')
      ->willReturn(TRUE);

    $entity->expects($this->once())
      ->method('access')
      ->with('testop', $account, TRUE)
      ->willReturn(AccessResult::forbidden('no can do'));

    /** @var \Drupal\Core\Access\AccessResultForbidden $access */
    $access = $entityHooks->entityAccess($entity, $operation, $account);
    $this->assertInstanceOf(AccessResultForbidden::class, $access);
    $this->assertEquals(['user.permissions'], $access->getCacheContexts());
    $this->assertEquals('no can do', $access->getReason());
    $this->assertEquals([ScheduledTransitionsSettingsForm::SETTINGS_TAG], $access->getCacheTags());
  }

}
