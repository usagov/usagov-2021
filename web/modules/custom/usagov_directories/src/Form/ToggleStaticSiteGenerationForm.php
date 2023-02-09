<?php

namespace Drupal\usagov_directories\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Implements a form an administrator can use Enable or Disable Tome's cron runs.
* This is expected to be used during deployments, allowing config changes to be made before tome's first run.
*/
class ToggleStaticSiteGenerationForm extends FormBase {

 /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'toggle_static_site_generation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Are you sure you want to toggle Tome site generation (this will not cancel a Tome run that is already in progress) ?'),
    ];
    $form['confirm_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check this box to Confirm!'),
      '#default_value' => TRUE
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Toggle Static'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /*if (!$form_state->getValue('confirm_toggle')) {
      $form_state->setErrorByName('confirm_toggle', 'You must check the box. Are you really sure?');
    }*/
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $errors = FALSE;
    $toggle_state = $form_state->getValue('confirm_toggle');

    try {
      if ( $toggle_state ) {
        $this->messenger()->addStatus($this->t("Toggling Tome ON."));
      } else {
        $this->messenger()->addStatus($this->t("Toggling Tome OFF."));
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('usagov_directories')->error('Error while attempting toggle tome: @error',
        ['@error' => $e->getMessage()]);
      $errors = TRUE;
    }

    if ($errors) {
      $this->messenger()->addError("Something went wrong. See the error log for details.");
    }

    $this->messenger()->addStatus($this->t("Toggled Tome. I hope you're happy with yourself."));
  }

}
