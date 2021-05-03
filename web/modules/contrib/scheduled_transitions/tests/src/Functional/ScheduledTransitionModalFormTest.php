<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestEntity;
use Drupal\scheduled_transitions_test\Entity\ScheduledTransitionsTestNoRevisionTemplateEntity;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;

/**
 * Tests the route to add a new transition to an entity (modal form).
 *
 * @group scheduled_transitions
 * @coversDefaultClass \Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionAddForm
 */
class ScheduledTransitionModalFormTest extends BrowserTestBase {

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
   * Tests revision logs.
   */
  public function testRevisionLogHtml() {
    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      Permissions::addScheduledTransitionsPermission('st_entity_test', 'st_entity_test'),
    ]);
    $this->drupalLogin($currentUser);

    $entity = ScheduledTransitionsTestEntity::create(['type' => 'st_entity_test']);
    $logMessage = '<strong>Hello world</strong>';
    $entity->setRevisionLogMessage($logMessage);
    $entity->save();

    $this->drupalGet($entity->toUrl());
    // Access the modal directly.
    $this->drupalGet($entity->toUrl(ScheduledTransitionsRouteProvider::LINK_TEMPLATE_ADD));

    // Check if the log message exists in HTML verbatim, the HTML tags should
    // not be entity encoded.
    $this->assertSession()->responseContains($logMessage);
  }

  /**
   * Test using an entity without a revision link template.
   */
  public function testNoRevisionLinkTemplate() {
    $this->enabledBundles([['st_entity_test_norevtemplate', 'st_entity_test_norevtemplate']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test_norevtemplate', 'st_entity_test_norevtemplate');
    $workflow->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test_norevtemplate entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      'add scheduled transitions st_entity_test_norevtemplate st_entity_test_norevtemplate',
    ]);
    $this->drupalLogin($currentUser);

    $entity = ScheduledTransitionsTestNoRevisionTemplateEntity::create(['type' => 'st_entity_test_norevtemplate']);
    $entity->save();

    // Need to create an extra revision, because out of the box a freshly
    // created entity will have only one entity, and the toUrl method will
    // shortcut out, changing rel to 'canonical'.
    $entity->setNewRevision(TRUE);
    $entity->setRevisionLogMessage('test rev log msg');
    $entity->save();

    // Ensure the entity wasn't updated with a 'revision' link template.
    $this->assertFalse($entity->getEntityType()->hasLinkTemplate('revision'));

    // Access the modal directly.
    $this->drupalGet($entity->toUrl(ScheduledTransitionsRouteProvider::LINK_TEMPLATE_ADD));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test by default the latest revision radio is prechecked.
   */
  public function testLatestOptionPreselected() {
    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $currentUser = $this->drupalCreateUser([
      'administer st_entity_test entities',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
      Permissions::addScheduledTransitionsPermission('st_entity_test', 'st_entity_test'),
    ]);
    $this->drupalLogin($currentUser);

    // Create another revision so the list has more options than only the
    // 'latest' radio.
    $entity = ScheduledTransitionsTestEntity::create(['type' => 'st_entity_test']);
    $entity->setNewRevision();
    $entity->save();
    $revisionId = $entity->getRevisionId();

    $this->drupalGet($entity->toUrl());
    // Access the modal directly.
    $this->drupalGet($entity->toUrl(ScheduledTransitionsRouteProvider::LINK_TEMPLATE_ADD));

    // The revision should be listed.
    $this->assertSession()->checkboxNotChecked('edit-revision-' . $revisionId);
    // The latest radio should exist and checked.
    $this->assertSession()->checkboxChecked('edit-revision-latest-revision');
  }

}
