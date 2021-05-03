<?php

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;

/**
 * Plugin implementation of the 'options_select' widget.
 *
 * @FieldWidget(
 *   id = "dynamic_entity_reference_options_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "dynamic_entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class DynamicEntityReferenceOptionsSelectWidget extends OptionsSelectWidget {

  use DynamicEntityReferenceOptionsTrait;

  /**
   * {@inheritdoc}
   */
  public function supportsGroups() {
    $settings = $this->getFieldSettings();
    $entity_type_id = current($settings['entity_type_ids']);

    // We only support groups when there is more than 1 target_bundle
    // available.
    return !empty($settings[$entity_type_id]['handler_settings']['target_bundles']) && count($settings[$entity_type_id]['handler_settings']['target_bundles']) > 1;
  }

}
