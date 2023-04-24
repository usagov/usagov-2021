<?php

namespace Drupal\usagov_ssg_postprocessing\Form;

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
    return usagov_ssg_postprocessing_get_static_state_form_id();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $toggle_state = \Drupal::state()->get(usagov_ssg_postprocessing_get_static_state_var()) ? 'Enable' : 'Disable';

    $desc_text = $this->t(\Drupal::state()->get(usagov_ssg_postprocessing_get_static_state_var()) ?
      "Static Site Generation is currently DISABLED." :
      "Static Site Generation is currently ENABLED. Note: Disabling will not cancel a Tome run that is already in progress.");

    $form['description'] = [
      '#type' => 'processed_text',
      //'#text' => $this->t('Are you sure you want to toggle Tome site generation (this will not cancel a Tome run that is already in progress) ?'),
      '#text' => $desc_text,
    ];

    /*$form[usagov_ssg_postprocessing_get_static_state_button_name()] = [
    '#type' => 'checkbox',
    '#title' => $this->t('Check this box to ENABLE Static Site Generation.  Uncheck to DISABLE.'),
    '#default_value' => $toggle_state,
    ];*/

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('@able Static Site Generation', ['@able' => $toggle_state]),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $errors = FALSE;

    try {
      $toggle_state = \Drupal::state()->get(usagov_ssg_postprocessing_get_static_state_var()) ? FALSE : TRUE;
      //$toggle_state = $form_state->getValue(usagov_ssg_postprocessing_get_static_state_button_name()) ? TRUE : FALSE;
      if ($toggle_state) {
        \Drupal::state()->set(usagov_ssg_postprocessing_get_static_state_var(), TRUE);
      }
      else {
        \Drupal::state()->delete(usagov_ssg_postprocessing_get_static_state_var());
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('usagov_ssg_postprocessing')->error('Error while attempting toggle tome: @error',
        ['@error' => $e->getMessage()]);
      $errors = TRUE;
    }

    if ($errors) {
      $this->messenger()->addError("Something went wrong. See the error log for details.");
    }
  }

}
