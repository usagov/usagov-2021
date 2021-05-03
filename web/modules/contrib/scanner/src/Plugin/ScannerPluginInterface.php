<?php

namespace Drupal\scanner\Plugin;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

interface ScannerPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Performs the search operation and returns the results..
   * 
   * @param string $field
   *   The fully qualified name of the field (entityType:bundle:fieldname).
   * @param string $values
   *   The input values from the form ($form_state values).
   * 
   * @return array
   *   An array containing the entity titles and an array of matches in the
   *   entity.
   */
  public function search($field, $values);

  /**
   * Performs the replace operation and returns the results.
   * 
   * @param string $field
   *  The fully qualified name of the field (entityType:bundle:fieldname).
   * @param string $values
   *  The input values from the form ($form_state values).
   * 
   * @return array
   *   An array containing both the old and new revision IDs for each affected
   *   entity.
   */
  public function replace($field, $values, $undo_data);

  /**
   * Performs the undo operation.
   * 
   * @param array $data
   *   An array containing the old and new revision id for the enttiy.
   */
  public function undo($data);

}
