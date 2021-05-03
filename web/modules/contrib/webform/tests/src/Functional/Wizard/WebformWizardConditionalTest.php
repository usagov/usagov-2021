<?php

namespace Drupal\Tests\webform\Functional\Wizard;

use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;

/**
 * Tests for webform conditional wizard.
 *
 * @group webform
 */
class WebformWizardConditionalTest extends WebformWizardTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_form_wizard_conditional'];

  /**
   * Test webform custom wizard.
   */
  public function testConditionalWizard() {
    $webform = Webform::load('test_form_wizard_conditional');
    $this->drupalGet('/webform/test_form_wizard_conditional');

    // Check hiding page 1, 3, and 5.
    $edit = [
      'trigger_pages[page_1]' => FALSE,
      'trigger_pages[page_3]' => FALSE,
      'trigger_pages[page_5]' => FALSE,
    ];
    $this->drupalPostForm('/webform/test_form_wizard_conditional', $edit, 'Next >');
    $this->assertCurrentPage('Page 2', 'page_2');
    $this->drupalPostForm(NULL, [], 'Next >');
    $this->assertCurrentPage('Page 4', 'page_4');
    $this->drupalPostForm(NULL, [], 'Submit');
    $this->assertCurrentPage('Complete', WebformInterface::PAGE_CONFIRMATION);
    $sid = $this->getLastSubmissionId($webform);
    $this->assertNotEmpty($sid);

    // Check hiding all pages and skipping to complete.
    $edit = [
      'trigger_none' => TRUE,
    ];
    $this->drupalPostForm('/webform/test_form_wizard_conditional', $edit, 'Next >');
    $this->assertRaw('<div class="webform-progress">');
    $this->assertRaw('New submission added to Test: Webform: Wizard conditional.');
    $this->assertCurrentPage('Complete', WebformInterface::PAGE_CONFIRMATION);
    $sid = $this->getLastSubmissionId($webform);
    $this->assertNotEmpty($sid);

    // Enable preview.
    $webform->setSetting('preview', 1);
    $webform->save();

    // Check hiding all pages and skipping to preview.
    $edit = [
      'trigger_none' => TRUE,
    ];
    $this->drupalPostForm('/webform/test_form_wizard_conditional', $edit, 'Next >');
    $this->assertCurrentPage('Preview', WebformInterface::PAGE_PREVIEW);

    // Disable preview and include confirmation in progress page.
    $webform->setSettings(['preview' => 0, 'wizard_confirmation' => FALSE]);
    $webform->save();

    // Check hiding all page with no preview or confirmation page included
    // in the progress bar still submits the form and then skips
    // to the confirmation page.
    $edit = [
      'trigger_none' => TRUE,
    ];
    $this->drupalPostForm('/webform/test_form_wizard_conditional', $edit, 'Next >');
    $this->assertNoRaw('<div class="webform-progress">');
    $this->assertRaw('New submission added to Test: Webform: Wizard conditional.');
    $last_sid = $this->getLastSubmissionId($webform);
    $this->assertNotEqual($sid, $last_sid);

    // Enable wizard progress states.
    $webform->setSetting('wizard_progress_states', 1);
    $webform->save();

    $this->drupalGet('/webform/test_form_wizard_conditional');

    // Check hiding page 3, and 5.
    $edit = [
      'trigger_pages[page_3]' => FALSE,
      'trigger_pages[page_5]' => FALSE,
    ];
    $this->drupalPostForm('/webform/test_form_wizard_conditional', $edit, 'Next >');

    // Assert the progress bar no longer includes page 5.
    $this->assertNoPattern('|<li data-webform-page="5" class="webform-progress-bar__page">\s+<b>Page 5</b>|');
  }

}
