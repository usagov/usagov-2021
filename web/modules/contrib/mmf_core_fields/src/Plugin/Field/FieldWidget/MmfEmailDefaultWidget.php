<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EmailDefaultWidget;

/**
 * Plugin implementation of the 'mmf_email_default' widget.
 *
 * @FieldWidget(
 *   id = "mmf_email_default",
 *   label = @Translation("Email MMF"),
 *   field_types = {
 *     "email"
 *   },
 * )
 */
class MmfEmailDefaultWidget extends EmailDefaultWidget {
  use MmfBase;
}
