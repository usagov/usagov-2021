<?php

namespace Drupal\usa_workflow;

use Drupal\content_moderation\StateTransitionValidation;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\StateInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Validates whether a certain state transition is allowed.
 */
class OwnerStateTransitionValidation extends StateTransitionValidation implements StateTransitionValidationInterface {

  /**
   * {@inheritdoc}
   */
  public function getValidTransitions(ContentEntityInterface $entity, AccountInterface $user) {
    // Check if entity is owned by user and user has required permissions.
    $valid_transitions = parent::getValidTransitions($entity, $user);
    if ($entity->hasField('uid') && $entity->get('uid')->entity->id() == $user->id()) {
      if (!$user->hasPermission('usa approve own content')) {
        unset($valid_transitions['approve']);
      }
    }
    return $valid_transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function isTransitionValid(WorkflowInterface $workflow, StateInterface $original_state, StateInterface $new_state, AccountInterface $user, ContentEntityInterface $entity = NULL) {
    $transition = $workflow->getTypePlugin()->getTransitionFromStateToState($original_state->id(), $new_state->id());
    if ($transition->id() == 'approve' && $entity->hasField('uid') && $entity->get('uid')->entity->id() == $user->id()) {
      return $user->hasPermission('usa approve own content');
    }
    return parent::isTransitionValid($workflow, $original_state, $new_state, $user, $entity);
  }

}
