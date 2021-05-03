<?php

namespace Drupal\Tests\webform\Functional\States;

use Drupal\webform\Entity\Webform;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Tests for webform states wizard server.
 *
 * @group webform
 */
class WebformStatesWizardTest extends WebformBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = [
    'test_states_server_wizard',
  ];

  /**
   * Tests webform submission conditions (#states) validator wizard cross-page conditions.
   */
  public function testFormStatesValidatorWizard() {
    $webform = Webform::load('test_states_server_wizard');

    /**************************************************************************/

    // Go to default #states for page 02 with trigger-checkbox unchecked.
    $this->postSubmission($webform, [], 'Next >');

    $this->assertRaw("page_01_trigger_checkbox: 0
page_01_textfield_required: '{default_value}'
page_01_textfield_optional: '{default_value}'
page_01_textfield_disabled: ''
page_01_textfield_enabled: ''
page_01_textfield_visible: ''
page_01_textfield_invisible: ''
page_01_checkbox_checked: 0
page_01_checkbox_unchecked: 0
page_02_textfield_required: '{default_value}'
page_02_textfield_optional: '{default_value}'
page_02_textfield_disabled: ''
page_02_textfield_enabled: ''
page_02_textfield_visible: '{default_value}'
page_02_textfield_visible_slide: '{default_value}'
page_02_textfield_invisible: '{default_value}'
page_02_textfield_invisible_slide: '{default_value}'
page_02_checkbox_checked: 0
page_02_checkbox_unchecked: 0
page_03_textfield_required: '{default_value}'
page_01_trigger_checkbox_computed: 'No'");

    // Check trigger-checkbox value is No.
    $this->assertFieldByName('page_01_trigger_checkbox_computed', 'No');

    // Check page_02_textfield_required is not required.
    $this->assertNoCssSelect('[name="page_02_textfield_required"][required]');

    // Check page_02_textfield_optional is required.
    $this->assertCssSelect('[name="page_02_textfield_optional"][required]');

    // Check page_02_textfield_disabled is not disabled.
    $this->assertNoCssSelect('[name="page_02_textfield_disabled"][disabled]');

    // Check page_02_textfield_enabled is disabled.
    $this->assertCssSelect('[name="page_02_textfield_enabled"][disabled]');

    // Check page_02_textfield_visible is hidden via .js-webform-states-hidden.
    $this->assertCssSelect('.form-item-page-02-textfield-visible.js-webform-states-hidden');

    // Check page_02_textfield_visible_slide is hidden via .js-webform-states-hidden.
    $this->assertCssSelect('.form-item-page-02-textfield-visible-slide.js-webform-states-hidden');

    // Check page_02_textfield_invisible is visible.
    $this->assertFieldByName('page_02_textfield_invisible', '{default_value}');

    // Check page_02_textfield_invisible_slide is visible.
    $this->assertFieldByName('page_02_textfield_invisible_slide', '{default_value}');

    // Check page_02_checkbox_checked is not checked.
    $this->assertNoFieldChecked('page_02_checkbox_checked');

    // Check page_02_checkbox_unchecked is checked.
    $this->assertFieldChecked('page_02_checkbox_unchecked');

    // Check page_02_details_expanded is not open.
    $this->assertCssSelect('details[id="edit-page-02-details-expanded"]');
    $this->assertNoCssSelect('details[id="edit-page-02-details-expanded"][open]');

    // Check page_02_details_collapsed is open.
    $this->assertCssSelect('details[id="edit-page-02-details-collapsed"][open]');

    // Check submission data.
    $this->drupalPostForm(NULL, [], 'Submit');
    $this->assertRaw("page_01_trigger_checkbox: 0
page_01_textfield_required: '{default_value}'
page_01_textfield_optional: '{default_value}'
page_01_textfield_disabled: ''
page_01_textfield_enabled: ''
page_01_textfield_visible: ''
page_01_textfield_invisible: ''
page_01_checkbox_checked: 0
page_01_checkbox_unchecked: 0
page_02_textfield_required: '{default_value}'
page_02_textfield_optional: '{default_value}'
page_02_textfield_disabled: ''
page_02_textfield_enabled: ''
page_02_textfield_visible: ''
page_02_textfield_visible_slide: ''
page_02_textfield_invisible: '{default_value}'
page_02_textfield_invisible_slide: '{default_value}'
page_02_checkbox_checked: 0
page_02_checkbox_unchecked: 1
page_03_textfield_required: ''
page_01_trigger_checkbox_computed: 'No'");

    /**************************************************************************/

    // Go to default #states for page 02 with trigger_checkbox checked.
    $this->postSubmission($webform, ['page_01_trigger_checkbox' => TRUE], 'Next >');

    $this->assertRaw("page_01_trigger_checkbox: 1
page_01_textfield_required: '{default_value}'
page_01_textfield_optional: '{default_value}'
page_01_textfield_disabled: ''
page_01_textfield_enabled: ''
page_01_textfield_visible: ''
page_01_textfield_invisible: ''
page_01_checkbox_checked: 0
page_01_checkbox_unchecked: 0
page_02_textfield_required: '{default_value}'
page_02_textfield_optional: '{default_value}'
page_02_textfield_disabled: ''
page_02_textfield_enabled: ''
page_02_textfield_visible: '{default_value}'
page_02_textfield_visible_slide: '{default_value}'
page_02_textfield_invisible: '{default_value}'
page_02_textfield_invisible_slide: '{default_value}'
page_02_checkbox_checked: 0
page_02_checkbox_unchecked: 0
page_03_textfield_required: '{default_value}'
page_01_trigger_checkbox_computed: 'Yes'");

    // Check trigger-checkbox value is Yes.
    $this->assertCssSelect('[name="page_01_trigger_checkbox_computed"][value="Yes"]');

    // Check page_02_textfield_required is required.
    $this->assertCssSelect('[name="page_02_textfield_required"][required]');

    // Check page_02_textfield_optional is not required.
    $this->assertNoCssSelect('[name="page_02_textfield_optional"][required]');

    // Check page_02_textfield_disabled is disabled.
    $this->assertCssSelect('[name="page_02_textfield_disabled"][disabled="disabled"]');

    // Check page_02_textfield_enabled is not disabled.
    $this->assertNoCssSelect('[name="page_02_textfield_enabled"][disabled="disabled"]');

    // Check page_02_textfield_visible is visible.
    $this->assertFieldByName('page_02_textfield_visible', '{default_value}');

    // Check page_02_textfield_visible_slide is visible.
    $this->assertFieldByName('page_02_textfield_visible_slide', '{default_value}');

    // Check page_02_textfield_invisible is hidden with no default value.
    $this->assertCssSelect('.form-item-page-02-textfield-invisible.js-webform-states-hidden');
    $this->assertNoFieldByName('page_02_textfield_invisible', '{default_value}');
    $this->assertFieldByName('page_02_textfield_invisible', '');

    // Check page_02_textfield_invisible_slides is hidden with no default value.
    $this->assertCssSelect('.form-item-page-02-textfield-invisible-slide.js-webform-states-hidden');
    $this->assertNoFieldByName('page_02_textfield_invisible_slide', '{default_value}');
    $this->assertFieldByName('page_02_textfield_invisible_slide', '');

    // Check page_02_checkbox_checked is checked.
    $this->assertFieldChecked('page_02_checkbox_checked');

    // Check page_02_checkbox_unchecked is not checked.
    $this->assertNoFieldChecked('page_02_checkbox_unchecked');

    // Check page_02_details_expanded is open.
    $this->assertCssSelect('details[id="edit-page-02-details-expanded"][open]');

    // Check page_02_details_collapsed is not open.
    $this->assertNoCssSelect('details[id="edit-page-02-details-collapsed"][open]');

    // Check third page data.
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertRaw("page_01_trigger_checkbox: 1
page_01_textfield_required: '{default_value}'
page_01_textfield_optional: '{default_value}'
page_01_textfield_disabled: ''
page_01_textfield_enabled: ''
page_01_textfield_visible: ''
page_01_textfield_invisible: ''
page_01_checkbox_checked: 0
page_01_checkbox_unchecked: 0
page_02_textfield_required: '{default_value}'
page_02_textfield_optional: '{default_value}'
page_02_textfield_disabled: ''
page_02_textfield_enabled: ''
page_02_textfield_visible: '{default_value}'
page_02_textfield_visible_slide: '{default_value}'
page_02_textfield_invisible: ''
page_02_textfield_invisible_slide: ''
page_02_checkbox_checked: 1
page_02_checkbox_unchecked: 0
page_03_textfield_required: '{default_value}'
page_01_trigger_checkbox_computed: 'Yes'");

    // Check submission data.
    $this->drupalPostForm(NULL, [], 'Submit');
    $this->assertRaw("page_01_trigger_checkbox: 1
page_01_textfield_required: '{default_value}'
page_01_textfield_optional: '{default_value}'
page_01_textfield_disabled: ''
page_01_textfield_enabled: ''
page_01_textfield_visible: ''
page_01_textfield_invisible: ''
page_01_checkbox_checked: 0
page_01_checkbox_unchecked: 0
page_02_textfield_required: '{default_value}'
page_02_textfield_optional: '{default_value}'
page_02_textfield_disabled: ''
page_02_textfield_enabled: ''
page_02_textfield_visible: '{default_value}'
page_02_textfield_visible_slide: '{default_value}'
page_02_textfield_invisible: ''
page_02_textfield_invisible_slide: ''
page_02_checkbox_checked: 1
page_02_checkbox_unchecked: 0
page_03_textfield_required: '{default_value}'
page_01_trigger_checkbox_computed: 'Yes'");
  }

}
