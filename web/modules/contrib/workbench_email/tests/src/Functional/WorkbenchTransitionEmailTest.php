<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\node\NodeTypeInterface;
use Drupal\workbench_moderation\Entity\ModerationState;
use Drupal\workbench_moderation\Entity\ModerationStateTransition;

/**
 * Tests the view access control handler for moderation state entities.
 *
 * @group workbench_email
 */
class WorkbenchTransitionEmailTest extends WorkbenchEmailTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workbench_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function visitAdminMenuParent() {
    $this->drupalGet('admin/structure/workbench-moderation');
  }

  /**
   * {@inheritdoc}
   */
  protected function setupModerationForNodeType(NodeTypeInterface $node_type) {
    $node_type->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $states = array_keys(ModerationState::loadMultiple());
    $node_type->setThirdPartySetting('workbench_moderation', 'allowed_moderation_states', $states);
    $node_type->setThirdPartySetting('workbench_moderation', 'default_moderation_state', 'draft');
    $node_type->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function getApproverPermissions() {
    return [
      'view any unpublished content',
      'access content',
      'edit any test content',
      'create test content',
      'view test revisions',
      'edit any another content',
      'create another content',
      'view another revisions',
      'use draft_needs_review transition',
      'use needs_review_published transition',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditorPermissions() {
    return [
      'view any unpublished content',
      'access content',
      'edit any test content',
      'create test content',
      'view test revisions',
      'edit any another content',
      'create another content',
      'view another revisions',
      'use draft_needs_review transition',
      'use draft_draft transition',
      'use published_draft transition',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdminPermissions() {
    return [
      'administer moderation state transitions',
      'administer workbench_email templates',
      'access administration pages',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function enableTemplateForTransitionOrWorkflow($transition_name, $template_name) {
    $this->drupalGet('admin/structure/workbench-moderation/transitions/' . $transition_name);
    $field_name = 'workbench_email_templates[' . $template_name . ']';
    $this->submitForm([
      $field_name => TRUE,
    ], t('Save'));
    \Drupal::entityTypeManager()->getStorage('moderation_state_transition')->resetCache();
    $transition = ModerationStateTransition::load($transition_name);
    $this->assertEquals([$template_name => $template_name], $transition->getThirdPartySetting('workbench_email', 'workbench_email_templates', []));
    $this->drupalGet('admin/structure/workbench-moderation/transitions/' . $transition_name);
    $this->assertSession()->checkboxChecked($field_name);
    return $transition;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitButtonTitleForTransition($from_state, $to_state) {
    if ($from_state === 'published' && $to_state === 'draft') {
      return 'Create New Draft';
    }
    if ($from_state === 'draft' && $to_state === 'draft') {
      return 'Save and Create New Draft';
    }
    if ($from_state === 'draft' && $to_state === 'needs_review') {
      return 'Save and Request Review';
    }
    return 'Save and Publish';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalFieldsForTransition($from_state, $to_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedThirdPartySetting($transition_name, $template) {
    return [$template => $template];
  }

}
