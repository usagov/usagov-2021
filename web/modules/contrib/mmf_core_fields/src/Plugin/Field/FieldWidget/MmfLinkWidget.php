<?php

namespace Drupal\mmf_core_fields\Plugin\Field\FieldWidget;

use Drupal\mmf_core_fields\Plugin\Field\FieldWidget\MmfBase;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Plugin implementation of the 'mmf_link' widget.
 *
 * @FieldWidget(
 *   id = "mmf_link",
 *   label = @Translation("Link MMF"),
 *   field_types = {
 *     "link"
 *   },
 * )
 */
class MmfLinkWidget extends LinkWidget {
  use MmfBase;
}
