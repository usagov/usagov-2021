<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\diff\Functional\DiffPluginTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests dynamic entity reference diff plugin.
 *
 * @group dynamic_entity_reference
 */
class DiffDynamicEntityReferencePluginTest extends DiffPluginTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'image',
    'field_ui',
    'dynamic_entity_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->adminPermissions);

    // FieldUiTestTrait checks the breadcrumb when adding a field, so we need
    // to show the breadcrumb block.
    $this->drupalPlaceBlock('system_breadcrumb_block');
  }

  /**
   * Tests the DynamicEntityReferenceFieldBuilder plugin.
   *
   * @see \Drupal\dynamic_entity_reference\Plugin\diff\Field\DynamicEntityReferenceFieldBuilder
   */
  public function testDynamicEntityReferencePlugin() {
    // Add an dynamic entity reference field to the article content type.
    $bundle_path = 'admin/structure/types/manage/article';
    $field_name = 'reference';
    $storage_edit = [
      'settings[exclude_entity_types]' => FALSE,
      'settings[entity_type_ids][]' => ['user', 'node'],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ];
    $field_edit = [
      'settings[node][handler_settings][target_bundles][article]' => TRUE,
    ];
    $this->fieldUIAddNewField($bundle_path, $field_name, 'Reference', 'dynamic_entity_reference', $storage_edit, $field_edit);

    // Create three article nodes.
    $node1 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article A',
    ]);
    $node2 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article B',
    ]);
    $node3 = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Article C',
    ]);

    // Reference article B in article A.
    $edit = [
      'field_reference[0][target_type]' => 'node',
      'field_reference[0][target_id]' => 'Article B (' . $node2->id() . ')',
      'field_reference[1][target_type]' => 'user',
      'field_reference[1][target_id]' => 'admin (1)',
      'revision' => TRUE,
    ];
    $this->drupalGet('node/' . $node1->id() . '/edit');
    $button = $this->assertSession()->buttonExists('Add another item');
    $button->click();
    $this->submitForm($edit, t('Save'));

    // Update article A so it points to article C instead of B.
    $edit = [
      'field_reference[0][target_type]' => 'user',
      'field_reference[0][target_id]' => $this->adminUser->label() . ' (' . $this->adminUser->id() . ')',
      'field_reference[1][target_type]' => 'node',
      'field_reference[1][target_id]' => 'Article C (' . $node3->id() . ')',
      'revision' => TRUE,
    ];
    $this->drupalGet('node/' . $node1->id() . '/edit');
    $button = $this->assertSession()->buttonExists('Add another item');
    $button->click();
    $this->submitForm($edit, t('Save'));

    // Check differences between revisions.
    $this->clickLink(t('Revisions'));
    $this->submitForm([], t('Compare selected revisions'));
    $this->assertText('Reference');
    $this->assertText('Article B');
    $this->assertText('Article C');
    $this->assertText('admin');
    $this->assertText($this->adminUser->label());
  }

}
