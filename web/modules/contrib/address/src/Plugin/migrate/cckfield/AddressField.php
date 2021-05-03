<?php

namespace Drupal\address\Plugin\migrate\cckfield;

@trigger_error('AddressField is deprecated in address:8.x-1.3 and is removed from address:8.x-2.0. Use \Drupal\address\Plugin\migrate\field\AddressField instead. See https://www.drupal.org/project/address/issues/2912087', E_USER_DEPRECATED);

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\address\Plugin\migrate\field\AddressField as BaseAddressField;
use Drupal\migrate_drupal\Plugin\MigrateCckFieldInterface;

/**
 * Cck migration field.
 *
 * @MigrateCckField(
 *   id = "addressfield",
 *   core = {7},
 *   type_map = {
 *    "addressfield" = "address"
 *   },
 *   source_module = "addressfield",
 *   destination_module = "address"
 * )
 *
 * @deprecated in address:8.x-1.3 and is removed from address:8.x-2.0. Use
 *   \Drupal\address\Plugin\migrate\field\AddressField instead.
 *
 * @see https://www.drupal.org/project/address/issues/2912087
 */
class AddressField extends BaseAddressField implements MigrateCckFieldInterface {

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    return $this->processFieldValues($migration, $field_name, $data);
  }

}
