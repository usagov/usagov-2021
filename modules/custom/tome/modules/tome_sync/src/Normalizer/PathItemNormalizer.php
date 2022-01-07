<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer as BaseFieldItemNormalizer;

/**
 * Normalizer for Path fields.
 *
 * @internal
 */
class PathItemNormalizer extends BaseFieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = '\Drupal\path\Plugin\Field\FieldType\PathItem';

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $values = parent::normalize($object, $format, $context);
    if (isset($values['pid'])) {
      unset($values['pid']);
    }
    return $values;
  }

}
