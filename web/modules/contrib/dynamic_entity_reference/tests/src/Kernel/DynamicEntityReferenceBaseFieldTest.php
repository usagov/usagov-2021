<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests for the dynamic entity reference base field.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceBaseFieldTest extends EntityKernelTestBase {

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
  protected $referencedEntityType = 'entity_test_mul';

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
  protected $fieldName = 'dynamic_references';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['dynamic_entity_reference'];

  /**
   * Tests reference field validation.
   */
  public function testEntityReferenceFieldValidation() {
    \Drupal::state()->set('dynamic_entity_reference_entity_test_cardinality', 1);
    \Drupal::state()->set('dynamic_entity_reference_entity_test_exclude', [$this->entityType]);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->installEntitySchema('entity_test_mul');
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
    $this->assertEquals($violations[0]->getMessage(), t('The referenced entity type (%type) is not allowed for this field.', ['%type' => $entity->getEntityTypeId()]));
    // Test an invalid entity.
    $entity = $entity_type_manager
      ->getStorage($this->entityType)
      ->create(['type' => $this->bundle]);
    $entity->{$this->fieldName}->entity = $entity;
    $violations = $entity->{$this->fieldName}->validate();
    $this->assertEquals($violations->count(), 1, 'Validation throws a violation.');
    $this->assertEquals($violations[0]->getMessage(), t('The referenced entity type (%type) is not allowed for this field.', ['%type' => $entity->getEntityTypeId()]));

    // @todo Implement a test case for invalid bundle references after
    // https://drupal.org/node/2064191 is fixed
  }

  /**
   * Tests the multiple target entities loader.
   */
  public function testReferencedEntitiesMultipleLoad() {
    \Drupal::state()->set('dynamic_entity_reference_entity_test_cardinality', FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    \Drupal::state()->set('dynamic_entity_reference_entity_test_exclude', [$this->entityType]);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->installEntitySchema('entity_test_mul');
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

    // Attach the first created target entity as the sixth item ($delta == 5) of
    // the parent entity field. We want to test the case when the same target
    // entity is referenced twice (or more times) in the same entity reference
    // field.
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

    // Load target entities using EntityReferenceField::referencedEntities().
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

  /**
   * Tests the der field type for referencing multiple content entities.
   */
  public function testMultipleEntityReference() {
    \Drupal::state()->set('dynamic_entity_reference_entity_test_with_two_base_fields', TRUE);
    $this->enableModules(['dynamic_entity_reference_entity_test']);
    $this->installEntitySchema('entity_test_mul');
    // Update entity_test schema.
    $entity_definition_update_manager = \Drupal::entityDefinitionUpdateManager();
    $mock_entity_type = $this->prophesize(EntityTypeInterface::class);
    $mock_entity_type->id()->willReturn('entity_test');
    $field_storage_definitions = dynamic_entity_reference_entity_test_entity_base_field_info($mock_entity_type->reveal());
    foreach ($field_storage_definitions as $field_name => $field_storage_definition) {
      $entity_definition_update_manager->installFieldStorageDefinition($field_name, 'entity_test', 'dynamic_entity_reference_entity_test', $field_storage_definition);
    }
    // Create some test entities which link each other.
    $referenced_entity = EntityTest::create();
    $referenced_entity->save();
    $referenced_entity_mul = EntityTestMul::create();
    $referenced_entity_mul->save();

    $entity = EntityTest::create();
    $entity->dynamic_references[] = $referenced_entity;
    $entity->der[] = $referenced_entity_mul;
    $entity->save();
    // Loads an unchanged entity from the database.
    $entity = $this->container
      ->get('entity_type.manager')
      ->getStorage($this->entityType)
      ->loadUnchanged($entity->id());

    // Check references correctly for dynamic_references field.
    $this->assertEquals($entity->dynamic_references[0]->target_id, $referenced_entity->id());
    $this->assertEquals($entity->dynamic_references[0]->target_type, $referenced_entity->getEntityTypeId());
    $this->assertEquals($entity->dynamic_references[0]->entity->getName(), $referenced_entity->getName());
    $this->assertEquals($entity->dynamic_references[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->dynamic_references[0]->entity->uuid(), $referenced_entity->uuid());

    // Check references correctly for der field.
    $this->assertEquals($entity->der[0]->target_id, $referenced_entity_mul->id());
    $this->assertEquals($entity->der[0]->target_type, $referenced_entity_mul->getEntityTypeId());
    $this->assertEquals($entity->der[0]->entity->getName(), $referenced_entity_mul->getName());
    $this->assertEquals($entity->der[0]->entity->id(), $referenced_entity_mul->id());
    $this->assertEquals($entity->der[0]->entity->uuid(), $referenced_entity_mul->uuid());

    $entity = EntityTestMul::create();
    $entity->der[] = $referenced_entity;
    $entity->dynamic_references[] = $referenced_entity_mul;
    $entity->save();
    // Loads an unchanged entity from the database.
    $entity = $this->container
      ->get('entity_type.manager')
      ->getStorage('entity_test_mul')
      ->loadUnchanged($entity->id());

    // Check references correctly for dynamic_references field.
    $this->assertEquals($entity->dynamic_references[0]->target_id, $referenced_entity_mul->id());
    $this->assertEquals($entity->dynamic_references[0]->target_type, $referenced_entity_mul->getEntityTypeId());
    $this->assertEquals($entity->dynamic_references[0]->entity->getName(), $referenced_entity_mul->getName());
    $this->assertEquals($entity->dynamic_references[0]->entity->id(), $referenced_entity_mul->id());
    $this->assertEquals($entity->dynamic_references[0]->entity->uuid(), $referenced_entity_mul->uuid());

    // Check references correctly for der field.
    $this->assertEquals($entity->der[0]->target_id, $referenced_entity->id());
    $this->assertEquals($entity->der[0]->target_type, $referenced_entity->getEntityTypeId());
    $this->assertEquals($entity->der[0]->entity->getName(), $referenced_entity->getName());
    $this->assertEquals($entity->der[0]->entity->id(), $referenced_entity->id());
    $this->assertEquals($entity->der[0]->entity->uuid(), $referenced_entity->uuid());
  }

}
