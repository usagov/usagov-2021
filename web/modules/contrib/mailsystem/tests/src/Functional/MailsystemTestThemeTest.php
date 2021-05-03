<?php

namespace Drupal\Tests\mailsystem\Functional;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests mail theme for formatting emails using a theme template.
 *
 * @group mailsystem
 */
class MailsystemTestThemeTest extends BrowserTestBase {

  use AssertMailTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'mailsystem',
    'mailsystem_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The Mailsystem settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->config = $this->config('mailsystem.settings');
  }

  /**
   * Tests the mail theme.
   */
  public function testMailTheme() {

    // Mail System uses its own configuration for the used mail plugins.
    // Use the mail collector just like WebTestBase::initConfig().
    $this->config
      ->set('defaults.sender', 'test_mail_collector')
      ->set('defaults.formatter', 'test_mail_collector')
      ->save();

    // Send an email with the default setting (should NOT use the test theme).
    $this->drupalGet('/mailsystem-test/theme');
    $mails = $this->getMails();

    // Check the configuration and if the correct theme was used in mails.
    $this->assertEqual($this->config->get('theme'), 'current');
    $this->assertTrue(strpos($mails[0]['body'], 'Anonymous (not verified)') !== FALSE);

    // Install the test theme and set it as the mail theme.
    \Drupal::service('theme_installer')->install(['mailsystem_test_theme']);
    $this->config->set('theme', 'mailsystem_test_theme')->save();

    // Send another email (now it should use the test theme).
    $this->drupalGet('/mailsystem-test/theme');
    $mails = $this->getMails();

    // Check the new configuration and ensure that our test theme and its
    // implementation of the username template are used in mails.
    $this->assertEquals('mailsystem_test_theme', $this->config->get('theme'));
    $this->assertTrue(strpos($mails[1]['body'], 'Mailsystem test theme') !== FALSE);
  }

}
