<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Entity\EntityInterface;

/**
 * Utilities for Scheduled Transitions module.
 */
interface ScheduledTransitionsUtilityInterface {

  /**
   * Get scheduled transitions for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[]
   *   An array of scheduled transitions.
   */
  public function getTransitions(EntityInterface $entity): array;

  /**
   * Get list of entity type/bundles scheduled transitions can work with.
   *
   * @return array
   *   Arrays of bundles keyed by entity type.
   */
  public function getApplicableBundles(): array;

  /**
   * Get list of entity type/bundles scheduled transitions are enabled on.
   *
   * @return array
   *   Arrays of bundles keyed by entity type.
   */
  public function getBundles(): array;

}
