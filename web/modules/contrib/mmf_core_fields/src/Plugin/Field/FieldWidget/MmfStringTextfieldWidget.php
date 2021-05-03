<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;

/**
 * Plugin implementation of the 'mmf_string_textfield' widget.
 *
 * @FieldWidget(
 *   id = "mmf_string_textfield",
 *   label = @Translation("Textfield MMF"),
 *   field_types = {
 *     "string"
 *   },
 * )
 */
class MmfStringTextfieldWidget extends StringTextfieldWidget {
  use MmfBase;
}
