<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions_test\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;

/**
 * Defines the test entity class.
 *
 * @ContentEntityType(
 *   id = "st_entity_test",
 *   label = @Translation("ST test entity"),
 *   base_table = "st_entity_test",
 *   data_table = "st_entity_test_field_data",
 *   revision_table = "st_entity_test_revision",
 *   revision_data_table = "st_entity_test_field_revision",
 *   admin_permission = "administer st_entity_test entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *     "revision_translation_affected" = "revision_translation_affected",
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
 *     "canonical" = "/st_entity_test/{st_entity_test}",
 *     "edit-form" = "/st_entity_test/{st_entity_test}/edit",
 *   },
 *   translatable = TRUE,
 * )
 */
class ScheduledTransitionsTestEntity extends EntityTestWithRevisionLog implements EntityChangedInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
