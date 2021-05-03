<?php

namespace Drupal\address\Plugin\diff\Field;

use Drupal\diff\Plugin\diff\Field\CoreFieldBuilder;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin to compare address for address fields.
 *
 * @FieldDiffBuilder(
 *   id = "address_field_diff_builder",
 *   label = @Translation("Address Field Diff"),
 *   field_types = {
 *     "address"
 *   },
 * )
 */
class AddressFieldBuilder extends CoreFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];

    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $value = $field_item->view(['label' => 'hidden', 'type' => 'address_plain']);
        $rendered_value = $this->renderer->renderPlain($value);
        $result[$field_key][] = $rendered_value;
      }
    }

    return $result;
  }

}
