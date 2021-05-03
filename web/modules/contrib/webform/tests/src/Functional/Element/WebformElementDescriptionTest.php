<?php

namespace Drupal\Tests\webform\Functional\Element;

/**
 * Tests for webform element description.
 *
 * @group Webform
 */
class WebformElementDescriptionTest extends WebformElementBrowserTestBase {

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = ['test_element_description'];

  /**
   * Test date element.
   */
  public function testDateElement() {
    $this->drupalGet('/webform/test_element_description');

    // Check .description class is included when description display is before.
    $this->assertCssSelect('.description #edit-description-before--description.webform-element-description');

    // Check .description class is included when description display is after.
    $this->assertCssSelect('.description #edit-description-after--description.webform-element-description');
  }

}
