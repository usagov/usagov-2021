<?php

namespace Drupal\Tests\webform\Functional;

/**
 * Tests for webform help.
 *
 * @group webform
 */
class WebformHelpTest extends WebformBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['block', 'help', 'webform_test_message_custom'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('help_block');
  }

  /**
   * Tests webform help.
   */
  public function testHelp() {

    /**************************************************************************/
    // Help page.
    /**************************************************************************/

    // Check access denied to the webform help page.
    $this->drupalGet('/admin/structure/webform/help');
    $this->assertResponse(403);

    // Check access denied to the webform help video.
    $this->drupalGet('/admin/help/webform/video/introduction');
    $this->assertResponse(403);

    // Login with 'access content' permission.
    $this->drupalLogin($this->createUser(['access content']));

    // Check access allowed to the webform help video w/o watch more link.
    $this->drupalGet('/admin/help/webform/video/introduction', ['query' => ['_wrapper_format' => 'drupal_modal', 'more' => 1]]);
    $this->assertResponse(200);
    $this->assertNoRaw('Watch more videos');

    // Login with 'access webform help' permission.
    $this->drupalLogin($this->createUser(['access content', 'access webform help']));

    // Check access allowed to the webform help page.
    $this->drupalGet('/admin/structure/webform/help');
    $this->assertResponse(200);

    // Check access allowed to the webform help video with watch more link.
    $this->drupalGet('/admin/help/webform/video/introduction', ['query' => ['_wrapper_format' => 'drupal_modal', 'more' => 1]]);
    $this->assertResponse(200);
    $this->assertRaw('Watch more videos');

    /**************************************************************************/
    // Help block.
    /**************************************************************************/

    $this->drupalLogin($this->rootUser);

    // Check notifications, promotion, and welcome messages displayed.
    $this->drupalGet('/admin/structure/webform');
    $this->assertRaw('This is a warning notification.');
    $this->assertRaw('This is an info notification.');
    $this->assertRaw('If you enjoy and value Drupal and the Webform module consider');

    // Close all notifications, promotion, and welcome messages.
    $this->drupalGet('/admin/structure/webform');
    $this->clickLink('×', 0);
    $this->drupalGet('/admin/structure/webform');
    $this->clickLink('×', 0);
    $this->drupalGet('/admin/structure/webform');
    $this->clickLink('×', 0);

    // Check notifications, promotion, and welcome messages closed.
    $this->drupalGet('/admin/structure/webform');
    $this->assertNoRaw('This is a warning notification.');
    $this->assertNoRaw('This is an info notification.');
    $this->assertNoRaw('If you enjoy and value Drupal and the Webform module consider');

    // Check that help is enabled.
    $this->drupalGet('/admin/structure/webform/config/advanced');
    $this->assertPattern('#<div id="block-[^"]+" role="complementary">#');
    $this->assertRaw('The <strong>Advanced configuration</strong> page allows an administrator to enable/disable UI behaviors, manage requirements and define data used for testing webforms.');

    // Disable help via the UI which will clear the cached help block.
    $this->drupalPostForm('/admin/structure/webform/config/advanced', ['ui[help_disabled]' => TRUE], 'Save configuration');

    // Check that help is disabled.
    $this->drupalGet('/admin/structure/webform/config/advanced');
    $this->assertNoPattern('#<div id="block-[^"]+" role="complementary">#');
    $this->assertNoRaw('The <strong>Advanced configuration</strong> page allows an administrator to enable/disable UI behaviors, manage requirements and define data used for testing webforms.');

  }

}
