<?php

namespace Drupal\dynamic_entity_reference\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use const Drupal\diff\Plugin\diff\Field\COMPARE_ENTITY_REFERENCE_LABEL;
use Drupal\diff\Plugin\diff\Field\EntityReferenceFieldBuilder;

/**
 * Plugin to diff dynamic entity reference fields.
 *
 * @FieldDiffBuilder(
 *   id = "dynamic_entity_reference_field_diff_builder",
 *   label = @Translation("Dynamic Entity Reference Field Diff"),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   },
 * )
 */
class DynamicEntityReferenceFieldBuilder extends EntityReferenceFieldBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        if ($field_item->entity) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $field_item->entity;
          if ($this->configuration['compare_entity_reference'] == COMPARE_ENTITY_REFERENCE_LABEL) {
            $result[$field_key][] = $this->t('@id (@type)', [
              '@id' => $entity->label(),
              '@type' => $entity->getEntityType()->getLabel(),
            ]);
          }
          else {
            $result[$field_key][] = $this->t('Entity ID: @id (@type)', [
              '@id' => $entity->id(),
              '@type' => $entity->getEntityType()->getLabel(),
            ]);
          }
        }
      }
    }

    return $result;
  }

}
