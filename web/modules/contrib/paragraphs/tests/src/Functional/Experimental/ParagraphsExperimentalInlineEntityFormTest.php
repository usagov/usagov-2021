<?php

namespace Drupal\Tests\paragraphs\Functional\Experimental;

use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Tests the configuration of paragraphs in relation to ief.
 *
 * @group paragraphs
 */
class ParagraphsExperimentalInlineEntityFormTest extends ParagraphsExperimentalTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'inline_entity_form',
  ];

  /**
   * Tests the revision of paragraphs.
   */
  public function testParagraphsIEFPreview() {
    // Create article content type with a paragraphs field.
    $this->addParagraphedContentType('article');
    $this->loginAsAdmin(['create article content', 'edit any article content']);

    // Create the paragraphs type simple.
    $this->addParagraphsType('simple');
    $this->addParagraphsType('text');

    // Create a reference to an article.
    $this->fieldUIAddNewField('admin/structure/paragraphs_type/simple', 'article', 'Article', 'field_ui:entity_reference:node', [
      'settings[target_type]' => 'node',
      'cardinality' => 'number',
      'cardinality_number' => 1,
    ], [
      'required' => TRUE,
      'settings[handler_settings][target_bundles][article]' => TRUE
    ]);

    // Enable IEF simple widget.
    $this->drupalGet('admin/structure/paragraphs_type/simple/form-display');
    $edit = [
      'fields[field_article][type]' => 'inline_entity_form_simple',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Set the paragraphs widget edit mode to "Closed" and the closed mode to
    // "Preview".
    $settings = [
      'edit_mode' => 'closed',
      'closed_mode' => 'preview',
    ];
    $this->setParagraphsWidgetSettings('article', 'field_paragraphs', $settings);

    // Create node with one paragraph.
    $this->drupalGet('node/add/article');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_simple_add_more');

    // Set the values and save.
    $edit = [
      'title[0][value]' => 'Dummy1',
      'field_paragraphs[0][subform][field_article][0][inline_entity_form][title][0][value]' => 'Dummy2',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Go back into edit page.
    $node = $this->getNodeByTitle('Dummy1');
    $this->drupalGet('node/' . $node->id() . '/edit');

    // Try to open the previewed paragraph.
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_edit');
  }

  /**
   * Tests the reordering of previewed paragraphs.
   */
  public function testParagraphsIEFChangeOrder() {
    // Create article content type with a paragraphs field.
    $this->addParagraphedContentType('article');
    $this->loginAsAdmin(['create article content', 'edit any article content']);

    // Create the paragraphs type simple.
    $this->addParagraphsType('simple');
    $this->addParagraphsType('text');

    // Create a reference to an article.
    $this->fieldUIAddNewField('admin/structure/paragraphs_type/simple', 'article', 'Article', 'field_ui:entity_reference:node', [
      'settings[target_type]' => 'node',
      'cardinality' => 'number',
      'cardinality_number' => '1',
    ], [
      'required' => TRUE,
      'settings[handler_settings][target_bundles][article]' => TRUE
    ]);

    // Set cardinality explicit to -1.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_paragraphs/storage');
    $edit = [
      'settings[target_type]' => 'paragraph',
      'cardinality' => '-1',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save field settings'));

    // Enable IEF simple widget.
    $this->drupalGet('admin/structure/paragraphs_type/simple/form-display');
    $edit = [
      'fields[field_article][type]' => 'inline_entity_form_simple',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Set the paragraphs widget closed mode to preview.
    $this->setParagraphsWidgetSettings('article', 'field_paragraphs', ['closed_mode' => 'preview']);

    // Create node with one paragraph.
    $this->drupalGet('node/add/article');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_simple_add_more');

    // Set the values and save.
    $edit = [
      'title[0][value]' => 'Article 1',
      'field_paragraphs[0][subform][field_article][0][inline_entity_form][title][0][value]' => 'Basic page 1',
    ];

    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Go back into edit page.
    $node = $this->getNodeByTitle('Article 1');
    $this->drupalGet('node/' . $node->id() . '/edit');

    // Create second paragraph.
    $this->drupalPostForm(NULL, [], 'field_paragraphs_simple_add_more');

    // Set the values of second paragraph.
    $edit = [
      'field_paragraphs[1][subform][field_article][0][inline_entity_form][title][0][value]' => 'Basic 2'
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
  }

}
