<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget;

/**
 * Plugin implementation of the 'mmf_number' widget.
 *
 * @FieldWidget(
 *   id = "mmf_number",
 *   label = @Translation("Number field MMF"),
 *   field_types = {
 *     "integer",
 *     "decimal",
 *     "float"
 *   },
 * )
 */
class MmfNumberWidget extends NumberWidget {
  use MmfBase;
}
