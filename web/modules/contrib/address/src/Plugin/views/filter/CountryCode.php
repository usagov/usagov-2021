<?php

namespace Drupal\address\Plugin\views\filter;

/**
 * Filter by country.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("country_code")
 *
 * @deprecated in address:8.x-1.5 and is removed from address:8.x-2.0. Use the
 *   Country plugin instead.
 *
 * @see https://www.drupal.org/project/address/issues/3034122
 */
class CountryCode extends Country {}
