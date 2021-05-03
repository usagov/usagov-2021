<?php

namespace Drupal\autosave_form\Form;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for providing autosave for forms.
 */
interface AutosaveEntityFormHandlerInterface extends AutosaveFormInterface {

  /**
   * The object property to use to flag the entity with the autosave session ID.
   */
  const AUTOSAVE_SESSION_ID = 'autosaveSessionID';

  /**
   * Returns the autosave session ID of the entity.
   *
   * @return string|NULL
   *   The autosave session ID or NULL if it is not set.
   */
  public static function getAutosaveSessionID(EntityInterface $entity);

}
