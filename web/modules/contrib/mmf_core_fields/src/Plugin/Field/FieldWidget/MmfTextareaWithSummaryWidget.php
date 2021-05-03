<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWithSummaryWidget;

/**
 * Plugin implementation of the 'mmf_text_textarea_with_summary' widget.
 *
 * @FieldWidget(
 *   id = "mmf_text_textarea_with_summary",
 *   label = @Translation("Text area with a summary MMF"),
 *   field_types = {
 *     "text_with_summary"
 *   },
 * )
 */
class MmfTextareaWithSummaryWidget extends TextareaWithSummaryWidget {
  use MmfBase;
}
