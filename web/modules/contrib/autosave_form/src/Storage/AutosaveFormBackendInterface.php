<?php

namespace Drupal\autosave_form\Storage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for providing autosave form storage.
 */
interface AutosaveFormBackendInterface {

  /**
   * Stores the form state.
   *
   * @param $form_id
   *   The form id.
   * @param string $langcode
   *   The language code of the form.
   * @param $uid
   *   The user id.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function storeFormState($form_id, $langcode, $uid, FormStateInterface $form_state);

  /**
   * Retrieves the stored form state.
   *
   * @param $form_id
   *   The form id.
   * @param string $langcode
   *   The language code of the form.
   * @param $uid
   *   The user id.
   *
   * @return FormStateInterface
   *   The form state object.
   */
  public function getFormState($form_id, $langcode, $uid);

  /**
   * Checks if there is any autosaved form state.
   *
   * @param $form_id
   *   The form id.
   * @param string $langcode
   *   The language code of the form.
   * @param $uid
   *   The user id.
   *
   * @return bool
   *   TRUE, if any autosaved form states have been found, FALSE otherwise.
   */
  public function hasAutosavedFormState($form_id, $langcode, $uid);

  /**
   * Retrieves the timestamp of the last autosaved state.
   *
   * @param $form_id
   *   The form id.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   *
   * @return int
   *   The timestamp of the last autosaved state, or NULL if none has been found.
   */
  public function getLastAutosavedStateTimestamp($form_id, $langcode, $uid);

  /**
   * Purges all autosaved states for the given form id.
   *
   * @param $form_id
   *   The form id.
   * @param string $langcode
   *   (optional) The language code of the form.
   * @param $uid
   *   (optional) The user id.
   */
  public function purgeAutosavedFormStates($form_id, $langcode = NULL, $uid = NULL);

}
