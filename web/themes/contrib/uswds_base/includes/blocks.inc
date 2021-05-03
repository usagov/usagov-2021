<?php

/**
 * @file
 * Utility code related to menus and menu blocks.
 */

use Drupal\block\Entity\Block;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function uswds_base_theme_suggestions_block_alter(array &$suggestions, array $variables) {
  // Set the block variable.
  $block = '';
  if (!empty($variables['elements']['#id'])) {
    $block = Block::load($variables['elements']['#id']);
  }

  if (!empty($block)) {
    // We need to suggest our custom theming for menu blocks in certain regions.
    if (in_array('block__system_menu_block', $suggestions)) {
      $menu_regions =
        [
          'primary_menu',
          'secondary_menu',
          'mobile_menu',
          'sidebar_first',
          'sidebar_second',
          'footer_menu',
        ];
      if (in_array($block->getRegion(), $menu_regions)) {
        if (_uswds_base_process_menu_region($block->getRegion())) {
          $suggestions[] = 'block__system_menu_block__' . $block->getRegion();
        }
      }
    }

    // We need to suggest our custom theming for menu blocks in certain regions.
    if (in_array('block__block_content', $suggestions)) {
      $bc_regions = ['hero'];
      if (in_array($block->getRegion(), $bc_regions)) {
        $suggestions[] = 'block__block_content__' . $block->getRegion();
      }
    }
  }
  return $suggestions;
}

/**
 * Helper: mark block-content items as being in one of our block regions.
 *
 * This is the way we communicate a content block's region to its preprocessor
 * and template.
 *
 * @see preprocess/uswds_base_preprocess_block__block_content__*
 */
function _uswds_base_mark_block_content_items(&$variables, $region) {
  if (!empty($variables['content'])) {
    $variables['content']['#uswds_base_region'] = $region;
  }
}
