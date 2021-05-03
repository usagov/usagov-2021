<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\text\Plugin\Field\FieldWidget\TextfieldWidget;

/**
 * Plugin implementation of the 'mmf_text_textfield' widget.
 *
 * @FieldWidget(
 *   id = "mmf_text_textfield",
 *   label = @Translation("Text field MMF"),
 *   field_types = {
 *     "text"
 *   },
 * )
 */
class MmfTextfieldWidget extends TextfieldWidget {
  use MmfBase;
}
