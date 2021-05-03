<?php

namespace Drupal\Tests\webform\Functional\Wizard;

use Drupal\webform\Entity\Webform;
use Drupal\Core\Serialization\Yaml;
use Drupal\webform\WebformInterface;

/**
 * Tests for webform advanced wizard.
 *
 * @group webform
 */
class WebformWizardAdvancedTest extends WebformWizardTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_form_wizard_advanced'];

  /**
   * Test webform advanced wizard.
   */
  public function testAdvancedWizard() {
    $webform = Webform::load('test_form_wizard_advanced');

    // Get initial wizard start page (Your Information).
    $this->drupalGet('/webform/test_form_wizard_advanced');
    // Check progress bar is set to 'Your Information'.
    $this->assertPattern('#<li data-webform-page="information" class="webform-progress-bar__page webform-progress-bar__page--current"><b>Your Information</b><span></span></li>#');
    // Check progress pages.
    $this->assertRaw('1 of 5');
    // Check progress percentage.
    $this->assertText('(0%)');
    // Check draft button does not exist.
    $this->assertNoFieldById('edit-draft', 'Save Draft');
    // Check next button does exist.
    $this->assertFieldById('edit-wizard-next', 'Next >');
    // Check first name field does exist.
    $this->assertFieldById('edit-first-name', 'John');

    // Create a login user who can save drafts.
    $account = $this->drupalCreateUser();
    $this->drupalLogin($account);

    // Move to next page (Contact Information).
    $edit = [
      'first_name' => 'Jane',
    ];
    $this->drupalPostForm('/webform/test_form_wizard_advanced', $edit, 'Next >');
    // Check progress bar is set to 'Contact Information'.
    $this->assertPattern('#<li data-webform-page="information" class="webform-progress-bar__page webform-progress-bar__page--done"><b>Your Information</b><span></span></li>#');
    $this->assertPattern('#<li data-webform-page="contact" class="webform-progress-bar__page webform-progress-bar__page--current"><b>Contact Information</b></li>#');
    // Check progress pages.
    $this->assertRaw('2 of 5');
    // Check progress percentage.
    $this->assertText('(25%)');

    // Check draft button does exist.
    $this->assertFieldById('edit-draft', 'Save Draft');
    // Check previous button does exist.
    $this->assertFieldById('edit-wizard-prev', '< Previous');
    // Check next button does exist.
    $this->assertFieldById('edit-wizard-next', 'Next >');
    // Check email field does exist.
    $this->assertFieldById('edit-email', 'johnsmith@example.com');

    // Move to previous page (Your Information) while posting data new data
    // via autosave.
    $edit = [
      'email' => 'janesmith@example.com',
    ];
    $this->drupalPostForm(NULL, $edit, '< Previous');
    // Check progress bar is set to 'Your Information'.
    $this->assertPattern('#<li data-webform-page="information" class="webform-progress-bar__page webform-progress-bar__page--current"><b>Your Information</b><span></span></li>#');
    // Check nosave class.
    $this->assertRaw('js-webform-unsaved');
    // Check no nosave attributes.
    $this->assertNoRaw('data-webform-unsaved');
    // Check progress pages.
    $this->assertRaw('1 of 5');
    // Check progress percentage.
    $this->assertText('(0%)');

    // Check first name set to Jane.
    $this->assertFieldById('edit-first-name', 'Jane');
    // Check sex is still set to Male.
    $this->assertFieldChecked('edit-sex-male');

    // Change sex from Male to Female.
    $edit = [
      'sex' => 'Female',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save Draft');
    // Check first name set to Jane.
    $this->assertFieldById('edit-first-name', 'Jane');
    // Check sex is now set to Female.
    $this->assertFieldChecked('edit-sex-female');

    // Move to next page (Contact Information).
    $this->drupalPostForm('/webform/test_form_wizard_advanced', [], 'Next >');
    // Check nosave class.
    $this->assertRaw('js-webform-unsaved');
    // Check nosave attributes.
    $this->assertRaw('data-webform-unsaved');
    // Check progress bar is set to 'Contact Information'.
    $this->assertCurrentPage('Contact Information', 'contact');
    // Check progress pages.
    $this->assertRaw('2 of 5');
    // Check progress percentage.
    $this->assertText('(25%)');

    // Check email field is now janesmith@example.com.
    $this->assertFieldById('edit-email', 'janesmith@example.com');

    // Save draft which saves the 'current_page'.
    $edit = [
      'phone' => '111-111-1111',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save Draft');
    // Complete reload the webform.
    $this->drupalGet('/webform/test_form_wizard_advanced');
    // Check progress bar is still set to 'Contact Information'.
    $this->assertCurrentPage('Contact Information', 'contact');

    // Move to last page (Your Feedback).
    $this->drupalPostForm(NULL, [], 'Next >');
    // Check progress bar is set to 'Your Feedback'.
    $this->assertCurrentPage('Your Feedback', 'feedback');
    // Check previous button does exist.
    $this->assertFieldById('edit-wizard-prev', '< Previous');
    // Check next button is labeled 'Preview'.
    $this->assertFieldById('edit-preview-next', 'Preview');
    // Check submit button does exist.
    $this->assertFieldById('edit-submit', 'Submit');

    // Move to preview.
    $edit = [
      'comments' => 'This is working fine.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Preview');
    // Check progress bar is set to 'Preview'.
    $this->assertCurrentPage('Preview', WebformInterface::PAGE_PREVIEW);
    // Check progress pages.
    $this->assertRaw('4 of 5');
    // Check progress percentage.
    $this->assertText('(75%)');

    // Check preview values.
    $this->assertRaw('<label>First Name</label>');
    $this->assertRaw('Jane');
    $this->assertRaw('<label>Last Name</label>');
    $this->assertRaw('Smith');
    $this->assertRaw('<label>Sex</label>');
    $this->assertRaw('Female');
    $this->assertRaw('<label>Email</label>');
    $this->assertRaw('<a href="mailto:janesmith@example.com">janesmith@example.com</a>');
    $this->assertRaw('<label>Phone</label>');
    $this->assertRaw('<a href="tel:111-111-1111">111-111-1111</a>');
    $this->assertRaw('<div class="webform-element webform-element-type-textarea js-form-item form-item js-form-type-item form-item-comments js-form-item-comments form-no-label" id="test_form_wizard_advanced--comments">');
    $this->assertRaw('This is working fine.');

    // Submit the webform.
    $this->drupalPostForm(NULL, [], 'Submit');
    // Check progress bar is set to 'Complete'.
    $this->assertCurrentPage('Complete', WebformInterface::PAGE_CONFIRMATION);
    // Check progress pages.
    $this->assertRaw('5 of 5');
    // Check progress percentage.
    $this->assertText('(100%)');

    /* Custom wizard settings (using advanced wizard) */

    $this->drupalLogout();

    // Check global next and previous button labels.
    \Drupal::configFactory()->getEditable('webform.settings')
      ->set('settings.default_wizard_next_button_label', '{global wizard next}')
      ->set('settings.default_wizard_prev_button_label', '{global wizard previous}')
      ->save();
    $this->drupalPostForm('/webform/test_form_wizard_advanced', [], '{global wizard next}');

    // Check progress bar.
    $this->assertRaw('class="webform-progress-bar"');
    // Check previous button.
    $this->assertFieldById('edit-wizard-prev', '{global wizard previous}');
    // Check next button.
    $this->assertFieldById('edit-wizard-next', '{global wizard next}');

    // Add 'webform_actions' element.
    $webform->setElementProperties('actions', [
      '#type' => 'webform_actions',
      '#wizard_next__label' => '{webform wizard next}',
      '#wizard_prev__label' => '{webform wizard previous}',
      '#preview_next__label' => '{webform preview next}',
      '#preview_prev__label' => '{webform preview previous}',
    ]);
    $webform->save();

    // Check webform next and previous button labels.
    $this->drupalPostForm('/webform/test_form_wizard_advanced', [], '{webform wizard next}');
    // Check previous button.
    $this->assertFieldById('edit-actions-wizard-prev', '{webform wizard previous}');
    // Check next button.
    $this->assertFieldById('edit-actions-wizard-next', '{webform wizard next}');

    // Check custom next and previous button labels.
    $elements = Yaml::decode($webform->get('elements'));
    $elements['contact']['#next_button_label'] = '{elements wizard next}';
    $elements['contact']['#prev_button_label'] = '{elements wizard previous}';
    $webform->set('elements', Yaml::encode($elements));
    $webform->save();
    $this->drupalPostForm('/webform/test_form_wizard_advanced', [], '{webform wizard next}');

    // Check previous button.
    $this->assertFieldById('edit-actions-wizard-prev', '{elements wizard previous}');
    // Check next button.
    $this->assertFieldById('edit-actions-wizard-next', '{elements wizard next}');

    // Check webform next and previous button labels.
    $webform->setSettings([
      'wizard_progress_bar' => FALSE,
      'wizard_progress_pages' => TRUE,
      'wizard_progress_percentage' => TRUE,
    ] + $webform->getSettings());
    $webform->save();
    $this->drupalGet('/webform/test_form_wizard_advanced');

    // Check no progress bar.
    $this->assertNoRaw('class="webform-progress-bar"');
    // Check progress pages.
    $this->assertRaw('1 of 5');
    // Check progress percentage.
    $this->assertText('(0%)');

    // Check global complete labels.
    $webform->setSettings([
      'wizard_progress_bar' => TRUE,
    ] + $webform->getSettings());
    $webform->save();
    \Drupal::configFactory()->getEditable('webform.settings')
      ->set('settings.default_wizard_confirmation_label', '{global complete}')
      ->save();
    $this->drupalGet('/webform/test_form_wizard_advanced');
    $this->assertRaw('{global complete}');

    // Check webform complete label.
    $webform->setSettings([
      'wizard_progress_bar' => TRUE,
      'wizard_confirmation_label' => '{webform complete}',
    ] + $webform->getSettings());
    $webform->save();
    $this->drupalGet('/webform/test_form_wizard_advanced');
    $this->assertRaw('{webform complete}');

    // Check webform exclude complete.
    $webform->setSettings([
      'wizard_confirmation' => FALSE,
    ] + $webform->getSettings());
    $webform->save();
    $this->drupalGet('/webform/test_form_wizard_advanced');

    // Check complete label.
    $this->assertRaw('class="webform-progress-bar"');
    // Check complete is missing from confirmation page.
    $this->assertNoRaw('{webform complete}');
    $this->drupalGet('/webform/test_form_wizard_advanced/confirmation');
    $this->assertNoRaw('class="webform-progress-bar"');
  }

}
