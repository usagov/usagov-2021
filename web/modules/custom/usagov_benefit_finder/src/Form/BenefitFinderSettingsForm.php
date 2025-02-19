<?php

namespace Drupal\usagov_benefit_finder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class BenefitFinderSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'usagov_benefit_finder_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['automate_json_data_file_generating'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automate JSON data file generating'),
      '#description' => $this->t("If checked, automate JSON data file generating when Benefit Finder content changes."),
      '#return_value' => TRUE,
      '#default_value' => $this->config('usagov_benefit_finder.settings')
        ->get('automate_json_data_file_generating'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('usagov_benefit_finder.settings')
      ->set('automate_json_data_file_generating', $form_state->getValue('automate_json_data_file_generating'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'usagov_benefit_finder.settings',
    ];
  }

}
