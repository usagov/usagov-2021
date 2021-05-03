<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;

/**
 * Tests scheduled transactions dynamic permissions.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsPermissions
 * @group scheduled_transitions
 */
class ScheduledTransitionsPermissionsTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use ScheduledTransitionTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test_revlog',
    'entity_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test_revlog');
  }

  /**
   * Tests dynamic permissions.
   *
   * @covers ::permissions
   */
  public function testPermissions(): void {
    $this->enabledBundles([['entity_test_revlog', 'entity_test_revlog']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    /** @var \Drupal\user\PermissionHandlerInterface $permissionHandler */
    $permissionHandler = \Drupal::service('user.permissions');

    $permissions = $permissionHandler->getPermissions();
    $this->assertArrayHasKey('view scheduled transitions entity_test_revlog entity_test_revlog', $permissions);
    $this->assertArrayHasKey('add scheduled transitions entity_test_revlog entity_test_revlog', $permissions);
  }

}
