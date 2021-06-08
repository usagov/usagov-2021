<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests dynamic entity reference relationship data.
 *
 * @group dynamic_entity_reference
 */
class EntityQueryRelationshipTest extends EntityKernelTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['dynamic_entity_reference'];

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
  protected $referencedEntityType = 'entity_test_rev';

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
   * The results returned by EntityQuery.
   *
   * @var array
   */
  protected $queryResults;

  /**
   * The entity_test entities used by the test.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_rev');

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
      'settings' => [],
    ])->save();
  }

  /**
   * Tests entity query for DER.
   */
  public function testEntityQuery() {
    // Create some test entities which link each other.
    $referenced_entity_1 = EntityTestRev::create(['name' => 'Foobar']);
    $referenced_entity_1->save();
    $referenced_entity_2 = EntityTestRev::create(['name' => 'Barfoo']);
    $referenced_entity_2->save();

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_1;
    $entity->field_test[] = $referenced_entity_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_1->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_2->id());
    $this->entities[] = $entity;

    $entity = EntityTest::create();
    $entity->field_test[] = $referenced_entity_1;
    $entity->field_test[] = $referenced_entity_2;
    $entity->save();
    $this->assertEquals($entity->field_test[0]->entity->id(), $referenced_entity_1->id());
    $this->assertEquals($entity->field_test[1]->entity->id(), $referenced_entity_2->id());
    $this->entities[] = $entity;
    // This returns the 0th entity as that's only one pointing to the 0th
    // account.
    $this->queryResults = $this->container->get('entity_type.manager')
      ->getStorage('entity_test')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition("field_test.0.entity:entity_test_rev.name", 'Foobar')
      ->condition("field_test.1.entity:entity_test_rev.name", 'Barfoo')
      ->execute();
    $this->assertEquals([1 => 1, 2 => 2], $this->queryResults);
  }

}
