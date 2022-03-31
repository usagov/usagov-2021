<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * Normalizer for entity reference fields.
 *
 * @internal
 */
class EntityReferenceItemNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = parent::normalize($field_item, $format, $context);
    if (isset($values['target_id']) && is_numeric($values['target_id'])) {
      unset($values['target_id']);
    }
    if (isset($values['url'])) {
      unset($values['url']);
    }
    return $values;
  }

}
