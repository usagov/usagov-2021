<?php

namespace Drupal\tome_sync\Normalizer;

use Drupal\Core\Entity\RevisionableInterface;

/**
 * Normalizer for entity reference revision fields.
 *
 * @internal
 */
class EntityReferenceRevisionItemNormalizer extends EntityReferenceItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem';

  /**
   * {@inheritdoc}
   */
  protected function constructValue($data, $context) {
    $value = parent::constructValue($data, $context);
    if (isset($data['target_uuid']) && isset($data['target_type'])) {
      if ($entity = $this->entityRepository->loadEntityByUuid($data['target_type'], $data['target_uuid'])) {
        if ($entity instanceof RevisionableInterface) {
          $value['target_revision_id'] = $entity->getRevisionId();
        }
      }
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $values = parent::normalize($field_item, $format, $context);
    if (isset($values['target_revision_id'])) {
      unset($values['target_revision_id']);
    }
    return $values;
  }

}
