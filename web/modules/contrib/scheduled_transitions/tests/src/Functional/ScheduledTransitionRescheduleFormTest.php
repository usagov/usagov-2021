<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestEntity;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests the route to reschedule a transition.
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionRescheduleForm
 */
class ScheduledTransitionRescheduleFormTest extends BrowserTestBase {

  use ContentModerationTestTrait;
  use ScheduledTransitionTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test_revlog',
    'scheduled_transitions_test',
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
  protected $defaultTheme = 'classy';

  /**
   * Test rescheduling.
   */
  public function testLatestOptionPreselected() {
    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $author = User::create([
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      Permissions::rescheduleScheduledTransitionsPermission('st_entity_test', 'st_entity_test'),
    ]);
    $this->drupalLogin($currentUser);

    $entity = ScheduledTransitionsTestEntity::create(['type' => 'st_entity_test']);
    $entity->save();

    $date = new \DateTime('2 Feb 2018 11am');
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition */
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => $entity->getRevisionId(),
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => 'published',
      'transition_on' => $date->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $this->drupalGet($scheduledTransition->toUrl('reschedule-form'));
    $this->assertSession()->fieldValueEquals('current_date[date]', '2018-02-02');
    $this->assertSession()->fieldValueEquals('current_date[time]', '11:00:00');
    $this->assertSession()->fieldValueEquals('date[date]', '2018-02-02');
    $this->assertSession()->fieldValueEquals('date[time]', '11:00:00');

    $edit = [
      'date[date]' => '2018-10-21',
      'date[time]' => '14:00:00',
    ];
    $this->drupalPostForm(NULL, $edit, 'Reschedule transition');
    $this->assertSession()->pageTextContains('Rescheduled transition for Sun, 10/21/2018 - 14:00');

    // Reload ST.
    $expectedDate = (new \DateTime('2018-10-21 2pm'))->getTimestamp();
    $scheduledTransition = ScheduledTransition::load($scheduledTransition->id());
    $this->assertEquals($scheduledTransition->getTransitionTime(), $expectedDate);
  }

}
