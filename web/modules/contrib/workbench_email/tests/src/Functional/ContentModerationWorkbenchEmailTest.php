<?php

namespace Drupal\Tests\workbench_email\Functional;

use Drupal\node\NodeTypeInterface;
use Drupal\workflows\Entity\Workflow;

/**
 * Defines a class for testing workbench email with content moderation.
 *
 * @group workbench_email
 */
class ContentModerationWorkbenchEmailTest extends WorkbenchEmailTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setupModerationForNodeType(NodeTypeInterface $node_type) {
    $node_type->save();
    $typeSettings = [
      'states' => [
        'archived' => [
          'label' => 'Archived',
          'weight' => 5,
          'published' => FALSE,
          'default_revision' => TRUE,
        ],
        'draft' => [
          'label' => 'Draft',
          'published' => FALSE,
          'default_revision' => FALSE,
          'weight' => -5,
        ],
        'needs_review' => [
          'label' => 'Needs review',
          'published' => FALSE,
          'default_revision' => FALSE,
          'weight' => -4,
        ],
        'published' => [
          'label' => 'Published',
          'published' => TRUE,
          'default_revision' => TRUE,
          'weight' => 0,
        ],
      ],
      'transitions' => [
        'archive' => [
          'label' => 'Archive',
          'from' => ['published'],
          'to' => 'archived',
          'weight' => 2,
        ],
        'archived_draft' => [
          'label' => 'Restore to Draft',
          'from' => ['archived'],
          'to' => 'draft',
          'weight' => 3,
        ],
        'draft_needs_review' => [
          'label' => 'Request Review',
          'from' => ['draft'],
          'to' => 'needs_review',
          'weight' => 3,
        ],
        'archived_published' => [
          'label' => 'Restore',
          'from' => ['archived'],
          'to' => 'published',
          'weight' => 4,
        ],
        'create_new_draft' => [
          'label' => 'Create New Draft',
          'to' => 'draft',
          'weight' => 0,
          'from' => [
            'draft',
            'published',
            'needs_review',
          ],
        ],
        'needs_review_published' => [
          'label' => 'Publish',
          'to' => 'published',
          'weight' => 1,
          'from' => [
            'needs_review',
            'draft',
            'published',
          ],
        ],
      ],
    ];
    if (!($workflow = Workflow::load('editorial'))) {
      $workflow = Workflow::create([
        'type' => 'content_moderation',
        'id' => 'editorial',
        'label' => 'Editorial',
        'type_settings' => $typeSettings,
      ]);
    }
    else {
      if ($node_type->id() === 'test') {
        // Only do this the first time around.
        $workflow->getTypePlugin()->setConfiguration($typeSettings);
      }
    }
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', $node_type->id());
    $workflow->save();
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
      'use editorial transition draft_needs_review',
      'use editorial transition needs_review_published',
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
      'use editorial transition draft_needs_review',
      'use editorial transition create_new_draft',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdminPermissions() {
    return [
      'administer workflows',
      'administer workbench_email templates',
      'access administration pages',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function enableTemplateForTransitionOrWorkflow($transition_name, $template_name) {
    $this->drupalGet('admin/config/workflow/workflows/manage/editorial');
    $this->drupalGet('admin/config/workflow/workflows/manage/editorial/transition/' . $transition_name);
    $field_name = 'workbench_email_templates[' . $template_name . ']';
    $this->submitForm([
      $field_name => TRUE,
    ], t('Save'));
    $entityStorage = \Drupal::entityTypeManager()->getStorage('workflow');
    $entityStorage->resetCache();
    $workflow = $entityStorage->load('editorial');
    $this->drupalGet('admin/config/workflow/workflows/manage/editorial/transition/' . $transition_name);
    $this->assertSession()->checkboxChecked($field_name);
    return $workflow;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitButtonTitleForTransition($from_state, $to_state) {
    return 'Save';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdditionalFieldsForTransition($from_state, $to_state) {
    return ['moderation_state[0][state]' => $to_state];
  }

  /**
   * {@inheritdoc}
   */
  protected function visitAdminMenuParent() {
    $this->drupalGet('admin/config/workflow');
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedThirdPartySetting($transition_name, $template) {
    return [$transition_name => [$template => $template]];
  }

}
