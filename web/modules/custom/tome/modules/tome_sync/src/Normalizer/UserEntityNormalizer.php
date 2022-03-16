<?php

namespace Drupal\tome_sync\Normalizer;

/**
 * Normalizes/denormalizes Drupal user entities into an array structure.
 *
 * @internal
 */
class UserEntityNormalizer extends ContentEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\user\UserInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $values = parent::normalize($entity, $format, $context);
    $excluded_user_keys = [
      'access',
      'login',
      'init',
    ];
    $values = array_diff_key($values, array_flip($excluded_user_keys));
    return $values;
  }

}
