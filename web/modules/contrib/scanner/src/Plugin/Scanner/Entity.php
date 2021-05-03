<?php

namespace Drupal\scanner\Plugin\Scanner;

use Drupal\scanner\Plugin\ScannerPluginBase;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class Entity.
 *
 * @Scanner(
 *   id = "scanner_entity",
 *   type = "entity",
 * )
 */
class Entity extends ScannerPluginBase {

  protected $scannerRegexChars = '.\/+*?[^]$() {}=!<>|:';

  /**
   * Performs the serach operation for the given string/expression.
   *
   * @param string $field
   *   The field with the matching string (formatted as type:bundle:field).
   * @param array $values
   *   An array containing the $form_state values.
   *
   * @return array
   *   An array containing the titles of the entity and a snippet of the
   *   matching text.
   */
  public function search($field, $values) {
    $data = [];
    list($entityType, $bundle, $fieldname) = explode(':', $field);

    // Attempt to load the matching plugin for the matching entity.
    try {
      $plugin = $this->scannerManager->createInstance("scanner_$entityType");
    }
    catch (PluginException $e) {
      // The instance could not be found so fail gracefully and let the user
      // know.
      \Drupal::logger('scanner')->error($e->getMessage());
      \Drupal::messenger()->addError(t('An error occured: '. $e->getMessage()));
    }

    // Perform the search on the current field.
    $results = $plugin->search($field, $values);
    if (!empty($results)) {
      $data = $results;
    }
    return $data;
  }

  /**
   * Performs the replace operation for the given string/expression.
   *
   * @param string $field
   *   The field with the matching string (formatted as type:bundle:field).
   * @param array $values
   *   An array containing the $form_state values.
   * 
   * @return array
   *   An array containing the revisoion ids of the affected entities.
   */
  public function replace($field, $values, $undo_data) {
    $data = [];
    list($entityType, $bundle, $fieldname) = explode(':', $field);

    try {
      $plugin = $this->scannerManager->createInstance("scanner_$entityType");
    }
    catch (PluginException $e) {
      // The instance could not be found so fail gracefully and let the user
      // know.
      \Drupal::logger('scanner')->error($e->getMessage());
      \Drupal::messenger()->addError(t('An error occured: '. $e->getMessage()));
    }   
  
    // Perform the replace on the current field and save results.
    $results = $plugin->replace($field, $values, $undo_data);
    if (!empty($results)) {
      $data = $results;
    }

    return $data;
  }

  /**
   * Undo the replace operation by reverting entities to a previous revision.
   *
   * @param array $data
   *   An array containing the revision ids needed to undo the previous replace
   *   operation.
   */
  public function undo($data) {
    foreach ($data as $key => $value) {
      list($entityType, $id) = explode(':', $key);
      // Attempt to load the matching plugin for the matching entity.
      try {
        $plugin = $this->scannerManager->createInstance("scanner_$entityType");
        $plugin->undo($value);
      }
      catch (PluginException $e) {
        \Drupal::logger('scanner')->error($e->getMessage());
        \Drupal::messenger()->addError(t('An error occured: '. $e->getMessage()));
      }
    }
  }

  /**
   * Helper function to "build" the proper query condition.
   * 
   * @param string $search
   *   The string that is to be searched for.
   * @param boolean $mode
   *   The boolean that indicated whether or not the search should be case
   *   sensitive.
   * @param boolean $wholeword
   *   The boolean that indicates whether the search should be word bounded.
   * @param boolean $search
   *   The boolean that indicates whether or not the search term is a regular
   *   expression.
   *
   * @return array
   *   Returns an array containing the SQL and regex matching conditions.
   */
  protected function buildCondition($search, $mode, $wholeword, $regex, $preceded, $followed) {
    $preceded_php = '';
    if (!empty($preceded)) {
      if (!$regex) {
        $preceded = addcslashes($preceded, $this->scanerRegexChars);
      }
      $preceded_php = '(?<=' . $preceded . ')';
    }
    $followed_php = '';
    if (!empty($followed)) {
      if (!$followed) {
        $followed = addcslashes($followed, $this->scanerRegexChars);
      }
      $followed_php = '(?=' . $followed . ')';
    }

    // Case 1.
    if ($wholeword && $regex) {
      $value = "[[:<:]]" . $preceded . $search . $followed ."[[:>:]]";
      $operator = 'REGEXP';
      $phpRegex = '/\b' . $preceded_php . $search . $followed_php . '\b/';
    }
    // Case 2.
    elseif ($wholeword && !$regex) {
      $value = '[[:<:]]' . $preceded . addcslashes($search, $this->scannerRegexChars) . $followed . '[[:>:]]';
      $operator = 'REGEXP';
      $phpRegex = '/\b' . $preceded_php . addcslashes($search, $this->scannerRegexChars) . $followed . '\b/';
    }
    // Case 3.
    elseif (!$wholeword && $regex) {
      $value = $preceded . $search . $followed;
      $operator = 'REGEXP';
      $phpRegex = '/' . $preceded_php . $search . $followed_php . '/';
    }
    // Case 4.
    else {
      $value = '%' . $preceded . addcslashes($search, $this->scannerRegexChars) . $followed . '%';
      $operator = 'LIKE';
      $phpRegex = '/' . $preceded . addcslashes($search, $this->scannerRegexChars) . $followed . '/';
    }

    if ($mode) {
      return [
        'condition' => $value,
        'operator' => $operator . ' BINARY',
        'phpRegex' => $phpRegex
      ];
    }
    else {
      return [
        'condition' => $value,
        'operator' => $operator,
        'phpRegex' => $phpRegex . 'i'
      ];
    }
  }

}
