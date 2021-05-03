<?php

namespace Drupal\Tests\paragraphs\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that Paragraphs module can be uninstalled.
 *
 * @group paragraphs
 */
class ParagraphsUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('paragraphs_demo');

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(array(
      'administer paragraphs types',
      'administer modules',
    ));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that Paragraphs module can be uninstalled.
   */
  public function testUninstall() {

    // Uninstall the module paragraphs_demo.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[paragraphs_demo]' => TRUE], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));

    // Delete library data.
    $this->clickLink('Remove Paragraphs library items');
    $this->drupalPostForm(NULL, [], t('Delete all Paragraphs library items'));

    // Uninstall the library module.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[paragraphs_library]' => TRUE], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));

    // Delete paragraphs data.
    $this->clickLink('Remove Paragraphs');
    $this->drupalPostForm(NULL, [], t('Delete all Paragraphs'));

    // Uninstall the module paragraphs.
    $this->drupalPostForm('admin/modules/uninstall', ['uninstall[paragraphs]' => TRUE], t('Uninstall'));
    $this->drupalPostForm(NULL, [], t('Uninstall'));
    $this->assertSession()->pageTextContains(t('The selected modules have been uninstalled.'));
    $this->assertSession()->pageTextNotContains(t('Paragraphs demo'));
    $this->assertSession()->pageTextNotContains(t('Paragraphs library'));
    $this->assertSession()->pageTextNotContains(t('Paragraphs'));
  }

}
