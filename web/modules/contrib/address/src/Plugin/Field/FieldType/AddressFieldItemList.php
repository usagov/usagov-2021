<?php

namespace Drupal\address\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'address' field item list class.
 */
class AddressFieldItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function defaultValuesFormValidate(array $element, array &$form, FormStateInterface $form_state) {
    // Skip validation on the default value form.
    // This allows setting an incomplete address as the default value.
  }

}
