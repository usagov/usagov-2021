<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDefaultWidget;

/**
 * Plugin implementation of the 'mmf_datetime_default' widget.
 *
 * @FieldWidget(
 *   id = "mmf_datetime_default",
 *   label = @Translation("Date and time MMF"),
 *   field_types = {
 *     "datetime"
 *   },
 * )
 */
class MmfDateTimeDefaultWidget extends DateTimeDefaultWidget {
  use MmfBase;
}
