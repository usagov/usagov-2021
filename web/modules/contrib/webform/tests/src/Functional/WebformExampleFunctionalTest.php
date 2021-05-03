<?php

namespace Drupal\Tests\webform\Functional;

/**
 * Example of webform browser test.
 *
 * @group webform_browser
 */
class WebformExampleFunctionalTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['webform'];

  /**
   * Test get.
   */
  public function testGet() {
    $this->drupalGet('/webform/contact');
    $this->verbose('hi');
    $this->assertSession()->responseContains('Contact');
  }

}
