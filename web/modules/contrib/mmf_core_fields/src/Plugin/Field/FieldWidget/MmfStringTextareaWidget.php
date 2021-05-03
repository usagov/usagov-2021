<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextareaWidget;

/**
 * Plugin implementation of the 'mmf_string_textarea' widget.
 *
 * @FieldWidget(
 *   id = "mmf_string_textarea",
 *   label = @Translation("Text area (multiple rows) MMF"),
 *   field_types = {
 *     "string_long"
 *   },
 * )
 */
class MmfStringTextareaWidget extends StringTextareaWidget {
  use MmfBase;
}
