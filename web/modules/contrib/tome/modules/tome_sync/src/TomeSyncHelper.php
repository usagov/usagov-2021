<?php

namespace Drupal\tome_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * Provides helpers for the Tome Sync module.
 *
 * @internal
 */
class TomeSyncHelper {

  /**
   * Gets the content name for a given entity.
   *
   * This can be used to read/write from the tome_sync.storage.content service.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return string
   *   A string representing the content name.
   */
  public static function getContentName(EntityInterface $entity) {
    if ($entity instanceof TranslatableInterface && !$entity->isDefaultTranslation()) {
      return "{$entity->getEntityTypeId()}.{$entity->uuid()}.{$entity->language()->getId()}";
    }
    else {
      return "{$entity->getEntityTypeId()}.{$entity->uuid()}";
    }
  }

  /**
   * Gets the content name based on parts.
   *
   * This can be used to read/write from the tome_sync.storage.content service.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $uuid
   *   The entity UUID.
   * @param string $langcode
   *   (optional) The langcode, for translations.
   *
   * @return string
   *   A string representing the content name.
   */
  public static function getContentNameFromParts($entity_type_id, $uuid, $langcode = NULL) {
    if ($langcode) {
      return "$entity_type_id.$uuid.$langcode";
    }
    else {
      return "$entity_type_id.$uuid";
    }
  }

  /**
   * Gets the parts from a content name.
   *
   * @param string $name
   *   A content name.
   *
   * @return array
   *   A 3-tuple in the format [entity_type_id, uuid, langcode].
   */
  public static function getPartsFromContentName($name) {
    $parts = explode('.', $name);
    return [
      $parts[0],
      $parts[1],
      isset($parts[2]) ? $parts[2] : NULL,
    ];
  }

}
