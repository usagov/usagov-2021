<?php

namespace Drupal\Tests\webform\Functional\Wizard;

/**
 * Tests for webform wizard validation.
 *
 * @group webform
 */
class WebformWizardValidateTest extends WebformWizardTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['webform', 'webform_test_element'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_form_wizard_validate', 'test_form_wizard_validate_comp'];

  /**
   * Test webform wizard validation.
   */
  public function testWizardValidate() {
    $this->drupalGet('/webform/test_form_wizard_validate');

    /**************************************************************************/
    // Basic validation.
    /**************************************************************************/

    // Check validation errors.
    $this->drupalPostForm('/webform/test_form_wizard_validate', [], 'Next >');
    $this->assertRaw('wizard_1_textfield field is required.');
    $this->assertRaw('wizard_1_select_other field is required.');
    $this->assertRaw('wizard_1_datelist field is required.');

    // Check submiting page #1.
    $edit = [
      'wizard_1_textfield' => '{wizard_1_textfield}',
      'wizard_1_select_other[select]' => 'one',
      'wizard_1_datelist[items][0][_item_][year]' => '2001',
      'wizard_1_datelist[items][0][_item_][month]' => '1',
      'wizard_1_datelist[items][0][_item_][day]' => '1',
      'wizard_1_datelist[items][0][_item_][hour]' => '1',
      'wizard_1_datelist[items][0][_item_][minute]' => '10',
    ];
    $this->drupalPostForm('/webform/test_form_wizard_validate', $edit, 'Next >');
    $this->assertRaw("wizard_1_textfield: '{wizard_1_textfield}'
wizard_1_select_other: one
wizard_1_datelist:
  - '2001-01-01T01:10:00+1100'
wizard_2_textfield: ''
wizard_2_select_other: null
wizard_2_datelist: {  }");

    // Check submiting page #2.
    $edit = [
      'wizard_2_textfield' => '{wizard_2_textfield}',
      'wizard_2_select_other[select]' => 'two',
      'wizard_2_datelist[items][0][_item_][year]' => '2002',
      'wizard_2_datelist[items][0][_item_][month]' => '2',
      'wizard_2_datelist[items][0][_item_][day]' => '2',
      'wizard_2_datelist[items][0][_item_][hour]' => '2',
      'wizard_2_datelist[items][0][_item_][minute]' => '20',
    ];
    $this->drupalPostForm(NULL, $edit, 'Next >');
    $this->assertRaw("wizard_1_textfield: '{wizard_1_textfield}'
wizard_1_select_other: one
wizard_1_datelist:
  - '2001-01-01T01:10:00+1100'
wizard_2_textfield: '{wizard_2_textfield}'
wizard_2_select_other: two
wizard_2_datelist:
  - '2002-02-02T02:20:00+1100'");

    /**************************************************************************/
    // Composite validation.
    /**************************************************************************/

    // Check validation errors.
    $this->drupalPostForm('/webform/test_form_wizard_validate_comp', [], 'Next >');
    // $this->assertRaw('The <em class="placeholder">datelist</em> date is required.');
    $this->assertRaw('textfield field is required.');

    // Check submiting page #1.
    $edit = [
      'wizard_1_custom_composite[items][0][datelist][year]' => '2001',
      'wizard_1_custom_composite[items][0][datelist][month]' => '1',
      'wizard_1_custom_composite[items][0][datelist][day]' => '1',
      'wizard_1_custom_composite[items][0][datelist][hour]' => '1',
      'wizard_1_custom_composite[items][0][datelist][minute]' => '10',
      'wizard_1_custom_composite[items][0][textfield]' => '{wizard_1_custom_composite_textfield}',
      'wizard_1_test_composite[textfield]' => '{wizard_1_test_composite_textfield}',
      'wizard_1_test_composite[datelist][year]' => '2001',
      'wizard_1_test_composite[datelist][month]' => '1',
      'wizard_1_test_composite[datelist][day]' => '1',
      'wizard_1_test_composite[datelist][hour]' => '1',
      'wizard_1_test_composite[datelist][minute]' => '10',
      'wizard_1_test_composite_multiple[items][0][_item_][textfield]' => '{wizard_1_test_composite_multiple_textfield}',
      'wizard_1_test_composite_multiple[items][0][_item_][datelist][year]' => '2001',
      'wizard_1_test_composite_multiple[items][0][_item_][datelist][month]' => '1',
      'wizard_1_test_composite_multiple[items][0][_item_][datelist][day]' => '1',
      'wizard_1_test_composite_multiple[items][0][_item_][datelist][hour]' => '1',
      'wizard_1_test_composite_multiple[items][0][_item_][datelist][minute]' => '10',
    ];
    $this->drupalPostForm('/webform/test_form_wizard_validate_comp', $edit, 'Next >');
    $this->assertRaw("wizard_1_custom_composite:
  - datelist: '2001-01-01T01:10:00+1100'
    textfield: '{wizard_1_custom_composite_textfield}'
wizard_1_test_composite:
  textfield: '{wizard_1_test_composite_textfield}'
  datelist: '2001-01-01T01:10:00+1100'
  nested_tel: ''
  nested_select: ''
  email: ''
  webform_email_confirm: ''
  tel: ''
  select: ''
  radios: ''
  date: ''
  webform_entity_select: ''
  entity_autocomplete: null
  datetime: ''
  nested_radios: ''
wizard_1_test_composite_multiple:
  - textfield: '{wizard_1_test_composite_multiple_textfield}'
    datelist: '2001-01-01T01:10:00+1100'
    nested_tel: ''
    nested_select: ''
    email: ''
    webform_email_confirm: ''
    tel: ''
    select: ''
    radios: null
    date: ''
    webform_entity_select: ''
    entity_autocomplete: null
    datetime: ''
    nested_radios: null
wizard_2_custom_composite: {  }
wizard_2_test_composite: null
wizard_2_test_composite_multiple: {  }");

    // Check submiting page #2.
    $edit = [
      'wizard_2_custom_composite[items][0][datelist][year]' => '2002',
      'wizard_2_custom_composite[items][0][datelist][month]' => '2',
      'wizard_2_custom_composite[items][0][datelist][day]' => '2',
      'wizard_2_custom_composite[items][0][datelist][hour]' => '2',
      'wizard_2_custom_composite[items][0][datelist][minute]' => '20',
      'wizard_2_custom_composite[items][0][textfield]' => '{wizard_2_custom_composite_textfield}',
      'wizard_2_test_composite[textfield]' => '{wizard_2_test_composite_textfield}',
      'wizard_2_test_composite[datelist][year]' => '2002',
      'wizard_2_test_composite[datelist][month]' => '2',
      'wizard_2_test_composite[datelist][day]' => '2',
      'wizard_2_test_composite[datelist][hour]' => '2',
      'wizard_2_test_composite[datelist][minute]' => '20',
      'wizard_2_test_composite_multiple[items][0][_item_][textfield]' => '{wizard_2_test_composite_multiple_textfield}',
      'wizard_2_test_composite_multiple[items][0][_item_][datelist][year]' => '2002',
      'wizard_2_test_composite_multiple[items][0][_item_][datelist][month]' => '2',
      'wizard_2_test_composite_multiple[items][0][_item_][datelist][day]' => '2',
      'wizard_2_test_composite_multiple[items][0][_item_][datelist][hour]' => '2',
      'wizard_2_test_composite_multiple[items][0][_item_][datelist][minute]' => '20',
    ];
    $this->drupalPostForm(NULL, $edit, 'Next >');

    $raw = "wizard_1_custom_composite:
  - datelist: '2001-01-01T01:10:00+1100'
    textfield: '{wizard_1_custom_composite_textfield}'
wizard_1_test_composite:
  textfield: '{wizard_1_test_composite_textfield}'
  datelist: '2001-01-01T01:10:00+1100'
  nested_tel: ''
  nested_select: ''
  email: ''
  webform_email_confirm: ''
  tel: ''
  select: ''
  radios: ''
  date: ''
  webform_entity_select: ''
  entity_autocomplete: null
  datetime: ''
  nested_radios: ''
wizard_1_test_composite_multiple:
  - textfield: '{wizard_1_test_composite_multiple_textfield}'
    datelist: '2001-01-01T01:10:00+1100'
    nested_tel: ''
    nested_select: ''
    email: ''
    webform_email_confirm: ''
    tel: ''
    select: ''
    radios: null
    date: ''
    webform_entity_select: ''
    entity_autocomplete: null
    datetime: ''
    nested_radios: null
wizard_2_custom_composite:
  - datelist: '2002-02-02T02:20:00+1100'
    textfield: '{wizard_2_custom_composite_textfield}'
wizard_2_test_composite:
  textfield: '{wizard_2_test_composite_textfield}'
  datelist: '2002-02-02T02:20:00+1100'
  nested_tel: ''
  nested_select: ''
  email: ''
  webform_email_confirm: ''
  tel: ''
  select: ''
  radios: ''
  date: ''
  webform_entity_select: ''
  entity_autocomplete: null
  datetime: ''
  nested_radios: ''
wizard_2_test_composite_multiple:
  - textfield: '{wizard_2_test_composite_multiple_textfield}'
    datelist: '2002-02-02T02:20:00+1100'
    nested_tel: ''
    nested_select: ''
    email: ''
    webform_email_confirm: ''
    tel: ''
    select: ''
    radios: null
    date: ''
    webform_entity_select: ''
    entity_autocomplete: null
    datetime: ''
    nested_radios: null";
    $this->assertRaw($raw);

    // Make sure navigating back and next through the
    // previous pages does not lose any data.
    $this->drupalPostForm(NULL, [], '< Previous');
    $this->assertRaw($raw);
    $this->drupalPostForm(NULL, [], '< Previous');
    $this->assertRaw($raw);
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertRaw($raw);
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertRaw($raw);
  }

}
