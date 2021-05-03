<?php

namespace Drupal\autosave_form\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for providing autosave storage for entity forms.
 */
interface AutosaveEntityFormStorageInterface {

  const AUTOSAVE_ENTITY_FORM_TABLE = 'autosave_form_entity_form';

  const AUTOSAVE_FORM_FORM_ID_LENGTH = 512;

  /**
   * Stores the entity together with the form state.
   *
   * @param string $form_id
   *   The form id.
   * @param $form_session_id
   *   The form session id.
   * @param string $entity_type_id
   *   The entity type id of the entity to store.
   * @param mixed $entity_id
   *   The id of the entity to store.
   * @param string $langcode
   *   The language code of the original entity.
   * @param int $uid
   *   The user id.
   * @param int $timestamp
   *   The timestamp of the autosave.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function storeEntityAndFormState($form_id, $form_session_id, $entity_type_id, $entity_id, $langcode, $uid, $timestamp, EntityInterface $entity, FormStateInterface $form_state);

  /**
   * Retrieves the stored entity with the form state.
   *
   * The timestamp of the autosaved state will be set into the form state
   * storage under the key 'autosave_form_state_timestamp'.
   *
   * @param $form_id
   *   The form id.
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   * @param $form_session_id
   *   (optional) The form session id.
   * @param $timestamp
   *   (optional) The timestamp for which to load the entity and the form state.
   *
   * @return array
   *   An array containing the entity object and the form state object, keyed
   *   accordingly with 'entity' and 'form_state'.
   */
  public function getEntityAndFormState($form_id, $entity_type_id, $entity_id, $langcode, $uid, $form_session_id = NULL, $timestamp = NULL);

  /**
   * Retrieves the stored entity.
   *
   * @param $form_id
   *   The form id.
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   * @param $form_session_id
   *   (optional) The form session id.
   * @param $timestamp
   *   (optional) The timestamp for which to load the entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity($form_id, $entity_type_id, $entity_id, $langcode, $uid, $form_session_id = NULL, $timestamp = NULL);

  /**
   * Retrieves the form state.
   *
   * The timestamp of the autosaved state will be set into the form state
   * storage under the key 'autosave_form_state_timestamp'.
   *
   * @param $form_id
   *   The form id.
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   * @param $form_session_id
   *   (optional) The form session id.
   * @param $timestamp
   *   (optional) The timestamp for which to load the form state.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   The form state.
   */
  public function getFormState($form_id, $entity_type_id, $entity_id, $langcode, $uid, $form_session_id = NULL, $timestamp = NULL);

  /**
   * Checks if any autosaved state exist for the user based on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param $uid
   *   The user id.
   *
   * @return bool
   *   TRUE if the storage contains an autosaved state for the user id based on
   *   the form state, FALSE otherwise.
   */
  public function hasAutosavedStateForFormState(FormStateInterface $form_state, $uid);

  /**
   * Checks if there is any autosaved entity.
   *
   * @param $form_id
   *   The form id.
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity to store.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   * @param string $form_session_id
   *   (optional) The form session id.
   *
   * @return bool
   *   TRUE, if any autosaved entities have been found, FALSE otherwise.
   */
  public function hasAutosavedState($form_id, $entity_type_id, $entity_id, $langcode, $uid, $form_session_id = NULL);

  /**
   * Retrieves the timestamp of the last autosaved state.
   *
   * @param $form_id
   *   The form id.
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity to store.
   * @param string $langcode
   *   The language code of the original entity.
   * @param $uid
   *   The user id.
   *
   * @return int
   *   The timestamp of the last autosaved state, or NULL if none has been found.
   */
  public function getLastAutosavedStateTimestamp($form_id, $entity_type_id, $entity_id, $langcode, $uid);

  /**
   * Purges all autosaved states for the given entity.
   *
   * @param string $entity_type_id
   *   The entity type id of the entity.
   * @param mixed $entity_id
   *   The id of the entity to store.
   * @param string $form_session_id
   *   (optional) The form session id.
   * @param $form_id
   *   (optional) The form id.
   * @param string $langcode
   *   (optional) The language code, for which to restrict the purge.
   * @param $uid
   *   (optional) The user id, for which to restrict the purge.
   */
  public function purgeAutosavedEntityState($entity_type_id, $entity_id, $form_session_id = NULL, $form_id = NULL, $langcode = NULL, $uid = NULL);

  /**
   * Purges all autosaved states matching the conditions.
   *
   * Calling this method without arguments will empty the whole storage.
   *
   * @param string $entity_type_id
   *   (optional) The entity type id.
   * @param string $langcode
   *   (optional) The language code, for which to restrict the purge.
   * @param $uid
   *   (optional) The user id, for which to purge the autosaved entities.
   */
  public function purgeAutosavedEntitiesStates($entity_type_id = NULL, $langcode = NULL, $uid = NULL);

}
