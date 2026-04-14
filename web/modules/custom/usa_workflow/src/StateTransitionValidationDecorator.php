<?php

namespace Drupal\usa_workflow;

use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Decorates core moderation transition checks with USA workflow policy.
 */
class StateTransitionValidationDecorator implements StateTransitionValidationInterface {

  public function __construct(
    private StateTransitionValidationInterface $inner,
    private UsaWorkflowPolicy $policy,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user): array {
    $transitions = $this->inner->getValidTransitions($entity, $user);

    return array_filter(
      $transitions,
      function ($transition) use ($entity, $user) {
        return !$this->policy->isSelfPublishRestricted($entity, $user, $transition->to());
      },
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(
    WorkflowInterface $workflow,
    StateInterface $original_state,
    StateInterface $new_state,
    AccountInterface $user,
    ContentEntityInterface $entity,
  ): bool {
    if (!$this->inner->isTransitionValid($workflow, $original_state, $new_state, $user, $entity)) {
      return FALSE;
    }

    return !$this->policy->isSelfPublishRestricted($entity, $user, $new_state);
  }

}
