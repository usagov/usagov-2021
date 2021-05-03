<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\workbench_email\Entity\Template;

/**
 * Defines a base class for workbench email tests.
 */
abstract class WorkbenchEmailTestBase extends BrowserTestBase {

  use AssertMailTrait;
  use NodeCreationTrait;
  use BlockCreationTrait;

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Test node type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * Approver role.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $approverRole;

  /**
   * Editor role.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $editorRole;

  /**
   * Approver 1.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver1;

  /**
   * Approver 2.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver2;

  /**
   * Approver 3 - blocked.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver3;

  /**
   * Approver 4 - no email address.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $approver4;

  /**
   * Editor.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor;

  /**
   * Admin.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $admin;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workbench_email',
    'workbench_email_test',
    'node',
    'options',
    'user',
    'system',
    'filter',
    'block',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Place some blocks.
    $this->placeBlock('local_tasks_block', ['id' => 'tabs_block']);
    $this->placeBlock('page_title_block');
    $this->placeBlock('local_actions_block', ['id' => 'actions_block']);
    // Create two node-types and make them moderated.
    $this->nodeType = NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ]);
    $this->setupModerationForNodeType($this->nodeType);
    $this->nodeType = NodeType::create([
      'type' => 'another',
      'name' => 'Another Test',
    ]);
    $this->setupModerationForNodeType($this->nodeType);
    // Create an approver role and two users.
    $this->approverRole = $this->drupalCreateRole($this->getApproverPermissions(), 'approver', 'Approver');
    $this->approver1 = $this->drupalCreateUser();
    $this->approver1->addRole('approver');
    $this->approver1->save();
    $this->approver2 = $this->drupalCreateUser();
    $this->approver2->addRole('approver');
    $this->approver2->save();
    $this->approver3 = $this->drupalCreateUser();
    $this->approver3->addRole('approver');
    $this->approver3->block();
    $this->approver3->save();
    $this->approver4 = $this->drupalCreateUser();
    $this->approver4->addRole('approver');
    $this->approver4->setEmail(NULL);
    $this->approver4->save();

    // Create a editor role and user.
    $this->editorRole = $this->drupalCreateRole($this->getEditorPermissions(), 'editor', 'Editor');
    $this->editor = $this->drupalCreateUser();
    $this->editor->addRole('editor');
    $this->editor->save();
    // Create an admin user.
    $this->admin = $this->drupalCreateUser($this->getAdminPermissions());
    // Add an email field notify to the node-type.
    FieldStorageConfig::create([
      'cardinality' => 1,
      'entity_type' => 'node',
      'field_name' => 'field_email',
      'type' => 'email',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_email',
      'bundle' => 'test',
      'label' => 'Notify',
      'entity_type' => 'node',
    ])->save();
    if (!$entity_form_display = EntityFormDisplay::load('node.test.default')) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => 'test',
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent('field_email', ['type' => 'email_default'])
      ->save();
  }

  /**
   * Enables moderation for a given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   Node type to enable moderation for.
   */
  abstract protected function setupModerationForNodeType(NodeTypeInterface $nodeType);

  /**
   * Gets approver permissions.
   *
   * @return array
   *   Permission names.
   */
  abstract protected function getApproverPermissions();

  /**
   * Gets editor permissions.
   *
   * @return array
   *   Permission names.
   */
  abstract protected function getEditorPermissions();

  /**
   * Gets permisisons for admin user.
   *
   * @return array
   *   Permission names.
   */
  abstract protected function getAdminPermissions();

  /**
   * Enables template for given transition or workflow.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Transition or workflow.
   */
  abstract protected function enableTemplateForTransitionOrWorkflow($transition_name, $template_name);

  /**
   * Get submit button title text for transition.
   *
   * @return string
   *   Button title.
   */
  abstract protected function getSubmitButtonTitleForTransition($from_state, $to_state);

  /**
   * Gets additional field data.
   *
   * @param string $from_state
   *   From state.
   * @param string $to_state
   *   To state.
   *
   * @return array
   *   Additional fields.
   */
  abstract protected function getAdditionalFieldsForTransition($from_state, $to_state);

  /**
   * Test administration.
   */
  public function testEndToEnd() {
    // Create some templates as admin.
    // - stuff got approved; and
    // - stuff needs review.
    $this->drupalLogin($this->admin);
    $this->visitAdminMenuParent();
    $page = $this->getSession()->getPage();
    $page->clickLink('Email Templates');
    $assert = $this->assertSession();
    $this->assertEquals($this->getSession()->getCurrentUrl(), Url::fromUri('internal:/admin/structure/workbench-moderation/workbench-email-template')->setOption('absolute', TRUE)->toString());
    $assert->pageTextContains('Email Template');
    $page->clickLink('Add Email Template');
    $this->submitForm([
      'id' => 'approved',
      'label' => 'Content approved',
      'body[value]' => 'Content with [node:field_does_not_exist]title [node:title] was approved. You can view it at [node:url].',
      'subject' => 'Content [node:field_does_not_exist]approved: [node:title][node:field_does_not_exist]',
      'enabled_recipient_types[author]' => TRUE,
      'enabled_recipient_types[email]' => TRUE,
      'enabled_recipient_types[role]' => TRUE,
      'recipient_types[email][settings][fields][node:field_email]' => TRUE,
      'recipient_types[role][settings][roles][editor]' => TRUE,
    ], t('Save'));
    $assert->pageTextContains('Created the Content approved Email Template');
    $page->clickLink('Add Email Template');
    $this->submitForm([
      'id' => 'needs_review',
      'label' => 'Content needs review',
      'body[value]' => 'Content with [node:field_does_not_exist]title [node:title] needs review. You can view it at [node:url].[node:field_does_not_exist]',
      'subject' => 'Content needs review',
      'replyTo' => '[node:author:mail]',
      'enabled_recipient_types[role]' => TRUE,
      'recipient_types[role][settings][roles][approver]' => TRUE,
      'bundles[node:test]' => TRUE,
    ], t('Save'));
    $assert->pageTextContains('Created the Content needs review Email Template');
    // Test dependencies.
    $approver = Template::load('needs_review');
    $dependencies = $approver->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('user.role.approver', $dependencies, TRUE));
    $this->assertTrue(in_array('node.type.test', $dependencies, TRUE));
    $approver = Template::load('approved');
    $dependencies = $approver->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('field.storage.node.field_email', $dependencies, TRUE));
    // Edit the template and test values persisted.
    $page->clickLink('Content approved');
    $assert->checkboxChecked('Notify (Content)');
    $this->getSession()->back();
    // Test editing a template.
    $page->clickLink('Content needs review');
    $assert->checkboxChecked('Approver', $page->find('css', '#edit-recipient-types-role-settings-roles--wrapper'));
    $this->submitForm([
      'label' => 'Content needs review',
      'body[value]' => 'Content with[node:field_does_not_exist] title [node:title] needs review. You can view it at [node:url].[node:field_does_not_exist]',
      'subject' => 'Content needs[node:field_does_not_exist] review: [node:title][node:field_does_not_exist]',
      'replyTo' => '[node:author:mail]',
    ], t('Save'));
    $assert->pageTextContains('Saved the Content needs review Email Template');
    // Edit the transition from needs review to published and use the
    // needs_review email template.
    $transitionOrWorkflow = $this->enableTemplateForTransitionOrWorkflow('needs_review_published', 'approved');
    $this->assertEquals($this->getExpectedThirdPartySetting('needs_review_published', 'approved'), $transitionOrWorkflow->getThirdPartySetting('workbench_email', 'workbench_email_templates'));
    $dependencies = $transitionOrWorkflow->calculateDependencies()->getDependencies()['config'];
    $this->assertTrue(in_array('workbench_email.workbench_email_template.approved', $dependencies, TRUE));
    // Edit the transition from draft to needs review and add email config:
    // approver template.
    $this->enableTemplateForTransitionOrWorkflow('draft_needs_review', 'needs_review');
    // Create a node and add to the notifier field.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/add/test');
    $this->submitForm([
        'title[0][value]' => 'Test node',
        'field_email[0][value]' => 'foo@example.com',
      ] + $this->getAdditionalFieldsForTransition('draft', 'draft'), $this->getSubmitButtonTitleForTransition('draft', 'draft'));
    $node = $this->getNodeByTitle('Test node');
    // Transition to needs review.
    $this->drupalGet('node/' . $node->id() . '/edit');
    // Reset collected email.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->submitForm([] + $this->getAdditionalFieldsForTransition('draft', 'needs_review'), $this->getSubmitButtonTitleForTransition('draft', 'needs_review'));
    $this->assertNeedsReviewNotifications($node);

    // Now try again going straight to needs review (no draft).
    // Reset collected email.
    $this->container->get('state')->set('system.test_mail_collector', []);
    // Create a node and add to the notifier field.
    $this->drupalGet('node/add/test');
    $this->submitForm([
        'title[0][value]' => 'Test node 2',
      ] + $this->getAdditionalFieldsForTransition('draft', 'needs_review'), $this->getSubmitButtonTitleForTransition('draft', 'needs_review'));
    $node2 = $this->getNodeByTitle('Test node 2');
    $this->assertNeedsReviewNotifications($node2);

    // Login as approver and transition to approved.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->drupalLogin($this->approver1);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([] + $this->getAdditionalFieldsForTransition('needs_review', 'published'), $this->getSubmitButtonTitleForTransition('needs_review', 'published'));
    // Check mail goes to author and notifier.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $last = end($captured_emails);
    $prev = prev($captured_emails);
    $mails = [$last['to'], $prev['to']];
    sort($mails);
    $expected = [$this->editor->getEmail(), 'foo@example.com'];
    sort($expected);
    $this->assertEquals($expected, $mails);

    // The node id text is added to the email subject in the
    // workbench_email_test_mail_alter() function.
    // We check that it is set here.
    $this->assertEquals(sprintf('Content approved: %s (node id: %s)', $node->getTitle(), $node->id()), $last['subject']);
    $this->assertEquals(sprintf('Content approved: %s (node id: %s)', $node->getTitle(), $node->id()), $prev['subject']);
    $this->assertStringContainsString(sprintf('Content with title %s was approved. You can view it at', $node->label()), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringContainsString(sprintf('Content with title %s was approved. You can view it at', $node->label()), preg_replace('/\s+/', ' ', $last['body']));
    // Check that empty tokens are removed.
    $this->assertStringNotContainsString('[node:field_does_not_exist]', preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringNotContainsString('[node:field_does_not_exist]', preg_replace('/\s+/', ' ', $last['body']));
    $this->assertStringContainsString($node->toUrl('canonical', ['absolute' => TRUE])->toString(), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringContainsString($node->toUrl('canonical', ['absolute' => TRUE])->toString(), preg_replace('/\s+/', ' ', $last['body']));

    // Test again with node that was previously published.
    // Log back in as editor.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([] + $this->getAdditionalFieldsForTransition('published', 'draft'), $this->getSubmitButtonTitleForTransition('published', 'draft'));
    // And now request a review.
    $this->drupalGet('node/' . $node->id() . '/edit');
    // Reset collected email.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->submitForm([] + $this->getAdditionalFieldsForTransition('draft', 'needs_review'), $this->getSubmitButtonTitleForTransition('draft', 'needs_review'));
    $this->assertNeedsReviewNotifications($node);

    // Try with the other node type, that isn't enabled.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->drupalGet('node/add/another');
    $this->submitForm([
        'title[0][value]' => 'Another test node',
      ]+ $this->getAdditionalFieldsForTransition('draft', 'draft'), $this->getSubmitButtonTitleForTransition('draft', 'draft'));
    $node = $this->getNodeByTitle('Another test node');
    // Transition to needs review.
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm([] + $this->getAdditionalFieldsForTransition('draft', 'needs_review'), $this->getSubmitButtonTitleForTransition('draft', 'needs_review'));
    // No mail should be sent.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    $this->assertEmpty($captured_emails);
  }

  /**
   * Visits admin parent menu link.
   */
  abstract protected function visitAdminMenuParent();

  /**
   * Gets expected third party settings.
   *
   * @param string $transition_name
   *   Transition name.
   * @param string $template
   *   Template ID.
   *
   * @return array
   *   Expected settings.
   */
  abstract protected function getExpectedThirdPartySetting($transition_name, $template);

  /**
   * Assert notifications sent for needs review.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node updated.
   */
  protected function assertNeedsReviewNotifications(NodeInterface $node) {
    // Check mail goes to approvers.
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector') ?: [];
    // Should only be two emails.
    $this->assertCount(2, $captured_emails);
    $last = end($captured_emails);
    $prev = prev($captured_emails);
    $mails = [$last['to'], $prev['to']];
    sort($mails);
    $expected = [$this->approver1->getEmail(), $this->approver2->getEmail()];
    sort($expected);
    $this->assertEquals($expected, $mails);

    // The node id text is added to the email subject in the
    // workbench_email_test_mail_alter() function.
    // We check that it is set here.
    $this->assertEquals(sprintf('Content needs review: %s (node id: %s)', $node->label(), $node->id()), preg_replace('/\s+/', ' ', $last['subject']));
    $this->assertEquals(sprintf('Content needs review: %s (node id: %s)', $node->label(), $node->id()), preg_replace('/\s+/', ' ', $prev['subject']));

    $this->assertEquals($this->editor->getEmail(), $last['reply-to']);
    $this->assertEquals($this->editor->getEmail(), $prev['reply-to']);
    $this->assertStringContainsString(sprintf('Content with title %s needs review. You can view it at', $node->label()), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringContainsString(sprintf('Content with title %s needs review. You can view it at', $node->label()), preg_replace('/\s+/', ' ', $last['body']));
    // Check that empty tokens are removed.
    $this->assertStringNotContainsString('[node:field_does_not_exist]', preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringNotContainsString('[node:field_does_not_exist]', preg_replace('/\s+/', ' ', $last['body']));
    $this->assertStringContainsString($node->toUrl('canonical', ['absolute' => TRUE])->toString(), preg_replace('/\s+/', ' ', $prev['body']));
    $this->assertStringContainsString($node->toUrl('canonical', ['absolute' => TRUE])->toString(), preg_replace('/\s+/', ' ', $last['body']));
  }

}
