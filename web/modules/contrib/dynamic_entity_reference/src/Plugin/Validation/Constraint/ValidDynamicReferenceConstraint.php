<?php

namespace Drupal\dynamic_entity_reference\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Dynamic Entity Reference valid reference constraint.
 *
 * Verifies that referenced entities are valid.
 *
 * @Constraint(
 *   id = "ValidDynamicReference",
 *   label = @Translation("Dynamic Entity Reference valid reference", context = "Validation")
 * )
 */
class ValidDynamicReferenceConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'This entity (%type: %id) cannot be referenced.';

  /**
   * Violation message when the entity does not exist.
   *
   * @var string
   */
  public $nonExistingMessage = 'The referenced entity (%type: %id) does not exist.';

  /**
   * Violation message when a new entity ("autocreate") is invalid.
   *
   * @var string
   */
  public $invalidAutocreateMessage = 'This entity (%type: %label) cannot be referenced.';

  /**
   * The default violation message.
   *
   * @var string
   */
  public $wrongIDMessage = 'The referenced entity (%type: %id) does not exist.';

  /**
   * Validation message when the entity type is not supported.
   *
   * @var string
   */
  public $wrongTypeMessage = 'The referenced entity type (%type) is not allowed for this field.';

  /**
   * Validation message when bundle is not supported.
   *
   * @var string
   */
  public $wrongBundleMessage = 'Referenced entity %label does not belong to one of the supported bundles (%bundles).';

  /**
   * Validate message if no bundle is allowed.
   *
   * @var string
   */
  public $noBundleAllowed = 'No bundle is allowed for (%type)';

  /**
   * Validation message when the target_id or target_type is empty.
   *
   * @var string
   */
  public $nullMessage = '%property should not be null.';

}
