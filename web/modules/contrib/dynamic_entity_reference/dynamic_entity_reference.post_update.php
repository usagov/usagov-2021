<?php

/**
 * @file
 * Post update functions for dynamic entity reference.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Only add dependencies to reference-able entity types in DER field storage.
 */
function dynamic_entity_reference_post_update_field_storage_dependencies() {
  /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');
  $map = $entity_field_manager->getFieldMapByFieldType('dynamic_entity_reference');
  foreach ($map as $entity_type_id => $info) {
    foreach ($info as $name => $data) {
      if ($field_storage = FieldStorageConfig::loadByName($entity_type_id, $name)) {
        // Re-saving the storage object will fix the dependencies.
        $field_storage->save();
      }
    }
  }
}

/**
 * Add config dependencies to reference-able entity types bundles in DER field.
 */
function dynamic_entity_reference_post_update_field_config_dependencies() {
  /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
  $entity_field_manager = \Drupal::service('entity_field.manager');
  $map = $entity_field_manager->getFieldMapByFieldType('dynamic_entity_reference');
  foreach ($map as $entity_type_id => $info) {
    foreach ($info as $name => $data) {
      if ($field_storage = FieldStorageConfig::loadByName($entity_type_id, $name)) {
        foreach ($field_storage->getBundles() as $bundle) {
          if ($field = FieldConfig::loadByName($field_storage->getTargetEntityTypeId(), $bundle, $field_storage->getName())) {
            // Re-saving the config object will fix the config dependencies.
            $field->save();
          }
        }
      }
    }
  }
}
