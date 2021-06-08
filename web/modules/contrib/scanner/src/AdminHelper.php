<?php

namespace Drupal\scanner;

use Drupal\Core\Entity\EntityInterface;

/**
 * Shared logic for use in the mdoule.
 */
class AdminHelper {

  /**
   * Show a message on the screen.
   */
  public static function addMessage($message) {
    \Drupal::messenger()->addMessage($message);
  }

  /**
   * Show an error message on the screen.
   */
  public static function addError($message) {
    \Drupal::messenger()->addError($message);
  }

  /**
   * Log or show a notice message on the screen.
   */
  public static function addToLog($message, $DEBUG = FALSE) {
    if ($DEBUG) {
      \Drupal::logger('scanner')->notice($message);
    }
  }

  /**
   * Get all enabled languages, excluding current language.
   */
  public static function getOtherEnabledLanguages() {
    // Get the list of all languages.
    $language = \Drupal::languageManager()->getCurrentLanguage();
    $languages = \Drupal::languageManager()->getLanguages();
    $other_languages = [];

    // Add each enabled language, aside from the current language to an array.
    foreach ($languages as $field_language_code => $field_language) {
      if ($field_language_code != $language->getId()) {
        $other_languages[$field_language_code] = $field_language->getName();
      }
    }
    return $other_languages;
  }

  /**
   * Get current language.
   */
  public static function getDefaultLangcode() {
    $language = \Drupal::languageManager()->getDefaultLanguage();
    return $language->getId();
  }

  /**
   * Get all enabled languages, including the current language.
   */
  public static function getAllEnabledLanguages() {
    // Get the list of all languages.
    $languages = \Drupal::languageManager()->getLanguages();
    $other_languages = [];

    // Add each enabled language, aside from the current language to an array.
    foreach ($languages as $field_language_code => $field_language) {
      $other_languages[$field_language_code] = $field_language->getName();
    }
    return $other_languages;
  }

  /**
   * Get the latest revision.
   */
  public static function _latest_revision($nid, &$vid, $langcode) {
    // Change record below might be helpful for future improvements.
    // @see https://www.drupal.org/node/2942013
    $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if (!isset($langcode)) {
      $langcode = $lang;
    }
    if ($lang != $langcode) {
      $lang = $langcode;
    }
    $latestRevisionResult = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->latestRevision()
      ->condition('nid', $nid, '=')
      ->execute();
    if (count($latestRevisionResult)) {
      $node_revision_id = key($latestRevisionResult);
      if ($node_revision_id == $vid) {
        // There is no pending revision, the current revision is the latest.
        return FALSE;
      }
      $vid = $node_revision_id;
      $latestRevision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($node_revision_id);
      if ($latestRevision->language()->getId() != $lang && $latestRevision->hasTranslation($lang)) {
        $latestRevision = $latestRevision->getTranslation($lang);
      }
      return $latestRevision;
    }
    return FALSE;
  }

  /**
   * Prepares a new revision of a given entity, if applicable.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   A revision log message to set.
   * @param int $current_uid
   *   The user ID of the current logged-in user.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The moderation state for the given entity.
   */
  public static function prepareNewRevision(EntityInterface $entity, $message, $current_uid) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    if ($storage instanceof ContentEntityStorageInterface) {
      $revision = $storage->createRevision($entity);
      if ($revision instanceof RevisionLogInterface) {
        $revision->setRevisionLogMessage($message);
        $revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $revision->setRevisionUserId($current_uid);
      }
      return $revision;
    }
    return $entity;
  }

}
