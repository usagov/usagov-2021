<?php

namespace Drupal\scheduled_publish\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Plugin implementation of the 'scheduled_publish_type' field type.
 *
 * @FieldType(
 *   id = "scheduled_publish",
 *   label = @Translation("Scheduled publish"),
 *   description = @Translation("Scheduled publish"),
 *   default_widget = "scheduled_publish",
 *   default_formatter = "scheduled_publish_generic_formatter"
 * )
 */
class ScheduledPublish extends FieldItemBase implements DateTimeItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {

    $properties['value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(t('Date value'))
      ->setRequired(TRUE);

    $properties['date'] = DataDefinition::create('any')
      ->setLabel(t('Computed date'))
      ->setDescription(t('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\datetime\DateTimeComputed')
      ->setSetting('date source', 'value');

    $properties['moderation_state'] = DataDefinition::create('string')
      ->setLabel(t('The moderation state.'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the computed date is recalculated.
    if ($property_name === 'value') {
      $this->date = NULL;
    }
    parent::onChange($property_name, $notify);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema =
      [
        'columns' => [
          'moderation_state' => [
            'type' => 'varchar',
            'length' => 32,
          ],
          'value' => [
            'description' => 'The date value.',
            'type' => 'varchar',
            'length' => 20,
          ],
        ],
        'indexes' => [
          'value' => ['value'],
        ],
      ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $is_moderation_state = empty($this->get('moderation_state')->getValue());
    $is_value = empty($this->get('value')->getValue());

    return $is_moderation_state || $is_value;
  }
}
