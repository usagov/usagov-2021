<?php

namespace Drupal\usagov_category_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'Hello' Block.
 */
#[Block(
  id: "usagov_category_search_filters",
  admin_label: new TranslatableMarkup("Hello block"),
  category: new TranslatableMarkup("Hello World")
)]
class CategoryFacetsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => $this->t('Hello, World!'),
    ];
  }

}
