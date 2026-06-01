<?php

declare(strict_types=1);

namespace Drupal\Tests\usa_workflow\Unit;

use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\usa_workflow\StateTransitionValidationDecorator;
use Drupal\usa_workflow\UsaWorkflowPolicy;
use Drupal\workflow_type_test\Plugin\WorkflowType\TestType;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Tests USA workflow transition validation decoration.
 *
 * @coversDefaultClass \Drupal\usa_workflow\StateTransitionValidationDecorator
 * @group usa_workflow
 */
class StateTransitionValidationDecoratorTest extends UnitTestCase {

  /**
   * Tests that publish transitions are filtered for restricted users.
   */
  public function testGetValidTransitionsFiltersRestrictedPublish(): void {
    $workflow_type = new TestType([], '', []);
    $workflow_type
      ->addState('draft', 'Draft')
      ->addState('published', 'Published')
      ->addTransition('create_new_draft', 'Create New Draft', ['draft'], 'draft')
      ->addTransition('publish', 'Publish', ['draft'], 'published');

    $draft_transition = $workflow_type->getState('draft')->getTransitionTo('draft');
    $publish_transition = $workflow_type->getState('draft')->getTransitionTo('published');

    $entity = $this->prophesize(ContentEntityInterface::class)->reveal();
    $user = $this->prophesize(AccountInterface::class)->reveal();

    $inner = $this->prophesize(StateTransitionValidationInterface::class);
    $inner->getValidTransitions($entity, $user)->willReturn([
      'create_new_draft' => $draft_transition,
      'publish' => $publish_transition,
    ]);

    $policy = $this->prophesize(UsaWorkflowPolicy::class);
    $policy->isSelfPublishRestricted($entity, $user, $draft_transition->to())->willReturn(FALSE);
    $policy->isSelfPublishRestricted($entity, $user, $publish_transition->to())->willReturn(TRUE);

    $decorator = new StateTransitionValidationDecorator($inner->reveal(), $policy->reveal());
    $transitions = $decorator->getValidTransitions($entity, $user);

    $this->assertArrayHasKey('create_new_draft', $transitions);
    $this->assertArrayNotHasKey('publish', $transitions);
  }

  /**
   * Tests that backend transition validation is denied when policy blocks it.
   */
  public function testIsTransitionValidDeniesRestrictedPublish(): void {
    $entity = $this->prophesize(ContentEntityInterface::class)->reveal();
    $user = $this->prophesize(AccountInterface::class)->reveal();
    $workflow = $this->prophesize(WorkflowInterface::class)->reveal();
    $original_state = $this->prophesize(StateInterface::class)->reveal();
    $new_state = $this->prophesize(StateInterface::class)->reveal();

    $inner = $this->prophesize(StateTransitionValidationInterface::class);
    $inner->isTransitionValid($workflow, $original_state, $new_state, $user, $entity)->willReturn(TRUE);

    $policy = $this->prophesize(UsaWorkflowPolicy::class);
    $policy->isSelfPublishRestricted($entity, $user, $new_state)->willReturn(TRUE);

    $decorator = new StateTransitionValidationDecorator($inner->reveal(), $policy->reveal());
    $this->assertFalse($decorator->isTransitionValid($workflow, $original_state, $new_state, $user, $entity));
  }

}
