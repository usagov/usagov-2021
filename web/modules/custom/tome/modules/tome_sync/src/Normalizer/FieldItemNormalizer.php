<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\serialization\Normalizer\FieldItemNormalizer as BaseFieldItemNormalizer;

/**
 * Normalizer for fields.
 *
 * @internal
 */
class FieldItemNormalizer extends BaseFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $values = parent::normalize($object, $format, $context);
    if ($object instanceof ComplexDataInterface) {
      foreach ($object->getDataDefinition()->getPropertyDefinitions() as $name => $property) {
        // Remove computed properties from the normalized file to reduce noise.
        if ($property->isComputed() && isset($values[$name])) {
          unset($values[$name]);
        }
      }
    }
    return $values;
  }

}
