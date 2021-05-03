<?php

namespace Drupal\Tests\paragraphs\Functional\Experimental;

use Drupal\block_content\Entity\BlockContent;

/**
 * Tests paragraphs edit modes.
 *
 * @group paragraphs
 */
class ParagraphsExperimentalEditModesTest extends ParagraphsExperimentalTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'image',
    'block_field',
    'block_content',
    'link',
    'field_ui'
  ];

  /**
   * Tests the collapsed summary of paragraphs.
   */
  public function testCollapsedSummary() {
    $this->addParagraphedContentType('paragraphed_test');
    $this->loginAsAdmin(['create paragraphed_test content', 'edit any paragraphed_test content']);

    // Add a Paragraph type.
    $paragraph_type = 'image_text_paragraph';
    $this->addParagraphsType($paragraph_type);
    $title_paragraphs_type = 'title';
    $this->addParagraphsType($title_paragraphs_type);
    $this->addParagraphsType('text');
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'image', 'Image', 'image', [], ['settings[alt_field_required]' => FALSE]);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'text', 'Text', 'text_long', [], []);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $title_paragraphs_type, 'title', 'Title', 'string', [], []);

    // Add a user Paragraph Type
    $paragraph_type = 'user_paragraph';
    $this->addParagraphsType($paragraph_type);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'user', 'User', 'entity_reference', ['settings[target_type]' => 'user'], []);

    // Set edit mode to closed.
    $this->drupalGet('admin/structure/types/manage/paragraphed_test/form-display');
    $this->drupalPostForm(NULL, [], "field_paragraphs_settings_edit");
    $edit = ['fields[field_paragraphs][settings_edit_form][settings][edit_mode]' => 'closed'];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Add a paragraph.
    $this->drupalPostForm('node/add/paragraphed_test', [], 'field_paragraphs_image_text_paragraph_add_more');
    $this->drupalPostForm(NULL, NULL, 'field_paragraphs_title_add_more');

    $files = $this->getTestFiles('image');
    $file_system = \Drupal::service('file_system');

    // Create a node with an image and text.
    $edit = [
      'title[0][value]' => 'Test article',
      'field_paragraphs[0][subform][field_text][0][value]' => 'text_summary',
      'files[field_paragraphs_0_subform_field_image_0]' => $file_system->realpath($files[0]->uri),
      'field_paragraphs[1][subform][field_title][0][value]' => 'Title example',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->clickLink(t('Edit'));
    $this->drupalPostForm(NULL, [], t('Add user_paragraph'));
    $edit = [
      'field_paragraphs[2][subform][field_user][0][target_id]' => $this->admin_user->label() . ' (' . $this->admin_user->id() . ')',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Assert the summary is correctly generated.
    $this->clickLink(t('Edit'));
    $this->assertSession()->responseContains('<span class="summary-content">' . $files[0]->filename . '</span>, <span class="summary-content">text_summary</span>');
    $this->assertSession()->responseContains('<span class="summary-content">' . $this->admin_user->label());
    $this->assertSession()->responseContains('<span class="summary-content">Title example');

    // Edit and remove alternative text.
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_edit');
    $edit = [
      'field_paragraphs[0][subform][field_image][0][alt]' => 'alternative_text_summary',
    ];
    $this->drupalPostForm(NULL, $edit, 'field_paragraphs_0_collapse');
    // Assert the summary is correctly generated.
    $this->assertSession()->responseContains('<span class="summary-content">alternative_text_summary</span>, <span class="summary-content">text_summary</span>');

    // Remove image.
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_edit');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_subform_field_image_0_remove_button');
    $this->drupalPostForm(NULL, [], t('Save'));

    // Assert the summary is correctly generated.
    $this->clickLink(t('Edit'));
    $this->assertSession()->responseContains('<span class="summary-content">text_summary');

    $this->addParagraphsType('nested_paragraph');
    static::fieldUIAddNewField('admin/structure/paragraphs_type/nested_paragraph', 'nested_content', 'Nested Content', 'entity_reference_revisions', ['settings[target_type]' => 'paragraph'], []);
    $this->drupalGet('admin/structure/paragraphs_type/nested_paragraph/form-display');
    $this->drupalPostForm(NULL, ['fields[field_nested_content][type]' => 'entity_reference_paragraphs'], t('Save'));

    $test_user = $this->drupalCreateUser([]);

    $this->drupalGet('node/add/paragraphed_test');
    $this->drupalPostForm(NULL, NULL, t('Add nested_paragraph'));
    $this->drupalPostForm(NULL, NULL, t('field_paragraphs_0_subform_field_nested_content_user_paragraph_add_more'));
    $edit = [
      'title[0][value]' => 'Node title',
      'field_paragraphs[0][subform][field_nested_content][0][subform][field_user][0][target_id]' => $test_user->label() . ' (' . $test_user->id() . ')',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Create an orphaned ER field item by deleting the target entity.
    $test_user->delete();

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'Node title']);
    $this->drupalGet('node/' . current($nodes)->id() . '/edit');
    $this->drupalPostForm(NULL, [], t('field_paragraphs_0_edit'));
    $this->drupalPostForm(NULL, [], t('field_paragraphs_0_collapse'));
    $this->assertSession()->statusCodeEquals(200);

    // Add a Block Paragraphs type.
    $this->addParagraphsType('block_paragraph');
    $this->addFieldtoParagraphType('block_paragraph', 'field_block', 'block_field');

    // Test the summary of a Block field.
    $after_block2 = BlockContent::create([
      'info' => 'Llama custom block',
      'type' => 'basic_block',
    ]);
    $after_block2->save();

    $this->placeBlock($after_block2->id());

    $this->drupalGet('node/add/paragraphed_test');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_block_paragraph_add_more');
    $edit = [
      'field_paragraphs[0][subform][field_block][0][plugin_id]' => 'block_content:' . $after_block2->uuid(),
    ];
    $this->drupalPostForm(NULL, $edit, 'field_paragraphs_0_collapse');
    $this->assertSession()->responseContains('<span class="summary-content">Llama custom block');
    $edit = ['title[0][value]' => 'Test llama block'];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Delete the block.
    $after_block2->delete();
    // Attempt to edit the node when the node is deleted.
    $node = $this->getNodeByTitle('Test llama block');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Test the summary of a Block field.
    $paragraph_type = 'link_paragraph';
    $this->addParagraphsType($paragraph_type);
    static::fieldUIAddNewField('admin/structure/paragraphs_type/' . $paragraph_type, 'link', 'Link', 'link', [], []);
    // Create a node with a link paragraph.
    $this->drupalPostForm('node/add/paragraphed_test', [], 'field_paragraphs_link_paragraph_add_more');
    $edit = [
      'title[0][value]' => 'Test link',
      'field_paragraphs[0][subform][field_link][0][uri]' => 'http://www.google.com',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Check the summary when no link title is provided.
    $this->clickLink(t('Edit'));
    $this->assertSession()->responseContains('<span class="summary-content">http://www.google.com');
    // Set a link title.
    $this->drupalPostForm(NULL, NULL, 'field_paragraphs_0_edit');
    $edit = [
      'field_paragraphs[0][subform][field_link][0][title]' => 'Link title',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    // Check the summary when the link title is set.
    $this->clickLink(t('Edit'));
    $this->assertSession()->responseContains('<span class="summary-content">Link title');

    // Allow the user to select if the paragraphs is published or not.
    $edit = [
      'fields[status][region]' => 'content',
      'fields[status][type]' => 'boolean_checkbox'
    ];
    $this->drupalPostForm('admin/structure/paragraphs_type/' . $title_paragraphs_type . '/form-display', $edit, 'Save');
    $edit = [
      'fields[field_nested_content][type]' => 'paragraphs',
      'fields[status][region]' => 'content',
      'fields[status][type]' => 'boolean_checkbox'
    ];
    $this->drupalPostForm('admin/structure/paragraphs_type/nested_paragraph/form-display', $edit, 'Save');

    // Add a unpublished text paragraph and check its summary when unpublished.
    $this->drupalPostForm('node/add/paragraphed_test', [], 'field_paragraphs_title_add_more');
    $edit = [
      'title[0][value]' => 'Access summary test',
      'field_paragraphs[0][subform][field_title][0][value]' => 'memorable_summary_title',
      'field_paragraphs[0][subform][status][value]' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextNotContains('memorable_summary_title');
    $node = $this->getNodeByTitle('Access summary test');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->responseContains('<span class="summary-content">memorable_summary_title');
    $this->assertEquals(1, count($this->xpath("//*[contains(@class, 'paragraphs-icon-view')]")));

    $this->drupalPostForm('node/add/paragraphed_test', [], 'field_paragraphs_nested_paragraph_add_more');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_subform_field_nested_content_title_add_more');

    // Add a nested paragraph and with the parent unpublished, check the
    // summary.
    $edit = [
      'title[0][value]' => 'Access nested summary test',
      'field_paragraphs[0][subform][status][value]' => FALSE,
      'field_paragraphs[0][subform][field_nested_content][0][subform][status][value]' => FALSE,
      'field_paragraphs[0][subform][field_nested_content][0][subform][field_title][0][value]' => 'memorable_nested_summary_title',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextNotContains('memorable_nested_summary_title');
    $node = $this->getNodeByTitle('Access nested summary test');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->responseContains('<span class="summary-content">memorable_nested_summary_title');
    $this->assertEquals(1, count($this->xpath("//*[contains(@class, 'paragraphs-icon-view')]")));
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_edit');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_subform_field_nested_content_0_collapse');
    $this->assertSession()->responseContains('<span class="summary-content">memorable_nested_summary_title');
    $this->assertEquals(1, count($this->xpath("//*[contains(@class, 'paragraphs-icon-view')]")));

    // Assert the unpublished icon.
    $permissions = [
      'edit any paragraphed_test content',
    ];
    $this->loginAsAdmin($permissions);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->responseContains('<span class="summary-content">memorable_nested_summary_title');
    $this->assertEquals(1, count($this->xpath("//*[contains(@class, 'paragraphs-icon-view')]")));
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_edit');
    $this->drupalPostForm(NULL, [], 'field_paragraphs_0_subform_field_nested_content_0_collapse');
    $this->assertSession()->responseContains('<span class="summary-content">memorable_nested_summary_title');
    $this->assertEquals(1, count($this->xpath("//*[contains(@class, 'paragraphs-icon-view')]")));
  }

}
