<?php

namespace Drupal\Tests\tome_static\Functional;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the preview functionality of Tome Static.
 *
 * @group tome_static
 */
class TomeStaticPreviewTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['tome_static'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the static preview.
   */
  public function testStaticPreview() {
    $directory = $this->siteDirectory . '/files/tome/static';
    $settings['settings']['tome_static_directory'] = (object) [
      'value' => $directory,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    $this->drupalLogin($this->createUser(['use tome static']));
    $directory .= '/foo';
    \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents($directory . '/index.html', '<html><body>Hello world!</body></html>');

    $this->drupalGet('/admin/config/tome/static/preview');
    $this->submitForm([], 'Preview');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Request path not present in the static build.');
    $this->drupalGet('/foo');
    $assert_session->pageTextContains('Hello world!');
    $this->clickLink('Exit preview');
    $assert_session->addressEquals('/admin/config/tome/static/preview');
  }

}
