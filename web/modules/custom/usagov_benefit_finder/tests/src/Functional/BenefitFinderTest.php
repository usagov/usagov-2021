<?php

namespace Drupal\Tests\usagov_benefit_finder\Functional;

use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests Benefit Finder system.
 *
 * @group usagov_benefit_finder
 */
class BenefitFinderTest extends ExistingSiteBase {

  /**
   * A user with the 'administer users' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  protected function setUp(): void {
    parent::setUp();

    // Cause tests to fail if an error is sent to Drupal logs.
    $this->failOnLoggedErrors();

    // Create admin user.
    $this->adminUser = $this->createUser(['administer users']);
  }

  /**
   * Tests Benefit Finder API.
   *
   * This test only needs to ensure that creating life event JSON data works.
   */
  public function testAll() {
    $this->testApi();
    $this->testPage();
  }

  /**
   * Tests Benefit Finder API.
   *
   * This test only needs to ensure that creating life event JSON data works.
   */
  public function testApi() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/benefit-finder/api/life-event/death');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalLogout();
  }

  /**
   * Tests Benefit Finder page.
   *
   *  This test only needs to ensure that life event page template works.
   */
  public function testPage() {
    $this->drupalGet('/benefit-finder/death');
    $this->assertSession()->elementExists('css', '#benefit-finder');
  }

}
