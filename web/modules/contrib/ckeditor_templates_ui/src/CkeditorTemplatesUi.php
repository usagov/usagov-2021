<?php

namespace Drupal\ckeditor_templates_ui;

use Drupal\ckeditor_templates\Plugin\CKEditorPlugin\CkeditorTemplates;
use Drupal\editor\Entity\Editor;
use Drupal\Core\Form\FormStateInterface;

class CkeditorTemplatesUi extends CkeditorTemplates {

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config = [];
    $settings = $editor->getSettings();

    // Set replace content default value if set.
    if (isset($settings['plugins']['templates']['replace_content'])) {
      $config['templates_replaceContent'] = $settings['plugins']['templates']['replace_content'];
    }

    $config['templates_files'] = $this->getTemplatesDefaultPath();

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $form = parent::settingsForm($form, $form_state, $editor);

    // Disable template path option.
    $form['template_path']['#disabled'] = TRUE;
    $form['template_path']['#description'] .= '. ' . t('Note: This option will not work when CKeditor templates UI module is enabled.');

    return $form;
  }

  /**
   * Generate the path to the template file.
   *
   * The file will be picked from :
   * - the module js folder.
   *
   * @return array
   *   List of path to the template file.
   */
  private function getTemplatesDefaultPath() {
    global $base_path;
    return [$base_path . drupal_get_path('module', 'ckeditor_templates_ui') . '/js/ckeditor_templates.js'];
  }

}
