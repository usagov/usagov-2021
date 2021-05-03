<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Core\Url;

/**
 * Tests the Scheduler lightweight cron urls and admin form.
 *
 * @group scheduler
 */
class SchedulerLightweightCronTest extends SchedulerBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->routeCronForm = Url::fromRoute('scheduler.cron_form');
  }

  /**
   * Test scheduler lightweight cron runs.
   */
  public function testLightweightCronRun() {
    // Run scheduler lightweight cron anonymously without any cron key.
    // The response with no key should be "404 Not Found".
    $this->drupalGet('scheduler/cron');
    $this->assertSession()->statusCodeEquals(404);

    // Run scheduler lightweight cron anonymously with a random cron key.
    // The response for an incorrect key should be "403 Access Denied".
    $key = substr(md5(rand()), 0, 20);
    $this->drupalGet('scheduler/cron/' . $key);
    $this->assertSession()->statusCodeEquals(403);

    // Run scheduler lightweight cron anonymously with the valid cron key which
    // is defined during install. It should run OK but no content will be
    // produced so the response should be "204 No Content".
    $config = $this->config('scheduler.settings');
    $key = $config->get('lightweight_cron_access_key');
    $this->drupalGet('scheduler/cron/' . $key);
    $this->assertSession()->statusCodeEquals(204);
  }

  /**
   * Test the lightweight cron settings form.
   */
  public function testLightweightCronSettingsForm() {
    // Log in.
    $this->drupalLogin($this->adminUser);

    // Check that the cron key has an initial value, created during install.
    $this->drupalGet($this->routeCronForm);
    $key_xpath = $this->xpath('//input[@id="edit-lightweight-access-key"]/@value');
    $key = $key_xpath[0]->getText();
    $this->assertTrue(!empty($key), 'Default lightweight cron key field is not empty');
    $this->assertTrue(strlen($key) == 20, 'Default lightweight cron key string length is 20');

    // Check that a new random key can be generated.
    $this->drupalPostForm($this->routeCronForm, [], 'Generate new random key');
    $new_key_xpath = $this->xpath('//input[@id="edit-lightweight-access-key"]/@value');
    $new_key = $new_key_xpath[0]->getText();
    $this->assertTrue(!empty($new_key), 'Lightweight cron key field is not empty after generating new key');
    $this->assertTrue(strlen($new_key) == 20, 'New lightweight cron key string length is 20');
    $this->assertNotEqual($key, $new_key, 'Lightweight cron key has changed.');

    // Check that the 'run lightweight cron' button works.
    $this->drupalPostForm($this->routeCronForm, [], "Run Scheduler's lightweight cron now");
    $this->assertSession()->pageTextContains('Lightweight cron run completed.');

    // Check that the form cannot be saved if the cron key is blank.
    $this->drupalPostForm($this->routeCronForm, ['lightweight_access_key' => ''], 'Save configuration');
    $this->assertSession()->pageTextContains('Lightweight cron access key field is required.');
    $this->assertSession()->pageTextNotContains('The configuration options have been saved.');
  }

}
