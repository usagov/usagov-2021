<?php

namespace Drupal\address\Plugin\views\field;

/**
 * Allows the country name to be displayed instead of the country code.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("country_code")
 *
 * @deprecated in address:8.x-1.5 and is removed from address:8.x-2.0. Use the
 *   Country plugin instead.
 *
 * @see https://www.drupal.org/project/address/issues/3034122
 */
class CountryCode extends Country {}
