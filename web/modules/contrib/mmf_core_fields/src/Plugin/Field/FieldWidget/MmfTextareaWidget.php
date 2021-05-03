<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;

/**
 * Plugin implementation of the 'mmf_text_textfield' widget.
 *
 * @FieldWidget(
 *   id = "mmf_text_textarea",
 *   label = @Translation("Text area (multiple rows) MMF"),
 *   field_types = {
 *     "text_long"
 *   },
 * )
 */
class MmfTextareaWidget extends TextareaWidget {
  use MmfBase;
}
