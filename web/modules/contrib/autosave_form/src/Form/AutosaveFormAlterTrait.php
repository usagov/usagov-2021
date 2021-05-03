<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a trait for common autosave form alterations.
 */
trait AutosaveFormAlterTrait {

  use StringTranslationTrait;
  use AutosaveButtonClickedTrait;

  /**
   * Performs the needed alterations to the form.
   *
   * @param array $form
   *   The form to be altered to provide the autosave form support.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    if (!$this->isAutosaveEnabled($form_state)) {
      return;
    }

    $form['#attached']['library'][] = 'autosave_form/drupal.autosave_form';
    $form['#attached']['drupalSettings']['autosaveForm']['interval'] = $this->configFactory->get('autosave_form.settings')->get('interval');
    $form['#attached']['drupalSettings']['autosaveForm']['notification'] = $this->configFactory->get('autosave_form.settings')->get('notification');
    $input = $form_state->getUserInput();

    $show_restore_discard = !$form_state->isRebuilding() ?: !empty($input['autosave_restore_discard']);
    if ($show_restore_discard && !$form_state->get('autosave_form_state_timestamp') && !$form_state->get('autosave_form_rejected') && ($autosave_form_state_timestamp = $this->getLastAutosavedTimestamp($form_state, $this->currentUser->id()))) {
      $form[AutosaveFormInterface::AUTOSAVE_RESTORE_ELEMENT_NAME] = [
        '#type' => 'submit',
        '#name' => AutosaveFormInterface::AUTOSAVE_RESTORE_ELEMENT_NAME,
        '#value' => $this->t('Autosave restore'),
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['autosave-form-restore', 'visually-hidden']],
        '#submit' => [[$this, 'autosaveFormRestoreSubmit']],
        '#autosave_form_state_timestamp' => $autosave_form_state_timestamp,
      ];
      $form[AutosaveFormInterface::AUTOSAVE_REJECT_ELEMENT_NAME] = [
        '#type' => 'submit',
        '#name' => 'autosave_form_reject',
        '#value' => $this->t('Autosave reject'),
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['autosave-form-reject', 'visually-hidden']],
        '#submit' => [[$this, 'autosaveFormRejectSubmit']],
        '#ajax' => [
          'callback' => [$this, 'autosaveFormRejectAjax'],
        ],
      ];
      $form['autosave_restore_discard'] = [
        '#type' => 'hidden',
        '#default_value' => 'autosave_restore_discard',
        '#attributes' => ['class' => ['autosave-form-restore-discard']],
      ];

      // Add the message to be shown on the form. Our JS library will check if
      // the message exist and only then offer the options for restore and
      // reject, otherwise will start the auto save process.
      $date = $this->dateFormatter->format($autosave_form_state_timestamp, 'custom', 'M d, Y H:i');
      $message = $this->t('A version of this page you were editing at @date was saved as a draft. Do you want to resume editing or discard it?', ['@date' => $date]);
      $form['#attached']['drupalSettings']['autosaveForm']['message'] = (string) $message;
    }

    $autosave_form_session_id = $this->getAutosaveFormSessionID($form_state);
    if (!$autosave_form_session_id) {
      $autosave_form_session_id = !empty($input['autosave_form_session_id']) ? $input['autosave_form_session_id'] : $form['#build_id'];
      $this->setAutosaveFormSessionID($form_state, $autosave_form_session_id);
    }

    $form['autosave_form_session_id'] = [
      '#type' => 'hidden',
      '#value' => $autosave_form_session_id,
      '#name' => 'autosave_form_session_id',
      // Form processing and validation requires this value, so ensure the
      // submitted form value appears literally, regardless of custom #tree
      // and #parents being set elsewhere.
      '#parents' => ['autosave_form_session_id'],
    ];

    $form[AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME] = [
      '#type' => 'submit',
      '#name' => AutosaveFormInterface::AUTOSAVE_ELEMENT_NAME,
      '#value' => $this->t('Autosave save'),
      '#attributes' => ['class' => ['autosave-form-save', 'visually-hidden']],
      '#submit' => [[$this, 'autosaveFormSubmit']],
      '#ajax' => [
        'callback' => [$this, 'autosaveFormAjax'],
        // Do not refocus to prevent losing focus of the element the user might
        // be currently editing when the autosave submission is triggered.
        'disable-refocus' => TRUE,
        'progress' => FALSE,
      ],
      '#autosave_form' => TRUE,
      // Retrieve the "autosave_form_session_id" also from the form state as on
      // autosave restore the one from the restored state will be present in
      // the form state storage and we want to continue using that session for
      // the further autosave states after the restoration.
      '#autosave_form_session_id' => $autosave_form_session_id,
    ];

    $form['autosave_form_last_autosave_timestamp'] = [
      '#type' => 'hidden',
      '#name' => 'autosave_form_last_autosave_timestamp',
      '#value' => $form_state->get('autosave_form_last_autosave_timestamp') ?: '',
    ];
  }

  /**
   * Form submission handler for restoring autosaved state.
   */
  public function autosaveFormRestoreSubmit($form, FormStateInterface $form_state) {
    $trigering_element = $form_state->getTriggeringElement();
    if (!empty($trigering_element['#autosave_form_state_timestamp'])) {
      // Set the timestamp of the autosaved state which has to be used to
      // restore the form on rebuild.
      $form_state->set('autosave_form_state_timestamp', $trigering_element['#autosave_form_state_timestamp']);
      $form_state->setRebuild();
    }
  }

  /**
   * Form submission handler for rejecting autosaved states.
   */
  public function autosaveFormRejectSubmit($form, FormStateInterface $form_state) {
    // As this processing might take some time we want to prevent that if the
    // connection is terminated the user input will be lost.
    ignore_user_abort(TRUE);

    // Mark the form state with a flag indicating an action for autosave has
    // been chosen.
    $form_state->set('autosave_form_rejected', TRUE);
    // Cache the form state in order for the flag to be kept.
    $form_state->setCached();

    // Purge the autosave states.
    $this->purgeAllAutosavedStates($form_state, $this->currentUser->id());
  }

  /**
   * Ajax callback for rejecting autosaved states.
   */
  public function autosaveFormRejectAjax($form, FormStateInterface $form_state) {
    return new AjaxResponse();
  }

  /**
   * Form submission handler for autosaving forms.
   */
  public function autosaveFormSubmit($form, FormStateInterface $form_state) {
    // As this processing might take some time we want to prevent that if the
    // connection is terminated the user input will be lost.
    ignore_user_abort(TRUE);

    if (!$this->isAutosaveSubmitValid($form_state)) {
      $form_state->disableCache();
      return;
    }

    // Having an autosave form session id also ensures that after resuming
    // editing the new autosaved entities will be saved to the same autosave
    // session id.
    $autosave_form_session_id = $this->getAutosaveFormSessionID($form_state);
    $current_user_id = $this->currentUser->id();
    $autosaved_form_state = $this->getLastAutosavedFormState($form_state, $autosave_form_session_id, $current_user_id);

    // If there is non-autosaved state for this session then we have to put the
    // user input into a temporary store and on each autosave submit compare
    // against it for changes and after the first change compare with the last
    // autosaved state.
    if (is_null($autosaved_form_state)) {
      if ($initial_user_input = $this->keyValueExpirableFactory->get('autosave_form')->get($autosave_form_session_id)) {
        $autosaved_form_state_input = $initial_user_input;
      }
      else {
        // 6 hours cache life time for forms should be plenty, like the form
        // cache.
        $expire = 21600;
        $this->keyValueExpirableFactory->get('autosave_form')->setWithExpire($autosave_form_session_id, $form_state->getUserInput(), $expire);

        // This is the first where we cache the user input initially and we are
        // done.
        $form_state->disableCache();
        return;
      }
    }
    else {
      $autosaved_form_state_input = $autosaved_form_state->getUserInput();
    }

    // Subsequent autosaving - compare the user input only. This should be
    // sufficient to detect changes in the fields.
    $form_state_input = $form_state->getUserInput();

    $skip_from_comparision_keys = [
      'form_build_id',
      'form_token',
      'ajax_page_state',
      'autosave_form_last_autosave_timestamp',
      AutosaveFormInterface::AUTOSAVE_RESTORE_ELEMENT_NAME,
      AutosaveFormInterface::AUTOSAVE_REJECT_ELEMENT_NAME,
      'autosave_restore_discard',
    ];
    foreach ($skip_from_comparision_keys as $skip_from_comparision_key) {
      unset($autosaved_form_state_input[$skip_from_comparision_key]);
      unset($form_state_input[$skip_from_comparision_key]);
    }

    $store = $autosaved_form_state_input != $form_state_input;
    if ($store) {
      $autosave_timestmap = $this->time->getRequestTime();
      $form_state->set('autosave_form_last_autosave_timestamp', $autosave_timestmap);
      $form_state->setTemporaryValue('autosave_form_last_autosave_timestamp', $autosave_timestmap);

      $this->storeState($form_state, $autosave_form_session_id, $autosave_timestmap, $current_user_id);
      $this->keyValueExpirableFactory->get('autosave_form')->delete($autosave_form_session_id);
    }

    // We don't have to cache the form each time an autosave submission is
    // triggered, especially when we've skipped the form validation.
    $form_state->disableCache();
  }

  /**
   * Ajax callback for autosaving forms.
   */
  public function autosaveFormAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $timestamp = $form_state->getTemporaryValue('autosave_form_last_autosave_timestamp');
    if (is_numeric($timestamp)) {
      $response->addCommand(new InvokeCommand('input[name="autosave_form_last_autosave_timestamp"]', 'attr', ['value', $timestamp]));
    }

    return $response;
  }

  /**
   * Retrieves the autosave form session ID.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return string|NULL
   *   The autosave form session ID or NULL if none present yet.
   */
  protected static function getAutosaveFormSessionID(FormStateInterface $form_state) {
    return $form_state->get('autosave_form_session_id');
  }

  /**
   * Sets the autosave form session ID into the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $autosave_form_session_id
   *   The autosave form session ID.
   */
  protected function setAutosaveFormSessionID(FormStateInterface $form_state, $autosave_form_session_id) {
    $form_state->set('autosave_form_session_id', $autosave_form_session_id);
  }

  /**
   * Returns the HTTP method used by the request that is building the form.
   *
   * @return string
   *   Can be any valid HTTP method, such as GET, POST, HEAD, etc.
   */
  protected function getRequestMethod() {
    return \Drupal::requestStack()->getCurrentRequest()->getMethod();
  }

}
