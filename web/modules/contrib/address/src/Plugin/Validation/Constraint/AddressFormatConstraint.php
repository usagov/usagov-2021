<?php

namespace Drupal\address\Plugin\Validation\Constraint;

use CommerceGuys\Addressing\Validator\Constraints\AddressFormatConstraint as ExternalAddressFormatConstraint;

/**
 * Address format constraint.
 *
 * @Constraint(
 *   id = "AddressFormat",
 *   label = @Translation("Address Format", context = "Validation"),
 *   type = { "address" }
 * )
 */
class AddressFormatConstraint extends ExternalAddressFormatConstraint {

  /**
   * Validation message if a field must be blank.
   *
   * @var string
   */
  public $blankMessage = '@name field must be blank.';

  /**
   * Validation message if a field is required.
   *
   * @var string
   */
  public $notBlankMessage = '@name field is required.';

  /**
   * Validation message if a field has an invalid format.
   *
   * @var string
   */
  public $invalidMessage = '@name field is not in the right format.';

}
