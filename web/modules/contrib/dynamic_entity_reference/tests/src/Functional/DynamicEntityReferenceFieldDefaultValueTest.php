<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\SchemaCheckTestTrait;

/**
 * Tests dynamic entity reference field default values storage in CMI.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceFieldDefaultValueTest extends BrowserTestBase {
  use SchemaCheckTestTrait;

  /**
   * A user with permission to administer content types, node fields, etc.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'dynamic_entity_reference',
    'field_ui',
    'node',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access content',
    'administer content types',
    'administer node fields',
    'administer node form display',
    'bypass node access',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create default content type.
    $this->drupalCreateContentType(['type' => 'reference_content']);
    $this->drupalCreateContentType(['type' => 'referenced_content']);

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that default values are correctly translated to UUIDs in config.
   */
  public function testEntityReferenceDefaultValue() {
    $assert_session = $this->assertSession();
    // Create a node to be referenced.
    $referenced_node = $this->drupalCreateNode(['type' => 'referenced_content']);

    $field_name = mb_strtolower($this->randomMachineName());
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'dynamic_entity_reference',
      'settings' => [
        'exclude_entity_types' => FALSE,
        'entity_type_ids' => [
          'node',
          'user',
        ],
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'reference_content',
      'settings' => [
        'node' => [
          'handler' => 'default',
          'handler_settings' => [
            'target_bundles' => ['referenced_content'],
            'sort' => ['field' => '_none'],
          ],
        ],
        'user' => [
          'handler' => 'default:user',
          'handler_settings' => [
            'target_bundles' => NULL,
          ],
        ],
      ],
    ])->save();

    // Set created node as default_value.
    $field_edit = [
      'default_value_input[' . $field_name . '][0][target_type]' => $referenced_node->getEntityTypeId(),
      'default_value_input[' . $field_name . '][0][target_id]' => $referenced_node->getTitle() . ' (' . $referenced_node->id() . ')',
    ];
    $this->drupalGet('admin/structure/types/manage/reference_content/fields/node.reference_content.' . $field_name);
    $this->submitForm($field_edit, t('Save settings'));

    // Check that default value is selected in default value form.
    $this->drupalGet('admin/structure/types/manage/reference_content/fields/node.reference_content.' . $field_name);
    $assert_session->optionExists("default_value_input[$field_name][0][target_type]", $referenced_node->getEntityTypeId());
    $assert_session->responseContains('name="default_value_input[' . $field_name . '][0][target_id]" value="' . $referenced_node->getTitle() . ' (' . $referenced_node->id() . ')');

    // Check if the ID has been converted to UUID in config entity.
    $config_entity = $this->config('field.field.node.reference_content.' . $field_name)->get();
    $this->assertTrue(isset($config_entity['default_value'][0]['target_uuid']), 'Default value contains target_uuid property');
    $this->assertEquals($config_entity['default_value'][0]['target_uuid'], $referenced_node->uuid(), 'Content uuid and config entity uuid are the same');
    // Ensure the configuration has the expected dependency on the entity that
    // is being used a default value.
    $this->assertEquals([$referenced_node->getConfigDependencyName()], $config_entity['dependencies']['content']);

    // Clear field definitions cache in order to avoid stale cache values.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Create a new node to check that UUID has been converted to numeric ID.
    $new_node = Node::create(['type' => 'reference_content']);
    $this->assertEquals($new_node->get($field_name)->offsetGet(0)->target_type, $referenced_node->getEntityTypeId());
    $this->assertEquals($new_node->get($field_name)->offsetGet(0)->target_id, $referenced_node->id());

    // Ensure that the entity reference config schemas are correct.
    $field_config = $this->config('field.field.node.reference_content.' . $field_name);
    $this->assertConfigSchema(\Drupal::service('config.typed'), $field_config->getName(), $field_config->get());
    $field_storage_config = $this->config('field.storage.node.' . $field_name);
    $this->assertConfigSchema(\Drupal::service('config.typed'), $field_storage_config->getName(), $field_storage_config->get());
  }

}
