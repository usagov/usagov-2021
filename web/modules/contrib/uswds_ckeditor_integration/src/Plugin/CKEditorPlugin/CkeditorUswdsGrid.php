<?php

namespace Drupal\uswds_ckeditor_integration\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "uswds_grid" plugin.
 *
 * @CKEditorPlugin(
 *   id = "uswds_grid",
 *   label = @Translation("USWDS Grid")
 * )
 */
class CkeditorUswdsGrid extends CKEditorPluginBase implements CKEditorPluginCssInterface {

  /**
   * {@inheritdoc}
   */
  public function getButtons(): array {
    $path = drupal_get_path('module', 'uswds_ckeditor_integration') . '/js/plugins/uswds_grid';
    return [
      'uswds_grid' => [
        'label' => 'USWDS Grid',
        'image' => $path . '/icons/uswds_grid.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'uswds_ckeditor_integration') . '/js/plugins/uswds_grid/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor): array {
    return [
      'core/jquery',
      'core/drupal',
      'core/drupal.ajax',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor): array {
    $settings = $editor->getSettings();
    return $settings['plugins']['uswds_grid'] ?? [];
  }

}
