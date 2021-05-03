<?php

namespace Drupal\Tests\mailsystem\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Administrator Settings UI.
 *
 * @group mailsystem
 */
class AdminFormSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['mailsystem'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests the Administrator Settings UI.
   */
  public function testAdminMailSystemForm() {
    $assert_session = $this->assertSession();
    // Unauthorized user should not have access.
    $this->drupalGet('admin/config/system/mailsystem');
    $this->assertResponse(403);

    // Check the overview.
    $user = $this->createUser(['administer_mailsystem']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/mailsystem');
    $assert_session->pageTextContains('Mail System');
    // Check default theme options.
    $assert_session->optionExists('edit-mailsystem-default-theme', 'current');
    $assert_session->optionExists('edit-mailsystem-default-theme', 'default');
    $assert_session->optionExists('edit-mailsystem-default-theme', 'stable');
    $assert_session->optionExists('edit-mailsystem-default-theme', 'classy');
    // Check custom module options labels.
    $assert_session->optionExists('edit-custom-custom-module', '- Select -');
    $assert_session->optionExists('edit-custom-custom-module', 'System');
    $assert_session->optionExists('edit-custom-custom-module', 'User');

    // Configure the default Mail System.
    $this->drupalPostForm(NULL, [
      'mailsystem[default_formatter]' => 'test_mail_collector',
      'mailsystem[default_sender]' => 'test_mail_collector',
      'mailsystem[default_theme]' => 'current',
    ], 'Save configuration');

    // Configure a specific module configuration.
    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'aaa',
      'custom[custom_formatter]' => 'test_mail_collector',
      'custom[custom_sender]' => 'test_mail_collector',
    ], 'Add');
    $this->drupalGet('admin/config/system/mailsystem');
    $assert_session->pageTextContains('aaa');

    // Add additional custom module settings, one with test_mail_collector and
    // one with php_mail.
    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'bbb',
      'custom[custom_formatter]' => 'test_mail_collector',
      'custom[custom_sender]' => 'test_mail_collector',
    ], 'Add');
    $this->drupalGet('admin/config/system/mailsystem');
    $assert_session->pageTextContains('bbb');

    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'ccc',
      'custom[custom_formatter]' => 'php_mail',
      'custom[custom_sender]' => 'php_mail',
    ], 'Add');
    $this->drupalGet('admin/config/system/mailsystem');
    $assert_session->pageTextContains('ccc');

    // Add a custom module settings, without specifying any key.
    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_formatter]' => 'test_mail_collector',
      'custom[custom_sender]' => 'test_mail_collector',
    ], 'Add');
    $assert_session->pageTextContains('All');
    $this->drupalGet('admin/config/system/mailsystem');

    // Try to add a custom module, first without setting the module, then
    // without formatter nor sender, then just specifying a key.
    $this->drupalPostForm(NULL, [
      'custom[custom_module_key]' => 'ddd',
      'custom[custom_formatter]' => 'test_mail_collector',
      'custom[custom_sender]' => 'test_mail_collector',
    ], 'Add');
    $assert_session->pageTextNotContains('ddd');
    $assert_session->pageTextContains('The module is required.');
    $this->drupalGet('admin/config/system/mailsystem');

    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'ddd',
    ], 'Add');
    $assert_session->pageTextNotContains('ddd');
    $assert_session->pageTextContains('At least a formatter or sender is required.');
    $this->drupalGet('admin/config/system/mailsystem');

    $this->drupalPostForm(NULL, [
      'custom[custom_module_key]' => 'ddd',
    ], 'Add');
    $assert_session->pageTextNotContains('ddd');
    $assert_session->pageTextContains('The module is required.');
    $assert_session->pageTextContains('At least a formatter or sender is required.');
    $this->drupalGet('admin/config/system/mailsystem');

    // Checking the configuration.
    $config = $this->config('mailsystem.settings');
    $this->assertEquals('current', $config->get('theme'));
    $this->assertEquals('test_mail_collector', $config->get('defaults.formatter'));
    $this->assertEquals('test_mail_collector', $config->get('defaults.sender'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.aaa.formatter'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.aaa.sender'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.bbb.formatter'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.bbb.sender'));
    $this->assertEquals('php_mail', $config->get('modules.system.ccc.formatter'));
    $this->assertEquals('php_mail', $config->get('modules.system.ccc.sender'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.none.formatter'));
    $this->assertEquals('test_mail_collector', $config->get('modules.system.none.sender'));
    $this->assertNull($config->get('modules.system.ddd'));

    // Try to update the formatter of the module keyed as 'ccc' from the form.
    $this->drupalPostForm(NULL, [
      'custom[custom_module_key]' => 'ccc',
      'custom[custom_formatter]' => 'test_mail_collector',
    ], 'Add');
    $assert_session->pageTextContains('The module is required.');
    $this->drupalGet('admin/config/system/mailsystem');

    // Try to update 'modules.system.ccc' formatter and sender from the form.
    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'ccc',
      'custom[custom_formatter]' => 'test_mail_collector',
      'custom[custom_sender]' => 'test_mail_collector',
    ], 'Add');
    $assert_session->pageTextContains('An entry for this combination exists already. Use the form below to update or remove it.');
    $this->drupalGet('admin/config/system/mailsystem');

    // Try to add a custom module with the same settings of an existing one,
    // without formatter and sender.
    $this->drupalPostForm(NULL, [
      'custom[custom_module]' => 'system',
      'custom[custom_module_key]' => 'ccc',
    ], 'Add');
    $assert_session->pageTextContains('An entry for this combination exists already. Use the form below to update or remove it.');
    $assert_session->pageTextNotContains('At least a formatter or sender is required.');
    $this->drupalGet('admin/config/system/mailsystem');

    // Edit the second and third custom module formatter from the table.
    $this->drupalPostForm(NULL, [
      'custom[modules][system.bbb][formatter]' => 'php_mail',
      'custom[modules][system.ccc][formatter]' => 'test_mail_collector',
    ], 'Save configuration');
    $config->set('modules.system.bbb.formatter', 'php_mail')->save();
    $config->set('modules.system.ccc.formatter', 'test_mail_collector')->save();
    $this->drupalGet('admin/config/system/mailsystem');
    $this->assertEquals($config->get('modules.system.aaa.formatter'), 'test_mail_collector');
    $this->assertEquals($config->get('modules.system.bbb.formatter'), 'php_mail');
    $this->assertEquals($config->get('modules.system.ccc.formatter'), 'test_mail_collector');
    $this->assertEquals($config->get('modules.system.none.formatter'), 'test_mail_collector');

    // Remove the first custom module.
    $this->drupalPostForm(NULL, [
      'custom[modules][system.aaa][remove]' => TRUE,
    ], 'Save configuration');
    $config->clear('modules.system.aaa')->save();
    $this->drupalGet('admin/config/system/mailsystem');
    $this->assertNull($config->get('modules.system.aaa'));
    $this->assertNotNull($config->get('modules.system.bbb'));
    $this->assertNotNull($config->get('modules.system.ccc'));
    $this->assertNotNull($config->get('modules.system.none'));
  }

}
