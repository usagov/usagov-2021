<?php

namespace Drupal\workbench_email\Plugin\RecipientType;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a recipient type based on entity reference fields.
 *
 * @RecipientType(
 *   id = "entity_reference_user",
 *   title = @Translation("Entity Reference: User"),
 *   description = @Translation("Send to users referenced in an entity reference field."),
 *   settings = {
 *     "fields" = {},
 *   },
 * )
 */
class EntityReferenceUser extends EmailField {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Add the fields.
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');
    $field_options = [];
    foreach ($fields as $entity_type_id => $entity_type_fields) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      if (!$this->isModeratableEntityType($entity_type)) {
        // These fields are irrelevant, the entity type isn't moderated.
        continue;
      }
      $base = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
      foreach ($entity_type_fields as $field_name => $field_detail) {
        if (in_array($field_name, array_keys($base), TRUE)) {
          if ($base[$field_name]->getSetting('target_type') !== 'user') {
            continue;
          }
          $field_options[$entity_type_id . ':' . $field_name] = $base[$field_name]->getLabel() . ' (' . $entity_type->getLabel() . ')';
          continue;
        }
        $sample_bundle = reset($field_detail['bundles']);
        $bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $sample_bundle);
        if (!isset($bundle_fields[$field_name])) {
          // Stale field map reference.
          continue;
        }
        $sample_field = $bundle_fields[$field_name];
        if ($sample_field->getSetting('target_type') !== 'user') {
          continue;
        }
        $field_options[$entity_type_id . ':' . $field_name] = $sample_field->label() . ' (' . $entity_type->getLabel() . ')';
      }
    }
    return [
      'fields' => [
        '#type' => 'checkboxes',
        '#title' => $this->t('Entity Reference User Fields'),
        '#description' => $this->t('Send to users referenced by the selected fields.'),
        '#options' => $field_options,
        '#default_value' => $this->getFields(),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailFromFieldItem(FieldItemInterface $field_item) {
    return $field_item->entity->getEmail();
  }

}
