<?php

namespace Drupal\Tests\workbench_email\Traits;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\NodeTypeInterface;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Contains helper classes for tests to set up various configuration.
 */
trait WorkbenchEmailTestTrait {

  /**
   * Enables moderation for a given node type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   Node type to enable moderation for.
   */
  protected function setUpModerationForNodeType(NodeTypeInterface $node_type) {
    $node_type->setThirdPartySetting('workbench_moderation', 'enabled', TRUE);
    $states = array_keys(ModerationState::loadMultiple());
    $node_type->setThirdPartySetting('workbench_moderation', 'allowed_moderation_states', $states);
    $node_type->setThirdPartySetting('workbench_moderation', 'default_moderation_state', 'draft');
    $node_type->save();
  }

  /**
   * Adds an email field to a node bundle.
   *
   * @param string $bundle
   *   (optional) Bundle name. Defaults to 'test'.
   */
  protected function setUpEmailFieldForNodeBundle($bundle = 'test') {
    // Add an email field notify to the bundle.
    FieldStorageConfig::create([
      'cardinality' => 1,
      'entity_type' => 'node',
      'field_name' => 'field_email',
      'type' => 'email',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_email',
      'bundle' => 'test',
      'label' => 'Notify',
      'entity_type' => 'node',
    ])->save();
    if (!$entity_form_display = EntityFormDisplay::load(sprintf('node.%s.default', $bundle))) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent('field_email', ['type' => 'email_default'])->save();
  }

}
