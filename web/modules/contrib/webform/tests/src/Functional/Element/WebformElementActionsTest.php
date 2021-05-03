<?php

namespace Drupal\Tests\webform\Functional\Element;

use Drupal\webform\Entity\Webform;

/**
 * Tests for webform actions element.
 *
 * @group webform
 */
class WebformElementActionsTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_actions', 'test_element_actions_buttons'];

  /**
   * Tests actions element.
   */
  public function testActions() {
    global $base_path;

    $webform = Webform::load('test_element_actions');

    /**************************************************************************/
    /* Test webform actions */
    /**************************************************************************/

    // Get form.
    $this->drupalGet('/webform/test_element_actions');

    // Check custom actions.
    $this->assertRaw('<div style="border: 2px solid red; padding: 10px" data-drupal-selector="edit-actions-custom" class="form-actions webform-actions js-form-wrapper form-wrapper" id="edit-actions-custom">');
    $this->assertRaw('<input formnovalidate="formnovalidate" class="webform-button--draft custom-draft button js-form-submit form-submit" style="font-weight: bold" data-custom-draft data-drupal-selector="edit-actions-custom-draft" type="submit" id="edit-actions-custom-draft" name="op" value="{Custom draft}" />');
    $this->assertRaw('<input class="webform-button--next custom-wizard-next button js-form-submit form-submit" style="font-weight: bold" data-custom-wizard-next data-drupal-selector="edit-actions-custom-wizard-next" type="submit" id="edit-actions-custom-wizard-next" name="op" value="{Custom wizard next}" />');
    $this->assertRaw('<input formnovalidate="formnovalidate" class="webform-button--reset custom-reset button js-form-submit form-submit" style="font-weight: bold" data-custom-reset data-drupal-selector="edit-actions-custom-reset" type="submit" id="edit-actions-custom-reset" name="op" value="{Custom reset}" />');

    // Check wizard next.
    $this->assertCssSelect('[id="edit-actions-wizard-next-wizard-next"]');
    $this->assertNoCssSelect('[id="edit-actions-wizard-prev-wizard-prev"]');

    // Move to next page.
    $this->drupalPostForm(NULL, [], 'Next >');

    // Check no wizard next.
    $this->assertNoCssSelect('[id="edit-actions-wizard-next-wizard-next"]');
    $this->assertCssSelect('[id="edit-actions-wizard-prev-wizard-prev"]');

    // Move to preview.
    $this->drupalPostForm(NULL, [], 'Preview');

    // Check submit button.
    $this->assertCssSelect('[id="edit-actions-submit-submit"]');

    // Check reset button.
    $this->assertCssSelect('[id="edit-actions-reset-reset"]');

    // Submit form.
    $this->drupalPostForm(NULL, [], 'Submit');
    $sid = $this->getLastSubmissionId($webform);

    // Check no actions.
    $this->assertNoCssSelect('.form-actions');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Check custom update action.
    $this->drupalGet("/admin/structure/webform/manage/test_element_actions/submission/$sid/edit");
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertRaw('<input class="webform-button--submit custom-update button button--primary js-form-submit form-submit" style="font-weight: bold" data-custom-update data-drupal-selector="edit-actions-custom-submit" type="submit" id="edit-actions-custom-submit" name="op" value="{Custom update}" />');

    // Check custom delete action.
    $this->drupalPostForm('/webform/test_element_actions', [], 'Save Draft');
    $sid = $this->getLastSubmissionId($webform);
    $this->assertRaw('<a href="' . $base_path . 'admin/structure/webform/manage/test_element_actions/submission/' . $sid . '/delete?destination=' . $base_path . 'webform/test_element_actions" class="button button--danger custom-delete" style="font-weight: bold" data-custom-delete data-drupal-selector="edit-actions-custom-delete" id="edit-actions-custom-delete" hreflang="en">{Custom delete}</a>');
    $this->assertCssSelect('[id="edit-actions-delete"]');

    /**************************************************************************/
    /* Test actions buttons */
    /**************************************************************************/

    $webform = Webform::load('test_element_actions_buttons');

    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/webform/test_element_actions_buttons');

    // Check draft button.
    $this->assertRaw('<input formnovalidate="formnovalidate" class="webform-button--draft draft_button_attributes button js-form-submit form-submit" style="color: blue" data-drupal-selector="edit-actions-draft" type="submit" id="edit-actions-draft" name="op" value="Save Draft" />');
    // Check next button.
    $this->assertRaw('<input class="webform-button--next wizard_next_button_attributes button js-form-submit form-submit" style="color: yellow" data-drupal-selector="edit-actions-wizard-next" type="submit" id="edit-actions-wizard-next" name="op" value="Next &gt;" />');

    $this->drupalPostForm('/webform/test_element_actions_buttons', [], 'Next >');

    // Check previous button.
    $this->assertRaw('<input formnovalidate="formnovalidate" class="webform-button--previous wizard_prev_button_attributes button js-form-submit form-submit" style="color: yellow" data-drupal-selector="edit-actions-wizard-prev" type="submit" id="edit-actions-wizard-prev" name="op" value="&lt; Previous" />');
    // Check preview button.
    $this->assertRaw('<input class="webform-button--preview preview_next_button_attributes button js-form-submit form-submit" style="color: orange" data-drupal-selector="edit-actions-preview-next" type="submit" id="edit-actions-preview-next" name="op" value="Preview" />');

    $this->drupalPostForm(NULL, [], 'Preview');

    // Check previous button.
    $this->assertRaw('<input formnovalidate="formnovalidate" class="webform-button--previous preview_prev_button_attributes button js-form-submit form-submit" style="color: orange" data-drupal-selector="edit-actions-preview-prev" type="submit" id="edit-actions-preview-prev" name="op" value="&lt; Previous" />');
    // Check submit button.
    $this->assertRaw('<input class="webform-button--submit form_submit_attributes button button--primary js-form-submit form-submit" style="color: green" data-drupal-selector="edit-actions-submit" type="submit" id="edit-actions-submit" name="op" value="Submit" />');

    $this->drupalPostForm(NULL, [], 'Submit');
    $sid = $this->getLastSubmissionId($webform);

    // Check update button.
    $this->drupalGet("/admin/structure/webform/manage/test_element_actions_buttons/submission/$sid/edit");
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertRaw('<input class="webform-button--submit form_update_attributes button button--primary js-form-submit form-submit" style="color: purple" data-drupal-selector="edit-actions-submit" type="submit" id="edit-actions-submit" name="op" value="Save" />');
  }

}
