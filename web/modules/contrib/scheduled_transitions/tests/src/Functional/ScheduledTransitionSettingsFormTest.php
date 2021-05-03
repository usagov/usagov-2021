<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\Core\Url;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\scheduled_transitions\Traits\ScheduledTransitionTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests settings form.
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionSettingsFormTest extends BrowserTestBase {

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
   * Tests automation/cron settings.
   */
  public function testAutomation(): void {
    $currentUser = $this->drupalCreateUser(['administer scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('scheduled_transitions.settings');
    $this->drupalGet($url);

    $this->assertSession()->checkboxChecked('cron_create_queue_items');

    $edit = [
      'cron_create_queue_items' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->checkboxNotChecked('cron_create_queue_items');
  }

  /**
   * Tests mirror operations settings.
   */
  public function testMirrorOperations(): void {
    // Set operation values to nothing.
    \Drupal::configFactory()
      ->getEditable('scheduled_transitions.settings')
      ->clear('mirror_operations.view scheduled transition')
      ->clear('mirror_operations.add scheduled transition')
      ->save(TRUE);

    $currentUser = $this->drupalCreateUser(['administer scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('scheduled_transitions.settings');
    $this->drupalGet($url);

    $this->assertSession()->fieldValueEquals('mirror_operation_view', '');
    $this->assertSession()->fieldValueEquals('mirror_operation_add', '');

    $edit = [
      'mirror_operation_view' => 'update',
      'mirror_operation_add' => 'update',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('mirror_operation_view', 'update');
    $this->assertSession()->fieldValueEquals('mirror_operation_add', 'update');
  }

  /**
   * Tests enabling bundles on form.
   */
  public function testEnabledBundles(): void {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $currentUser = $this->drupalCreateUser(['administer scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('scheduled_transitions.settings');
    $this->drupalGet($url);

    $this->assertSession()->pageTextContains('Enabled types (0)');
    $this->assertFieldByName('enabled[entity_test_revlog:entity_test_revlog]');
    $this->assertFieldByName('enabled[st_entity_test:st_entity_test]');

    // Enable just one of the boxes.
    $edit = [
      'enabled[st_entity_test:st_entity_test]' => 'TRUE',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->pageTextContains('Enabled types (1)');
    $this->assertSession()->checkboxNotChecked('enabled[entity_test_revlog:entity_test_revlog]');
    $this->assertSession()->checkboxChecked('enabled[st_entity_test:st_entity_test]');
  }

  /**
   * Tests permission message on form.
   */
  public function testNoPermissionsMessage(): void {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('st_entity_test', 'st_entity_test');
    $workflow->save();

    $this->enabledBundles([['st_entity_test', 'st_entity_test']]);

    $message = 'Notice: no roles are currently granted permissions for this type.';

    $currentUser = $this->drupalCreateUser(['administer scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('scheduled_transitions.settings');

    $this->drupalGet($url);
    $this->assertSession()->pageTextContains($message);

    $testRole = Role::create(['id' => 'test_role']);
    $testRole->grantPermission(Permissions::viewScheduledTransitionsPermission('st_entity_test', 'st_entity_test'));
    $testRole->save();

    $this->drupalGet($url);
    $this->assertSession()->pageTextNotContains($message);
  }

}
