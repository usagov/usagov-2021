<?php

namespace Drupal\address\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Country constraint.
 *
 * @Constraint(
 *   id = "Country",
 *   label = @Translation("Country", context = "Validation"),
 * )
 */
class CountryConstraint extends Constraint {

  /**
   * List of available countries.
   *
   * @var string[]
   */
  public $availableCountries = [];

  /**
   * Validation message if a country is invalid.
   *
   * @var string
   */
  public $invalidMessage = 'The country %value is not valid.';

  /**
   * Validation message if a country is not available.
   *
   * @var string
   */
  public $notAvailableMessage = 'The country %value is not available.';

}
