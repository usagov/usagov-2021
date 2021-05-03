<?php

namespace Drupal\Tests\paragraphs\Functional;

use Drupal\Tests\paragraphs\Functional\Experimental\ParagraphsExperimentalTestBase;

/**
 * Tests the Paragraphs user interface.
 *
 * @group paragraphs
 */
class ParagraphsExperimentalUiTest extends ParagraphsExperimentalTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  public static $modules = [
    'node',
    'paragraphs',
    'field',
    'field_ui',
    'block',
  ];

  /**
   * Tests if the paragraph type class is present when added.
   */
  public function testParagraphTypeClass() {
    $this->loginAsAdmin();
    // Add a Paragraphed test content.
    $this->addParagraphedContentType('paragraphed_test', 'paragraphs');

    $this->addParagraphsType('test_paragraph');
    $this->addParagraphsType('text');

    // Add paragraphs to a node and check if their type is present as a class.
    $this->drupalGet('node/add/paragraphed_test');
    $this->getSession()->getPage()->findButton('paragraphs_test_paragraph_add_more')->press();
    $this->assertSession()->responseContains('paragraph-type--test-paragraph');
    $this->getSession()->getPage()->findButton('paragraphs_text_add_more')->press();
    $this->assertSession()->responseContains('paragraph-type--text');
    $this->getSession()->getPage()->findButton('paragraphs_0_remove')->press();
    $this->assertSession()->responseContains('paragraph-type--text');
  }

  /**
   * Test paragraphs summary with markup text.
   */
  public function testSummary() {
    $this->addParagraphedContentType('paragraphed_test', 'paragraphs');
    $this->addParagraphsType('text');
    $this->addFieldtoParagraphType('text', 'field_text_demo', 'text');
    $this->loginAsAdmin(['edit any paragraphed_test content']);
    $settings = [
      'edit_mode' => 'closed',
      'closed_mode' => 'summary',
    ];
    $this->setParagraphsWidgetSettings('paragraphed_test', 'paragraphs', $settings, 'paragraphs');
    // Create a node and add a paragraph.
    $this->drupalGet('node/add/paragraphed_test');
    $this->getSession()->getPage()->findButton('paragraphs_text_add_more')->press();
    $edit = [
      'title[0][value]' => 'Llama test',
      'paragraphs[0][subform][field_text_demo][0][value]' => '<iframe src="https://www.llamatest.neck"></iframe>',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextContains('paragraphed_test Llama test has been created.');
    // Assert that the summary contains the html text.
    $node = $this->getNodeByTitle('Llama test');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->pageTextContains('<iframe src="https://www.llamatest.neck');
    $this->assertSession()->responseContains('class="paragraphs-description paragraphs-collapsed-description"><div class="paragraphs-content-wrapper"><span class="summary-content">&lt;iframe src=');
    // Assert that the summary keeps showing html even with longer html.
    $this->getSession()->getPage()->pressButton('paragraphs_0_edit');
    $edit = [
      'paragraphs[0][subform][field_text_demo][0][value]' => '<iframe src="https://www.llamatest.neck" class="this-is-a-pretty-long-class-that-needs-to-be-really-long-for-testing-purposes-so-we-have-a-better-summary-test-and-it-has-exactly-144-characters"></iframe>',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextContains('paragraphed_test Llama test has been updated.');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->pageTextContains('<iframe src="https://www.llamatest.neck" class="this-is-a-pretty-long-class-that-needs-to-be-really-long-for-testing-purposes-so-we-');
    $this->assertSession()->responseContains('class="paragraphs-description paragraphs-collapsed-description"><div class="paragraphs-content-wrapper"><span class="summary-content">&lt;iframe src=');
    // Asset that the summary does not display markup even when we have long
    // html.
    $this->getSession()->getPage()->pressButton('paragraphs_0_edit');
    $edit = [
      'paragraphs[0][subform][field_text_demo][0][value]' => '<iframe src="https://www.llamatest.neck" class="this-is-a-pretty-long-class-that-needs-to-be-really-long-for-testing-purposes-so-we-have-a-better-summary-test-and-it-has-exactly-144-characters"></iframe><h1>This is a title</h1>',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->responseContains('class="paragraphs-description paragraphs-collapsed-description"><div class="paragraphs-content-wrapper"><span class="summary-content">This is a title');
  }

}
