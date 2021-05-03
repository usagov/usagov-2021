<?php

namespace Drupal\address\Event;

/**
 * Defines events for the address module.
 */
final class AddressEvents {

  /**
   * Name of the event fired when altering an address format.
   *
   * @Event
   *
   * @see \Drupal\address\Event\AddressFormatEvent
   */
  const ADDRESS_FORMAT = 'address.address_format';

  /**
   * Name of the event fired when altering the list of available countries.
   *
   * @Event
   *
   * @see \Drupal\address\Event\AvailableCountriesEvent
   */
  const AVAILABLE_COUNTRIES = 'address.available_countries';

  /**
   * Name of the event fired when altering initial values.
   *
   * @deprecated in address:8.x-1.5 and is removed from address:8.x-2.0. Use
   *   hook_field_widget_form_alter() to change the address #default_value.
   *
   * @Event
   *
   * @see https://www.drupal.org/project/address/issues/2838457
   */
  const INITIAL_VALUES = 'address.widget.initial_values';

  /**
   * Name of the event fired when defining custom subdivisions.
   *
   * @Event
   *
   * @see \Drupal\address\Event\SubdivisionsEvent
   */
  const SUBDIVISIONS = 'address.subdivisions';

}
