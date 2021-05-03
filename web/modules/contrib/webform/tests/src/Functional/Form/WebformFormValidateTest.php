<?php

namespace Drupal\Tests\webform\Functional\Form;

use Drupal\webform\Entity\Webform;
use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Tests for webform form validation.
 *
 * @group webform
 */
class WebformFormValidateTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['webform', 'webform_test_validate'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_form_validate'];

  /**
   * Tests form (custom) validation.
   */
  public function testValidate() {
    /* Test form#validate webform handling */
    $webform_validate = Webform::load('test_form_validate');
    $this->postSubmission($webform_validate, []);
    $this->assertRaw('Custom element is required.');

    $this->postSubmission($webform_validate, ['custom' => 'value']);
    $this->assertNoRaw('Custom element is required.');
  }

}
