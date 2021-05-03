<?php

namespace Drupal\Tests\webform\Functional\Settings;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Tests for webform submission serial number.
 *
 * @group webform
 */
class WebformSettingsSerialTest extends WebformBrowserTestBase {

  /**
   * Tests webform submission serial number.
   */
  public function testSettings() {
    // Login the admin user.
    $this->drupalLogin($this->rootUser);

    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load('contact');

    // Set next serial to 99.
    $this->drupalPostForm('/admin/structure/webform/manage/contact/settings/submissions', ['next_serial' => 99], 'Save');

    // Check next serial is 99.
    $sid = $this->postSubmissionTest($webform);
    $webform_submission = WebformSubmission::load($sid);
    $this->assertEqual($webform_submission->serial(), 99);
    $this->assertNotEqual($webform_submission->serial(), $sid);

    // Check that next serial is set to max serial.
    $this->drupalPostForm('/admin/structure/webform/manage/contact/settings/submissions', ['next_serial' => 1], 'Save');
    $this->assertRaw('The next submission number was increased to 100 to make it higher than existing submissions.');

    // Disable serial.
    $webform->setSetting('serial_disabled', TRUE)->save();

    // Check next serial is not 100 but equal to the sid.
    $sid = $this->postSubmissionTest($webform);
    $webform_submission = WebformSubmission::load($sid);
    $this->assertNotEqual($webform_submission->serial(), 100);
    $this->assertEqual($webform_submission->serial(), $sid);

  }

}
