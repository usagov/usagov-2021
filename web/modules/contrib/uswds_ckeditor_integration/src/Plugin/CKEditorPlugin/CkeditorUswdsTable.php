<?php

namespace Drupal\uswds_ckeditor_integration\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;
use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;

/**
 * Defines the "uswds_table" plugin.
 *
 * @CKEditorPlugin(
 *   id = "uswds_table",
 *   label = @Translation("CKEditor USWDS table"),
 *   module = "uswds_ckeditor_integration"
 * )
 */
class CkeditorUswdsTable extends CKEditorPluginBase implements CKEditorPluginInterface, CKEditorPluginContextualInterface, CKEditorPluginConfigurableInterface {

  /**
   * Get path to plugin folder.
   */
  public function getPluginPath() {
    return drupal_get_path('module', 'uswds_ckeditor_integration') . '/js/plugins/uswds_table';
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getFile().
   */
  public function getFile() {
    return $this->getPluginPath() . '/plugin.js';
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
  public function isEnabled(Editor $editor) {

    if (!$editor->hasAssociatedFilterFormat()) {
      return FALSE;
    }

    // Automatically enable this plugin if Table button is enabled.
    $settings = $editor->getSettings();

    $checked = isset($settings['plugins']['uswds_table']['override_table']) ? $settings['plugins']['uswds_table']['override_table'] : FALSE;

    if (!empty($settings) && $checked) {
      foreach ($settings['toolbar']['rows'] as $row) {
        foreach ($row as $group) {
          foreach ($group['items'] as $button) {
            if ($button === 'Table') {
              return TRUE;
            }
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {

    $settings = $editor->getSettings();

    $form['override_table'] = [
      '#title' => 'Override table plugin with USWDS table.',
      '#type' => 'checkbox',
      '#default_value' => isset($settings['plugins']['uswds_table']['override_table']) ? $settings['plugins']['uswds_table']['override_table'] : '',
      '#description' => $this->t('Check to override the table plugin with USWDS attributes.'),
    ];

    $form['#attached']['library'][] = 'uswds_ckeditor_integration/ckeditor.uswds.table.admin';

    return $form;
  }

}
