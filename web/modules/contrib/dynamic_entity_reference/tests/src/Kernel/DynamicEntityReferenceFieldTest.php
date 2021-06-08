<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests for the dynamic entity reference field.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFieldTest extends EntityKernelTestBase {
  use SchemaCheckTestTrait;

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The entity type that is being referenced.
   *
   * @var string
   */
  protected $referencedEntityType = 'entity_test_with_bundle';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * The name of the field used in this test.
   *
   * @var string
   */
  protected $fieldName = 'field_test';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['dynamic_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_with_bundle');

    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'type' => 'dynamic_entity_reference',
      'entity_type' => $this->entityType,
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          $this->referencedEntityType,
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'bundle' => $this->bundle,
      'label' => 'Field test',
      'settings' => [
        $this->referencedEntityType => [
          'handler' => 'default:' . $this->referencedEntityType,
          'handler_settings' => [
            'target_bundles' => NULL,
          ],
        ],
      ],
    ])->save();

  }

  /**
   * Tests reference field validation.
   */
  public function testEntityReferenceFieldValidation() {
    $entity_type_manager = \Drupal::entityTypeManager();
    // Test a valid reference.
    $referenced_entity = $entity_type_manager
      ->getStorage($this->referencedEntityType)
      ->create(['type' => $this->bundle]);
    $referenced_entity->save();

    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 0, 'Validation passes.');

    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->entity = $referenced_entity;
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 0, 'Validation passes.');

    // Test an invalid reference.
    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = 9999;
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('The referenced entity (%type: %id) does not exist.', [
      '%type' => $this->referencedEntityType,
      '%id' => 9999,
    ]));

    // Test an invalid target_type.
    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('The referenced entity type (%type) is not allowed for this field.', ['%type' => $this->entityType]));

    // Test an invalid entity.
    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->entity = $entity;
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('The referenced entity type (%type) is not allowed for this field.', ['%type' => $entity->getEntityTypeId()]));

    // Test bundle validation with empty array. Empty array means no bundle is
    // allowed.
    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load($this->entityType . '.' . $this->bundle . '.' . $this->fieldName);
    // Empty array means no target bundles are allowed.
    $settings = [
      'handler' => 'default:' . $this->referencedEntityType,
      'handler_settings' => [
        'target_bundles' => [],
      ],
    ];
    $field_config->setSetting('entity_test_with_bundle', $settings);
    $field_config->save();

    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('No bundle is allowed for (%type)', ['%type' => $this->referencedEntityType]));

    // Test with wrong bundle.
    $bundle = EntityTestBundle::create([
      'id' => 'newbundle',
      'label' => 'New Bundle',
      'revision' => FALSE,
    ]);
    $bundle->save();

    $field_config = $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->load($this->entityType . '.' . $this->bundle . '.' . $this->fieldName);
    $settings = [
      'handler' => 'default:' . $this->referencedEntityType,
      'handler_settings' => [
        'target_bundles' => ['newbundle'],
      ],
    ];
    $field_config->setSetting('entity_test_with_bundle', $settings);
    $field_config->save();

    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->target_type = $referenced_entity->getEntityTypeId();
    $entity->{$this->fieldName}->target_id = $referenced_entity->id();
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('Referenced entity %label does not belong to one of the supported bundles (%bundles).', [
      '%label' => $referenced_entity->label(),
      '%bundles' => 'newbundle',
    ]));
  }

  /**
   * Tests the multiple target entities loader.
   */
  public function testReferencedEntitiesMultipleLoad() {
    $entity_type_manager = \Drupal::entityTypeManager();
    // Create the parent entity.
    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);

    // Create three target entities and attach them to parent field.
    $target_entities = [];
    $reference_field = [];
    for ($i = 0; $i < 3; $i++) {
      $target_entity = $entity_type_manager
        ->getStorage($this->referencedEntityType)
        ->create(['type' => $this->bundle]);
      $target_entity->save();
      $target_entities[] = $target_entity;
      $reference_field[] = [
        'target_id' => $target_entity->id(),
        'target_type' => $this->referencedEntityType,
      ];
    }

    // Also attach a non-existent entity and a NULL target id.
    $reference_field[3]['target_id'] = 99999;
    $reference_field[3]['target_type'] = $this->referencedEntityType;
    $target_entities[3] = NULL;
    $reference_field[4]['target_id'] = NULL;
    $reference_field[4]['target_type'] = $this->referencedEntityType;
    $target_entities[4] = NULL;

    // Also attach a non-existent entity and a NULL target id.
    $reference_field[5]['target_id'] = 99999;
    $reference_field[5]['target_type'] = $this->entityType;
    $target_entities[5] = NULL;
    $reference_field[6]['target_id'] = NULL;
    $reference_field[6]['target_type'] = NULL;
    $target_entities[6] = NULL;

    // Attach the first created target entity as the eighth item ($delta == 7)
    // of the parent entity field. We want to test the case when the same target
    // entity is referenced twice (or more times) in the same dynamic entity
    // reference field.
    $reference_field[7] = $reference_field[0];
    $target_entities[7] = $target_entities[0];

    // Create a new target entity that is not saved, thus testing the
    // "autocreate" feature.
    $target_entity_unsaved = $entity_type_manager
      ->getStorage($this->referencedEntityType)
      ->create(['type' => $this->bundle, 'name' => $this->randomString()]);
    $reference_field[8]['entity'] = $target_entity_unsaved;
    $target_entities[8] = $target_entity_unsaved;

    // Set the field value.
    $entity->{$this->fieldName}->setValue($reference_field);

    // Load the target entities using
    // DynamicEntityReferenceField::referencedEntities().
    $entities = $entity->{$this->fieldName}->referencedEntities();

    // Test returned entities:
    // - Deltas must be preserved.
    // - Non-existent entities must not be retrieved in target entities result.
    foreach ($target_entities as $delta => $target_entity) {
      if (!empty($target_entity)) {
        if (!$target_entity->isNew()) {
          // There must be an entity in the loaded set having the same id for
          // the same delta.
          $this->assertEquals($target_entity->id(), $entities[$delta]->id());
        }
        else {
          // For entities that were not yet saved, there must an entity in the
          // loaded set having the same label for the same delta.
          $this->assertEquals($target_entity->label(), $entities[$delta]->label());
        }
      }
      else {
        // A non-existent or NULL entity target id must not return any item in
        // the target entities set.
        $this->assertFalse(isset($entities[$delta]));
      }
    }
  }

}
