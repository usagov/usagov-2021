<?php

namespace Drupal\ckeditor_media_oembed_plugin\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Google Search" plugin.
 *
 * @CKEditorPlugin(
 *   id = "oembed",
 *   label = @Translation("Ckeditor oEmebed All"),
 *   module = "ckeditor_media_oembed_plugin"
 * )
 */
class Ckeditoroemebedall extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    if ($library_path = base_path() . 'libraries/oembed/oembed/') {
      return $library_path . '/plugin.js';
    }
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
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'oembed' => [
        'label' => t('oEmebed All'),
        'image' => base_path() . 'libraries/oembed/oembed/icons/oembed.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
