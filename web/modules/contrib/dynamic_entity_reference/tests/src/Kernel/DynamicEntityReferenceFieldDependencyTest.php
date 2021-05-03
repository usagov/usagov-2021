<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests for dynamic entity reference field dependencies.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFieldDependencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_entity_reference',
    'field',
    'entity_test',
    'user',
    'dynamic_entity_reference_test_entity_provider',
  ];

  /**
   * A dynamic entity reference field.
   *
   * @var \Drupal\field\FieldStorageConfigInterface
   */
  protected $fieldStorage;

  /**
   * A list of all providers of content entity types currently installed.
   *
   * @var array
   */
  protected $entityTypeProviders = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');

    $this->fieldStorage = FieldStorageConfig::create([
      'type' => 'dynamic_entity_reference',
      'field_name' => strtolower($this->randomMachineName()),
      'entity_type' => 'entity_test',
    ]);

    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = $this->container->get('entity_type.manager');

    // Gather the provider ID's of all content entity types.
    foreach ($entityTypeManager->getDefinitions() as $entity_id => $entity_type) {
      if ($entity_type instanceof ContentEntityTypeInterface) {
        $provider = $entity_type->getProvider();
        if (!in_array($provider, $this->entityTypeProviders) && $provider !== 'core') {
          $this->entityTypeProviders[] = $provider;
        }
      }
    }
  }

  /**
   * Test dependencies when all entity types can be referenced.
   */
  public function testFieldDependenciesAllEntities() {
    $this->fieldStorage->setSetting('exclude_entity_types', TRUE);
    $this->fieldStorage->setSetting('entity_type_ids', []);
    $this->fieldStorage->save();

    // Expected module dependencies.
    $module_dependencies = $this->entityTypeProviders;
    $module_dependencies[] = 'dynamic_entity_reference';

    $this->assertEqualsCanonicalizing(['module' => $module_dependencies], $this->fieldStorage->getDependencies());
  }

  /**
   * Test dependencies when there is entity type exclusion.
   */
  public function testFieldDependenciesExclude() {
    $this->fieldStorage->setSetting('exclude_entity_types', TRUE);
    $this->fieldStorage->setSetting('entity_type_ids', [
      'entity_test_provider',
    ]);
    $this->fieldStorage->save();

    // Expected module dependencies.
    $module_dependencies = $this->entityTypeProviders;
    $module_dependencies[] = 'dynamic_entity_reference';

    // Remove entity_test_provider.
    $module_dependencies = array_diff($module_dependencies, ['dynamic_entity_reference_test_entity_provider']);

    $this->assertEqualsCanonicalizing(['module' => $module_dependencies], $this->fieldStorage->getDependencies());
  }

  /**
   * Test dependencies when entity types are strictly defined.
   */
  public function testFieldDependenciesStrict() {
    $this->fieldStorage->setSetting('exclude_entity_types', FALSE);
    $this->fieldStorage->setSetting('entity_type_ids', [
      'entity_test_provider',
    ]);
    $this->fieldStorage->save();

    // Expected module dependencies.
    $module_dependencies[] = 'entity_test';
    $module_dependencies[] = 'dynamic_entity_reference_test_entity_provider';
    $module_dependencies[] = 'dynamic_entity_reference';

    $this->assertEqualsCanonicalizing(['module' => $module_dependencies], $this->fieldStorage->getDependencies());
  }

  /**
   * Test dependencies when entity types are strict, but none are defined.
   */
  public function testFieldDependenciesNone() {
    $this->fieldStorage->setSetting('exclude_entity_types', FALSE);
    $this->fieldStorage->setSetting('entity_type_ids', []);
    $this->fieldStorage->save();

    // Expected module dependencies.
    $module_dependencies[] = 'entity_test';
    $module_dependencies[] = 'dynamic_entity_reference';

    $this->assertEqualsCanonicalizing(['module' => $module_dependencies], $this->fieldStorage->getDependencies());
  }

}
