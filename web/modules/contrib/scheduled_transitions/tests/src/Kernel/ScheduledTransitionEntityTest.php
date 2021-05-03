<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;

/**
 * Tests scheduled transition entity.
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\Entity\ScheduledTransition
 */
class ScheduledTransitionEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'scheduled_transitions',
    'dynamic_entity_reference',
    'content_moderation',
    'workflows',
    'field',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('scheduled_transition');
  }

  /**
   * Tests getEntityRevisionLanguage method.
   *
   * @covers ::getEntityRevisionLanguage
   */
  public function testScheduledRevision() {
    $langCode = 'foobar';
    $scheduledTransition = ScheduledTransition::create([
      'entity_revision_langcode' => $langCode,
    ]);
    $this->assertEquals($langCode, $scheduledTransition->getEntityRevisionLanguage());
  }

}
