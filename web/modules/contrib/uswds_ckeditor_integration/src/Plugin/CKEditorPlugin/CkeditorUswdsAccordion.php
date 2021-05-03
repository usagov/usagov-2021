<?php

namespace Drupal\uswds_ckeditor_integration\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "CKEditorUswdsAccordion" plugin.
 *
 * @CKEditorPlugin (
 *   id = "uswds_accordion",
 *   label = @Translation("CkeditorUswdsAccordion"),
 *   module = "uswds_ckeditor_integration"
 * )
 */
class CkeditorUswdsAccordion extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config = [];
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'uswds_ckeditor_integration') . '/js/plugins/uswds_accordion/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = drupal_get_path('module', 'uswds_ckeditor_integration') . '/js/plugins/uswds_accordion/icons';
    return [
      'Accordion' => [
        'label' => 'Add Accordion',
        'image' => $path . '/accordion.png',
      ],
    ];
  }

}
