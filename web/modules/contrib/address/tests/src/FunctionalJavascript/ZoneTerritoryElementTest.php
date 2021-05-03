<?php

namespace Drupal\Tests\address\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the address_zone_territory form element.
 *
 * @group address
 */
class ZoneTerritoryElementTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'node',
    'address',
    'address_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the zone territory form element.
   */
  public function testZoneTerritory() {
    $this->drupalGet('/address_test/zone_territory_test_form');
    $this->getSession()->getPage()->selectFieldOption('territory[country_code]', 'US');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFieldByName('territory[administrative_area]');
    $this->assertFieldByName('territory[limit_by_postal_code]');
    $this->getSession()->getPage()->checkField('territory[limit_by_postal_code]');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertFieldByName('territory[included_postal_codes]');
    $this->assertFieldByName('territory[excluded_postal_codes]');
    $this->submitForm([
      'territory[administrative_area]' => 'SC',
      'territory[included_postal_codes]' => '29601',
      'territory[excluded_postal_codes]' => '29602',
    ], 'Submit');
    $this->assertSession()->pageTextContains('Submitted: US / SC / 29601 / 29602.');
  }

}
