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
    return get_static_state_form_id();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
 
    $form['description'] = [
      '#type' => 'processed_text',
      '#text' => $this->t('Are you sure you want to toggle Tome site generation (this will not cancel a Tome run that is already in progress) ?'),
    ];

    $toggle_state = \Drupal::state()->get(get_static_state_var()) ? TRUE : FALSE;
    $form[get_static_state_button_name()] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check this box to ENABLE Static Site Generation.  Uncheck to DISABLE.'),
      '#default_value' => $toggle_state,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Toggle Static Site Generation'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  /*public function validateForm(array &$form, FormStateInterface $form_state) {
  }*/

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $errors = FALSE;
    $toggle_state = $form_state->getValue(get_static_state_button_name());

    try {
      if ( $toggle_state ) {
        $this->messenger()->addStatus($this->t("Toggling Tome ON."));
        \Drupal::state()->set(get_static_state_var(),TRUE);
      } else {
        $this->messenger()->addStatus($this->t("Toggling Tome OFF."));
        \Drupal::state()->delete(get_static_state_var());
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
