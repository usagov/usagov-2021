<?php

namespace Drupal\Tests\scanner\Functional;

/**
 * Tests the default admin settings functionality.
 *
 * @group scanner
 */
class ScannerAdminTest extends ScannerTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    // Make sure to complete the normal setup steps first.
    parent::setUp();

    // Create a test content types.
    $this->createContentTypeNode('Title test', 'Body test', 'scanner_test_node_type', 'Scanner test node type');
    // Node content type that will be ignored.
    $this->createContentTypeNode('Ignored title', 'Ignored body', 'scanner_test_ignored_node_type', 'Scanner test ignored node type');

    // Create test Paragraphs content and its fields.
    $paragraphs_type_id = 'scanner_test_paragraph_type';
    $this->addParagraphsType($paragraphs_type_id);
    $this->addFieldtoParagraphType($paragraphs_type_id, 'field_string', 'string');
    $this->addFieldtoParagraphType($paragraphs_type_id, 'field_text', 'text');
    $this->addFieldtoParagraphType($paragraphs_type_id, 'field_text_long', 'text_long');
    $this->addFieldtoParagraphType($paragraphs_type_id, 'field_text_with_summary', 'text_with_summary');

    // Add an unsupported field.
    $this->addFieldtoParagraphType($paragraphs_type_id, 'field_boolean', 'boolean');

    // Add a Paragraphs field to the node content type.
    $this->addParagraphsField('scanner_test_node_type', 'scanner_paragraphs_field', 'node');

    // Create user with the appropriate admin role.
    $user = $this->createUser(['administer scanner settings']);
    $this->drupalLogin($user);
  }

  /**
   * Tests that the base admin settings form is functional.
   */
  public function testAdminSettingsForm() {
    // Confirm the settings form loads.
    $this->drupalGet('admin/config/content/scanner');

    $session_assert = $this->assertSession();

    $session_assert->statusCodeEquals(200);

    // Check for all checkboxes including the test content type.
    $session_assert->fieldExists('scanner_mode');
    $session_assert->fieldExists('scanner_wholeword');
    $session_assert->fieldExists('scanner_regex');
    $session_assert->fieldExists('scanner_published');
    $session_assert->fieldExists('scanner_pathauto');
    $session_assert->fieldExists('scanner_language');

    // Assert that the entity types are available but not enabled by default.
    $session_assert->checkboxNotChecked('enabled_content_types[node:scanner_test_node_type]');
    $session_assert->checkboxNotChecked('enabled_content_types[node:scanner_test_ignored_node_type]');
  }

  /**
   * Tests that the supported entity fields are present.
   */
  public function testAdminSettingsEntityTypeFields() {
    // Confirm the settings form loads.
    $this->drupalGet('admin/config/content/scanner');

    $session_assert = $this->assertSession();

    // Enable support for the "scanner_test_node_type" and
    // "scanner_test_paragraph_type" entity types.
    $edit = [
      'enabled_content_types[node:scanner_test_node_type]' => 'node:scanner_test_node_type',
      'enabled_content_types[paragraph:scanner_test_paragraph_type]' => 'paragraph:scanner_test_paragraph_type',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Assert that the enabled entity type is now checked.
    $session_assert->checkboxChecked('enabled_content_types[node:scanner_test_node_type]');

    // Assert that the node title and body fields are available.
    $session_assert->fieldExists('fields_of_selected_content_type[node:scanner_test_node_type:title]');
    $session_assert->fieldExists('fields_of_selected_content_type[node:scanner_test_node_type:body]');

    // Assert that the paragraph text fields are available.
    $session_assert->fieldExists('fields_of_selected_content_type[paragraph:scanner_test_paragraph_type:field_string]');
    $session_assert->fieldExists('fields_of_selected_content_type[paragraph:scanner_test_paragraph_type:field_text]');
    $session_assert->fieldExists('fields_of_selected_content_type[paragraph:scanner_test_paragraph_type:field_text_long]');
    $session_assert->fieldExists('fields_of_selected_content_type[paragraph:scanner_test_paragraph_type:field_text_with_summary]');

    // Assert that unsupported fields are not available.
    $session_assert->fieldNotExists('fields_of_selected_content_type[paragraph:scanner_test_paragraph_type:field_boolean]');

    // Assert that disabled entity type fields are not available.
    $session_assert->fieldNotExists('fields_of_selected_content_type[node:scanner_test_ignored_node_type:title]');
    $session_assert->fieldNotExists('fields_of_selected_content_type[node:scanner_test_ignored_node_type:body]');
  }

}
