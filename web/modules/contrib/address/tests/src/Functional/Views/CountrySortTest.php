<?php

namespace Drupal\Tests\address\Functional\Views;

use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;

/**
 * Tests sorting Views by country.
 *
 * @group address
 */
class CountrySortTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'views',
    'address',
    'address_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A regular user with 'access content' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The test nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * The countries, keyed by country code.
   *
   * @var string[]
   */
  protected $countries;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($this->user);

    // This is a good set since the names and codes aren't in the same order.
    $this->countries = [
      'AT' => 'Austria',
      'DE' => 'Germany',
      'FR' => 'France',
      'HR' => 'Croatia',
      'US' => 'United States',
      'VI' => 'U.S. Virgin Islands',
    ];

    foreach ($this->countries as $country_code => $name) {
      $this->nodes[$country_code] = $this->drupalCreateNode([
        'type' => 'address_test',
        'status' => 1,
        'title' => $name,
        'field_address_test' => [
          'country_code' => $country_code,
        ],
      ]);
    }
  }

  /**
   * Test sorting by country code.
   */
  public function testSortCountryCode() {
    // Force the view to sort by code.
    $view = Views::getView('address_test_sort_country');
    $sorts = $view->getDisplay()->getOption('sorts');
    $sorts['field_address_test_country_code']['sort_by'] = 'code';
    $view->getDisplay()->overrideOption('sorts', $sorts);
    $view->save();

    $this->drupalGet('address-test/views/sort-country');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1-AT');
    $this->assertSession()->pageTextContains('2-DE');
    $this->assertSession()->pageTextContains('3-FR');
    $this->assertSession()->pageTextContains('4-HR');
    $this->assertSession()->pageTextContains('5-US');
    $this->assertSession()->pageTextContains('6-VI');
  }

  /**
   * Test sorting by country name.
   */
  public function testSortCountryName() {
    // Force the view to sort by name.
    $view = Views::getView('address_test_sort_country');
    $sorts = $view->getDisplay()->getOption('sorts');
    $sorts['field_address_test_country_code']['sort_by'] = 'name';
    $view->getDisplay()->overrideOption('sorts', $sorts);
    $view->save();

    $this->drupalGet('address-test/views/sort-country');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('1-AT');
    $this->assertSession()->pageTextContains('2-HR');
    $this->assertSession()->pageTextContains('3-FR');
    $this->assertSession()->pageTextContains('4-DE');
    $this->assertSession()->pageTextContains('5-VI');
    $this->assertSession()->pageTextContains('6-US');
  }

}
