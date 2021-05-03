<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\webform\Entity\Webform;

/**
 * Tests for webform validate unique.
 *
 * @group webform
 */
class WebformElementValidateUniqueTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_validate_unique'];

  /**
   * Tests element validate unique.
   */
  public function testValidateUnique() {
    $this->drupalLogin($this->rootUser);

    $webform = Webform::load('test_element_validate_unique');

    $edit = [
      'unique_textfield' => '{unique_textfield}',
      'unique_textfield_multiple[items][0][_item_]' => '{unique_textfield_multiple}',
      'unique_user_textfield' => '{unique_user_textfield}',
      'unique_entity_textfield' => '{unique_entity_textfield}',
      'unique_error' => '{unique_error}',
      'unique_error_html' => '{unique_error}',
      'unique_multiple[1]' => TRUE,
    ];

    // Check post submission with default values does not trigger
    // unique errors.
    $sid = $this->postSubmission($webform, $edit);
    $this->assertNoRaw('The value <em class="placeholder">{unique_textfield}</em> has already been submitted once for the <em class="placeholder">unique_textfield</em> element. You may have already submitted this webform, or you need to use a different value.</li>');
    $this->assertNoRaw('unique_textfield_multiple error message.');
    $this->assertNoRaw('unique_user_textfield error message.');
    $this->assertNoRaw('unique_entity_textfield error message.');
    $this->assertNoRaw('unique_error error message.');
    $this->assertNoRaw('unique_error <em>error message</em>.');
    $this->assertNoRaw('unique_multiple error message.');

    // Check post duplicate submission with default values does trigger
    // unique errors.
    $this->postSubmission($webform, $edit);
    $this->assertRaw('The value <em class="placeholder">{unique_textfield}</em> has already been submitted once for the <em class="placeholder">unique_textfield</em> element. You may have already submitted this webform, or you need to use a different value.</li>');
    $this->assertRaw('unique_textfield_multiple error message.');
    $this->assertRaw('unique_user_textfield error message.');
    $this->assertRaw('unique_entity_textfield error message.');
    $this->assertRaw('unique_error error message.');
    $this->assertRaw('unique_error <em>error message</em>.');
    $this->assertRaw('unique_multiple error message.');

    // Check #unique element can be updated.
    $this->drupalPostForm("admin/structure/webform/manage/test_element_validate_unique/submission/$sid/edit", [], 'Save');
    $this->assertNoRaw('The value <em class="placeholder">{unique_textfield}</em> has already been submitted once for the <em class="placeholder">unique_textfield</em> element. You may have already submitted this webform, or you need to use a different value.</li>');
    $this->assertNoRaw('unique_user_textfield error message.');
    $this->assertNoRaw('unique_entity_textfield error message.');
    $this->assertNoRaw('unique_error error message.');
    $this->assertNoRaw('unique_error <em>error message</em>.');
    $this->assertNoRaw('unique_multiple error message.');

    // Check #unique multiple validation within the same element.
    // @see \Drupal\webform\Plugin\WebformElementBase::validateUniqueMultiple
    // Add 2 more items.
    $edit = [
      'unique_textfield_multiple[add][more_items]' => 2,
    ];
    $this->drupalPostForm('/webform/test_element_validate_unique', $edit, 'unique_textfield_multiple_table_add');

    $edit = [
      'unique_textfield_multiple[items][0][_item_]' => '{same}',
      'unique_textfield_multiple[items][2][_item_]' => '{same}',
    ];
    $this->drupalPostForm(NULL, $edit, 'Submit');
    $this->assertRaw('unique_textfield_multiple error message.');

    // Purge existing submissions.
    $this->purgeSubmissions();

    // Check #unique_user triggers for anonymous users.
    $edit = ['unique_user_textfield' => '{unique_user_textfield}'];
    $this->postSubmission($webform, $edit);
    $this->assertNoRaw('unique_user_textfield error message.');
    $this->postSubmission($webform, $edit);
    $this->assertRaw('unique_user_textfield error message.');

    // Create a user that is used as the source entity.
    $account = $this->drupalCreateUser();

    // Check #unique_entity triggers with source entity.
    $edit = ['unique_entity_textfield' => '{unique_entity_textfield}'];
    $options = ['query' => ['source_entity_type' => 'user', 'source_entity_id' => $account->id()]];
    $this->postSubmission($webform, $edit, NULL, $options);
    $this->assertNoRaw('unique_entity_textfield error message.');
    $this->postSubmission($webform, $edit, NULL, $options);
    $this->assertRaw('unique_entity_textfield error message.');

    // Check #unique_entity triggers without source entity.
    $edit = ['unique_entity_textfield' => '{unique_entity_textfield}'];
    $this->postSubmission($webform, $edit);
    $this->assertNoRaw('unique_entity_textfield error message.');
    $this->postSubmission($webform, $edit);
    $this->assertRaw('unique_entity_textfield error message.');
  }

}
