<?php

namespace Drupal\ctools;


interface ConstraintConditionInterface {

  /**
   * Applies relevant constraints for this condition to the injected contexts.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *
   * @return null
   */
  public function applyConstraints(array $contexts = []);

  /**
   * Removes constraints for this condition from the injected contexts.
   *
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *
   * @return null
   */
  public function removeConstraints(array $contexts = []);

}
