<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Access control handler for scheduled transitions.
 */
class ScheduledTransitionsAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    /** @var \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $entity */
    $access = parent::checkAccess($entity, $operation, $account);

    if ($access->isNeutral()) {
      $entity = $entity->getEntity();
      if ($entity) {
        // Defer access to associated entity.
        return $entity->access($operation, $account, TRUE);
      }
    }

    if ($operation === ScheduledTransitionInterface::ENTITY_OPERATION_RESCHEDULE) {
      $entity = $entity->getEntity();
      if ($entity) {
        // Defer access to associated entity.
        $access = $access->andIf($entity->access(ScheduledTransitionsPermissions::ENTITY_OPERATION_RESCHEDULE_TRANSITIONS, $account, TRUE));
      }
    }

    return $access;
  }

}
