<?php

namespace Drupal\autosave_form\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

trait AutosaveButtonClickedTrait {

  /**
   * Checks if the submission is triggered by autosave save.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if the form submission is triggered by autosave, FALSE otherwise.
   */
  protected function isAutosaveTriggered(FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (is_null($triggering_element)) {
      $user_input = $form_state->getUserInput();
      $autosave = isset($user_input['_triggering_element_name']) && ($user_input['_triggering_element_name'] == AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME);
    }
    else {
      $autosave = $triggering_element && !empty($triggering_element['#autosave_form']);
    }
    return $autosave;
  }

  /**
   * Checks if autosave restore has been triggered.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if autosave restore has been triggered, FALSE otherwise.
   */
  protected function isRestoreTriggered($form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    // The restore submit is a non-ajax element and therefore its name will be
    // contained in the user input as a key.
    $triggered = isset($input[AutosaveFormInterface::AUTOSAVE_RESTORE_ELEMENT_NAME]);
    return $triggered;
  }

  /**
   * Checks if autosave restore has been triggered.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if autosave restore has been triggered, FALSE otherwise.
   */
  protected function isRejectTriggered($form, FormStateInterface $form_state) {
    $user_input = $form_state->getUserInput();
    // The reject submit is an ajax element and therefore its name will not be
    // contained in the user input as a key, but will be contained as a value
    // under the key "_triggering_element_name".
    $triggered = isset($user_input['_triggering_element_name']) && ($user_input['_triggering_element_name'] == AutosaveFormInterface::AUTOSAVE_REJECT_ELEMENT_NAME);
    return $triggered;
  }

}
