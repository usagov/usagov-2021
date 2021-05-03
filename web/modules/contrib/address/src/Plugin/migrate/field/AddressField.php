<?php

namespace Drupal\address\Plugin\migrate\field;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Field migration plugin from D7 to D8.
 *
 * @MigrateField(
 *   id = "addressfield",
 *   core = {7},
 *   type_map = {
 *    "addressfield" = "address"
 *   },
 *   source_module = "addressfield",
 *   destination_module = "address"
 * )
 */
class AddressField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'addressfield_default' => 'address_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'addressfield_standard' => 'address_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineValueProcessPipeline(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'addressfield',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldValues(MigrationInterface $migration, $field_name, $data) {
    $this->defineValueProcessPipeline($migration, $field_name, $data);
  }

}
