<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Form\FormStateInterface;


/**
 * Interface for providing autosave for forms.
 */
interface AutosaveFormInterface {

  const AUTOSAVE_ELEMENT_NAME = 'autosave_form_save';
  const AUTOSAVE_RESTORE_ELEMENT_NAME = 'autosave_form_restore';
  const AUTOSAVE_REJECT_ELEMENT_NAME = 'autosave_form_reject';

  /**
   * Performs the needed alterations to the form.
   *
   * @param array $form
   *   The form to be altered to provide autosave.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state);

  /**
   * Checks if the autosave submit is allowed.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return boolean
   *   TRUE if the autosave submission should be executed, FALSE otherwise.
   */
  public function isAutosaveSubmitValid(FormStateInterface $form_state);

  /**
   * Stores the state of the form in the autosave storage.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $autosave_form_session_id
   *   The autosave form session ID.
   * @param int $autosave_timestamp
   *   The timestamp to use for the autosave record.
   * @param string|int $uid
   *   The user id.
   */
  public function storeState(FormStateInterface $form_state, $autosave_form_session_id, $autosave_timestamp, $uid);

  /**
   * Retrieves the last autosaved timestamp.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string|int $uid
   *   The user id.
   *
   * @return int
   *   The last autosaved timestamp.
   */
  public function getLastAutosavedTimestamp(FormStateInterface $form_state, $uid);

  /**
   * Retrieves the last autosaved form state if any present.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form, based on which to retrieve the last
   *   autosaved form state.
   * @param string $autosave_form_session_id
   *   The autosave form session ID for which to retrieve the form state.
   * @param string|int $uid
   *   The user id.
   *
   * @return \Drupal\Core\Form\FormStateInterface|NULL
   *   The last autosaved form state or NULL if none present.
   */
  public function getLastAutosavedFormState(FormStateInterface $form_state, $autosave_form_session_id, $uid);

  /**
   * Purges the current autosave state session for the form state and user.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The current state of the form.
   * @param string|int $uid
   *   The user id for which to purge the autosaved states.
   */
  public function purgeCurrentAutosavedState(FormStateInterface $form_state, $uid);

  /**
   * Purges all autosaved state sessions for the current form and user.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The current state of the form.
   * @param string|int $uid
   *   The user id for which to purge the autosaved states.
   */
  public function purgeAllAutosavedStates(FormStateInterface $form_state, $uid);

  /**
   * Determines if autosave is enabled for the current form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The current state of the form.
   *
   * @return bool
   *   TRUE if autosave is enabled, FALSE otherwise.
   */
  public function isAutosaveEnabled(FormStateInterface $form_state);

}
