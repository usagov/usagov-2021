<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions_test\Entity;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the non-translatable test entity class.
 *
 * @ContentEntityType(
 *   id = "st_nont_entity_test",
 *   label = @Translation("ST non translatable test entity"),
 *   base_table = "st_nont_entity_test",
 *   data_table = "st_nont_entity_test_field_data",
 *   revision_table = "st_nont_entity_test_revision",
 *   revision_data_table = "st_nont_entity_test_field_revision",
 *   admin_permission = "administer st_nont_entity_test entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "label" = "name",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/st_nont_entity_test/{st_nont_entity_test}",
 *     "edit-form" = "/st_nont_entity_test/{st_nont_entity_test}/edit",
 *   },
 *   translatable = FALSE,
 * )
 */
class ScheduledTransitionsNonTranslatableTestEntity extends ScheduledTransitionsTestEntity {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $fields */
    $fields['name']->setTranslatable(FALSE);
    unset($fields['revision_translation_affected']);
    return $fields;
  }

}
