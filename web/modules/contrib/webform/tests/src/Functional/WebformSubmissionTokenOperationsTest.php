<?php

namespace Drupal\Tests\webform\Functional;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Tests for operations on a webform submission using a tokenized URL.
 *
 * @group webform
 */
class WebformSubmissionTokenOperationsTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['token'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_token_operations'];

  /**
   * Test operations on a webform submission using a tokenized URL.
   */
  public function testTokenOperationsTest() {
    $normal_user = $this->drupalCreateUser();

    $webform = Webform::load('test_token_operations');

    $token_operations = ['view', 'update', 'delete'];

    // Post test submission.
    $sid = $this->postSubmission($webform, ['textfield' => 'test']);
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = WebformSubmission::load($sid);

    /**************************************************************************/

    // Check confirmation page's operations (view, update, & delete) token URLs.
    foreach ($token_operations as $token_operation) {
      $token_url = $webform_submission->getTokenUrl($token_operation);
      $link_label = $token_url->setAbsolute(FALSE)->toString();
      $link_url = $token_url->setAbsolute(TRUE)->toString();
      $this->assertRaw('<a href="' . $link_url . '">' . $link_label . '</a>');
    }

    /**************************************************************************/
    /* View */
    /**************************************************************************/

    // Check token view access allowed.
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('view'));
    $this->assertResponse(200);
    $this->assertRaw('Submission information');
    $this->assertRaw('<label>textfield</label>');

    // Check token view access denied.
    $webform->setSetting('token_view', FALSE)->save();
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('view'));
    $this->assertResponse(403);
    $this->assertNoRaw('Submission information');
    $this->assertNoRaw('<label>textfield</label>');

    /**************************************************************************/
    /* Update */
    /**************************************************************************/

    // Check token update access allowed.
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('update'));
    $this->assertResponse(200);
    $this->assertRaw('Submission information');
    $this->assertFieldByName('textfield', $webform_submission->getElementData('textfield'));

    // Check token update does not load the submission.
    $webform->setSetting('token_update', FALSE)->save();
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('update'));
    $this->assertResponse(200);
    $this->assertNoRaw('Submission information');
    $this->assertNoFieldByName('textfield', $webform_submission->getElementData('textfield'));

    /**************************************************************************/
    /* Delete */
    /**************************************************************************/

    // Check token delete access allowed.
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('delete'));
    $this->assertResponse(200);

    // Check token delete access denied.
    $webform->setSetting('token_delete', FALSE)->save();
    $this->drupalLogin($normal_user);
    $this->drupalGet($webform_submission->getTokenUrl('delete'));
    $this->assertResponse(403);

    /**************************************************************************/
    /* Anonymous */
    /**************************************************************************/

    // Logout and switch to anonymous user.
    $this->drupalLogout();

    // Set access to authenticated only and reenabled tokenized URL.
    $access = $webform->getAccessRules();
    $access['create']['roles'] = ['authenticated'];
    $webform->setAccessRules($access);
    $webform
      ->setSetting('token_view', TRUE)
      ->setSetting('token_update', TRUE)
      ->setSetting('token_delete', TRUE)
      ->save();

    // Check that access is denied for anonymous user.
    $this->drupalGet('/webform/test_token_operations');
    $this->assertResponse(403);

    // Check token operations are allowed for anonymous user.
    foreach ($token_operations as $token_operation) {
      $this->drupalGet($webform_submission->getTokenUrl($token_operation));
      $this->assertResponse(200);
    }
  }

}
