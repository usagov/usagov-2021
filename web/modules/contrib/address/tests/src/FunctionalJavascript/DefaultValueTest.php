<?php

namespace Drupal\Tests\address\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the default value handling in a complex ajax form.
 *
 * @group address
 */
class DefaultValueTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'node',
    'address',
    'address_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the default value.
   */
  public function testDefaultValue() {
    $expected_default_value = [
      'country_code' => 'US',
      'administrative_area' => 'CA',
      'locality' => 'Mountain View',
      'postal_code' => '94043',
      'address_line1' => '1098 Alta Ave',
      'organization' => 'Google Inc.',
      'given_name' => 'John',
      'family_name' => 'Smith',
    ];

    $this->drupalGet('/address_test/default_value_test_form');
    foreach ($expected_default_value as $property => $value) {
      $prefix = 'credit_card[address]';
      $this->assertSession()->fieldValueEquals($prefix . '[' . $property . ']', $value);
    }
    $this->getSession()->getPage()->fillField('credit_card[address][given_name]', 'Jack');
    $radio_button = $this->getSession()->getPage()->findField('Cash on delivery');
    $radio_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Confirm that the address is now under different parents, and that the
    // default value is set.
    foreach ($expected_default_value as $property => $value) {
      $prefix = 'cash_on_delivery[address]';
      $this->assertSession()->fieldValueEquals($prefix . '[' . $property . ']', $value);
    }
    $this->submitForm([
      'cash_on_delivery[address][address_line1]' => '1099 Alta Ave',
    ], 'Submit');
    $this->assertSession()->pageTextContains('The street is "1099 Alta Ave" and the country code is US.');
  }

}
