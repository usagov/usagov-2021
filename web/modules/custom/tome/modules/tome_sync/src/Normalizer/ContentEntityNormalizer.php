<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\serialization\Normalizer\ContentEntityNormalizer as BaseContentEntityNormalizer;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 *
 * @internal
 */
class ContentEntityNormalizer extends BaseContentEntityNormalizer {

  /**
   * Field names that should be excluded from normalization.
   *
   * Should only be used when more generic logic cannot be used.
   *
   * @var array
   */
  protected $fieldDenyList = [
    'metatag',
  ];

  /**
   * Entity type properties that should be excluded from normalization.
   *
   * Should only be used when more generic logic cannot be used.
   *
   * @var array
   */
  protected $entityTypeDenyList = [
    'entity_revision_parent_type_field',
    'entity_revision_parent_id_field',
    'entity_revision_parent_field_name_field',
  ];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $values = parent::normalize($entity, $format, $context);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity_type = $entity->getEntityType();
    $id_key = $entity_type->getKey('id');
    // User IDs are important to retain to ensure that User 1 is consistent
    // across rebuilds. We unset the ID key otherwise to make merging easier.
    // Imagine fighting over the next Node ID with an upstream repo! Yuck.
    if ($entity_type->id() !== 'user' && $id_key && isset($values[$id_key])) {
      unset($values[$id_key]);
    }
    $id_key = $entity_type->getKey('revision');
    if ($id_key && isset($values[$id_key])) {
      unset($values[$id_key]);
    }
    foreach ($this->fieldDenyList as $field_name) {
      if (isset($values[$field_name])) {
        unset($values[$field_name]);
      }
    }
    foreach ($this->entityTypeDenyList as $key) {
      $field_name = $entity->getEntityType()->get($key);
      if ($field_name && isset($values[$field_name])) {
        unset($values[$field_name]);
      }
    }
    return $values;
  }

}
