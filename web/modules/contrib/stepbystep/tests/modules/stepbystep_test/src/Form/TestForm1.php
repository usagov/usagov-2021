<?php

namespace Drupal\stepbystep_test\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for testing Step by Step.
 */
class TestForm1 extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stepbystep_test_form_1';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['stepbystep_test.config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $parameter1 = NULL) {
    $config = $this->config('stepbystep_test.config');
    $config_value_dump = ' textfield1 ' . $config->get('textfield1')
                       . ' textfield2 ' . $config->get('textfield2')
                       . ' textfield3 ' . $config->get('textfield3')
                       . ' textfield4 ' . $config->get('textfield4');
    $form['current_config'] = [
      '#markup' => $config_value_dump,
    ];
    $form['parameter1'] = [
      '#markup' => '<p>Parameter 1 ' . $parameter1 . '</p>',
    ];
    $form['textfield1'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('textfield1'),
      '#title' => $this->t('Form 1 text field 1'),
    ];
    $form['textfield2'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('textfield2'),
      '#title' => $this->t('Form 1 text field 2'),
    ];
    $form['details1'] = [
      '#type' => 'details',
      '#title' => 'Form 1 Details 1',
      '#open' => TRUE,
    ];
    $form['details1']['textfield3'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('textfield3'),
      '#title' => $this->t('Form 1 text field 3'),
    ];
    $form['details1']['textfield4'] = [
      '#type' => 'textfield',
      '#default_value' => $config->get('textfield4'),
      '#title' => $this->t('Form 1 text field 4'),
    ];
    $form['extra_actions'] = [
      '#type' => 'details',
      '#title' => 'Form 1 extra actions',
      '#open' => TRUE,
    ];
    $form['extra_actions']['submit1'] = [
      '#type' => 'submit',
      '#value' => $this->t('Form 1 submit extra 1'),
      '#button_type' => 'primary',
      '#submit' => ['::submitExtra'],
    ];
    $form['extra_actions']['submit2'] = [
      '#type' => 'submit',
      '#value' => $this->t('Form 1 submit extra 2'),
      '#button_type' => 'primary',
      '#submit' => ['::submitExtra'],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save 1'),
        '#button_type' => 'primary',
      ],
      'cancel' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('<front>'),
        '#title' => 'Cancel 1',
        '#attributes' => ['class' => ['button']],
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (strlen($form_state->getValue('textfield1')) > 20) {
      $form_state->setError($form['textfield1'], 'Text field 1 must be 20 characters or less');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('stepbystep_test.config');
    $config->set('textfield1', $form_state->getValue('textfield1'))
      ->set('textfield2', $form_state->getValue('textfield2'))
      ->set('textfield3', $form_state->getValue('textfield3'))
      ->set('textfield4', $form_state->getValue('textfield4'))
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function submitExtra(array &$form, FormStateInterface $form_state) {
    $config = $this->config('stepbystep_test.config');
    $config->set('textfield1', $form_state->getValue('textfield1') . ' extra')
      ->set('textfield2', $form_state->getValue('textfield2') . ' extra')
      ->set('textfield3', $form_state->getValue('textfield3') . ' extra')
      ->set('textfield4', $form_state->getValue('textfield4') . ' extra')
      ->save();
  }

}
