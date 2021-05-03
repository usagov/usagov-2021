<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm as SettingsForm;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;

/**
 * Tests scheduled transactions utility.
 *
 * @coversDefaultClass \Drupal\scheduled_transitions\ScheduledTransitionsUtility
 * @group scheduled_transitions
 */
class ScheduledTransitionsUtilityTest extends KernelTestBase {

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
   * Tests applicable bundles helper.
   *
   * @covers ::getApplicableBundles
   */
  public function testGetApplicableBundles(): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility */
    $scheduledTransitionsUtility = \Drupal::service('scheduled_transitions.utility');

    $result = $scheduledTransitionsUtility->getApplicableBundles();
    $this->assertEquals([], $result);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $result = $scheduledTransitionsUtility->getApplicableBundles();
    $this->assertEquals(['entity_test_revlog' => ['entity_test_revlog']], $result);
  }

  /**
   * Tests enabled bundles helper.
   *
   * @covers ::getBundles
   */
  public function testGetBundles(): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility */
    $scheduledTransitionsUtility = \Drupal::service('scheduled_transitions.utility');

    $result = $scheduledTransitionsUtility->getApplicableBundles();
    $this->assertEquals([], $result);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $result = $scheduledTransitionsUtility->getBundles();
    $this->assertEquals([], $result);

    $this->enabledBundles([['entity_test_revlog', 'entity_test_revlog']]);
    Cache::invalidateTags([SettingsForm::SETTINGS_TAG]);

    $result = $scheduledTransitionsUtility->getBundles();
    $this->assertEquals(['entity_test_revlog' => ['entity_test_revlog']], $result);
  }

}
