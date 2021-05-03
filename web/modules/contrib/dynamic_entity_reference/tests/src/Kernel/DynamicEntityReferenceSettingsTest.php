<?php

namespace Drupal\Tests\dynamic_entity_reference\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests dynamic entity reference field settings.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceSettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'taxonomy',
    'field',
    'user',
    'text',
    'dynamic_entity_reference',
    'entity_test',
    'system',
  ];

  /**
   * Testing node type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  protected $nodeType;

  /**
   * Testing vocabulary.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  protected $vocabulary;

  /**
   * An entity bundle that is not stored as a configuration entity.
   *
   * @var string
   */
  protected $customBundle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setup();

    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('entity_test');

    $this->nodeType = NodeType::create([
      'type' => mb_strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ]);
    $this->nodeType->save();

    $this->vocabulary = Vocabulary::create([
      'vid' => mb_strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ]);
    $this->vocabulary->save();

    // Create a custom bundle.
    $this->customBundle = 'test_bundle_' . mb_strtolower($this->randomMachineName());
    entity_test_create_bundle($this->customBundle, NULL, 'entity_test');
  }

  /**
   * Tests that config bundle deletions are mirrored in field config settings.
   */
  public function testConfigTargetBundleDeletion() {
    // Attach an entity reference field to $this->nodeType.
    $name = mb_strtolower($this->randomMachineName());
    $label = $this->randomString();
    $vid = $this->vocabulary->id();
    $handler_settings = ['target_bundles' => [$vid => $vid]];

    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $name,
      'type' => 'dynamic_entity_reference',
      'entity_type' => 'node',
      'cardinality' => 1,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'taxonomy_term',
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $this->nodeType->id(),
      'label' => $label,
      'settings' => [
        'taxonomy_term' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => $handler_settings,
        ],
      ],
    ])->save();

    // Check that the 'target_bundle' setting contains the vocabulary.
    $field_config = FieldConfig::loadByName('node', $this->nodeType->id(), $name);
    $settings = $field_config->getSettings();
    $actual_handler_settings = $settings['taxonomy_term']['handler_settings'];
    $this->assertEquals($handler_settings, $actual_handler_settings);

    // Delete the vocabulary.
    $this->vocabulary->delete();

    // Check that the deleted vocabulary is no longer present in the
    // 'target_bundles' field setting.
    $field_config = FieldConfig::loadByName('node', $this->nodeType->id(), $name);
    $settings = $field_config->getSettings();
    $handler_settings = $settings['taxonomy_term']['handler_settings'];
    $this->assertTrue(empty($handler_settings['target_bundles']));
  }

  /**
   * Tests that deletions of custom bundles are mirrored in field settings.
   */
  public function testCustomTargetBundleDeletion() {
    // Attach an entity reference field to $this->nodeType.
    $name = mb_strtolower($this->randomMachineName());
    $label = $this->randomString();
    $handler_settings = ['target_bundles' => [$this->customBundle => $this->customBundle]];

    // Create a field.
    FieldStorageConfig::create([
      'field_name' => $name,
      'type' => 'dynamic_entity_reference',
      'entity_type' => 'node',
      'cardinality' => 1,
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'entity_test',
        ],
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $this->nodeType->id(),
      'label' => $label,
      'settings' => [
        'entity_test' => [
          'handler' => 'default:entity_test',
          'handler_settings' => $handler_settings,
        ],
      ],
    ])->save();

    // Check that the 'target_bundle' setting contains the custom bundle.
    $field_config = FieldConfig::loadByName('node', $this->nodeType->id(), $name);
    $settings = $field_config->getSettings();
    $actual_handler_settings = $settings['entity_test']['handler_settings'];
    $this->assertEquals($handler_settings, $actual_handler_settings);

    // Delete the custom bundle.
    entity_test_delete_bundle($this->customBundle, 'entity_test');

    // Check that the deleted bundle is no longer present in the
    // 'target_bundles' field setting.
    $field_config = FieldConfig::loadByName('node', $this->nodeType->id(), $name);
    $settings = $field_config->getSettings();
    $handler_settings = $settings['entity_test']['handler_settings'];
    $this->assertTrue(empty($handler_settings['target_bundles']));
  }

}
