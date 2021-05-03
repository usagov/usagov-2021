<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Traits;

use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Test trait helpers.
 */
trait ScheduledTransitionTestTrait {

  /**
   * Enable bundles for use with scheduled transitions.
   *
   * @param array $bundles
   *   Arrays of bundles. Where each bundle is an array containing:
   *    - 0: Entity type ID.
   *    - 1: Bundle ID.
   */
  protected function enabledBundles(array $bundles): void {
    $enabledBundles = [];
    foreach ($bundles as $bundle) {
      $enabledBundles[] = [
        'entity_type' => $bundle[0],
        'bundle' => $bundle[1],
      ];
    }
    \Drupal::configFactory()->getEditable('scheduled_transitions.settings')
      ->set('bundles', $enabledBundles)
      ->save(TRUE);
  }

  /**
   * Checks and runs any ready transitions.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   */
  protected function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    /** @var \Drupal\scheduled_transitions\ScheduledTransitionsRunnerInterface $runner */
    $runner = \Drupal::service('scheduled_transitions.runner');
    $runner->runTransition($scheduledTransition);
  }

}
