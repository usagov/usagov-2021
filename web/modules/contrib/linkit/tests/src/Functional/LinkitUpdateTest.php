<?php

namespace Drupal\Tests\linkit\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests Linkit upgrade paths.
 *
 * @group Update
 * @group legacy
 */
class LinkitUpdateTest extends UpdatePathTestBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->configFactory = $this->container->get('config.factory');
  }

  /**
   * Set database dump files to be used.
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../tests/fixtures/update/drupal-8.linkit-enabled.standard.php.gz',
      __DIR__ . '/../../../tests/fixtures/update/linkit-additions.php',
    ];
  }

  /**
   * Tests linkit_update_X.
   */
  public function testLinkitUpdate8500() {
    $editor = $this->configFactory->get('editor.editor.format_1');
    $this->assertNotEmpty($editor->get('settings.plugins.linkit'), 'We got old linkit settings in the editor configuration.');
    $format_1_linkit_profile = $editor->get('settings.plugins.linkit.linkit_profile');

    $editor = $this->configFactory->get('editor.editor.format_2');
    $this->assertNotEmpty($editor->get('settings.plugins.linkit'), 'We got old linkit settings in the editor configuration.');
    $format_2_linkit_profile = $editor->get('settings.plugins.linkit.linkit_profile');

    $editor = $this->configFactory->get('editor.editor.format_3');
    $this->assertNotEmpty($editor->get('settings.plugins.linkit'), 'We got old linkit settings in the editor configuration.');
    $format_3_linkit_profile = $editor->get('settings.plugins.linkit.linkit_profile');

    $test_profile = $this->configFactory->get('linkit.linkit_profile.test_profile');
    $this->assertNotNull($test_profile->get('matchers.fc48c807-2a9c-44eb-b86b-7e134c1aa252.settings.result_description'), 'Profile have result_description');
    $this->assertNotNull($test_profile->get('third_party_settings.imce.use'), 'Profile have imce use');
    $this->assertNotNull($test_profile->get('third_party_settings.imce.scheme'), 'Profile have imce scheme');

    $this->runUpdates();

    $test_profile = $this->configFactory->get('linkit.linkit_profile.test_profile');
    $this->assertEquals(NULL, $test_profile->get('attributes'), 'Attributes are deleted from the profile.');
    $this->assertEquals('canonical', $test_profile->get('matchers.fc48c807-2a9c-44eb-b86b-7e134c1aa252.settings.substitution_type'), 'Content matcher has a substitution type of canonical.');
    $this->assertEquals('file', $test_profile->get('matchers.b8d6d672-6377-493f-b492-3cc69511cf17.settings.substitution_type'), 'File matcher has a substitution type of file.');
    $this->assertNull($test_profile->get('matchers.fc48c807-2a9c-44eb-b86b-7e134c1aa252.settings.result_description'), 'Profile does not have result_description');
    $this->assertNotNull($test_profile->get('matchers.fc48c807-2a9c-44eb-b86b-7e134c1aa252.settings.metadata'), 'Profile have metadata');
    $this->assertNull($test_profile->get('third_party_settings.imce.use'), 'Profile does not have imce use');
    $this->assertNull($test_profile->get('third_party_settings.imce.scheme'), 'Profile does not have imce scheme');

    $editor = $this->configFactory->get('editor.editor.format_1');
    $this->assertNull($editor->get('settings.plugins.linkit'), 'Old linkit settings in the editor configuration is removed.');
    $this->assertEquals($editor->get('settings.toolbar.rows.0.1.items.0'), 'DrupalLink', 'Drupal link plugin is in the toolbar.');
    $this->assertNotEquals($editor->get('settings.toolbar.rows.0.1.items.1'), 'Linkit', 'Linkit plugin is removed from the toolbar.');
    $this->assertNotEmpty($editor->get('settings.plugins.drupallink.linkit_enabled'), 'Drupal link plugin has linkit enabled.');
    $this->assertEquals($editor->get('settings.plugins.drupallink.linkit_profile'), $format_1_linkit_profile, 'Drupal link plugin uses the same profile as the old linkit plugin.');

    $editor = $this->configFactory->get('editor.editor.format_2');
    $this->assertNull($editor->get('settings.plugins.linkit'), 'Old linkit settings in the editor configuration is removed.');
    $this->assertEquals($editor->get('settings.toolbar.rows.0.1.items.0'), 'DrupalLink', 'Drupal link plugin is in the toolbar.');
    $this->assertNotEmpty($editor->get('settings.plugins.drupallink.linkit_enabled'), 'Drupal link plugin has linkit enabled.');
    $this->assertEquals($editor->get('settings.plugins.drupallink.linkit_profile'), $format_2_linkit_profile, 'Drupal link plugin uses the same profile as the old linkit plugin.');

    $editor = $this->configFactory->get('editor.editor.format_3');
    $this->assertNull($editor->get('settings.plugins.linkit'), 'Old linkit settings in the editor configuration is removed.');
    $this->assertEquals($editor->get('settings.toolbar.rows.0.0.items.0'), 'DrupalLink', 'Drupal link plugin is in the toolbar.');
    $this->assertNotEmpty($editor->get('settings.plugins.drupallink.linkit_enabled'), 'Drupal link plugin has linkit enabled.');
    $this->assertEquals($editor->get('settings.plugins.drupallink.linkit_profile'), $format_3_linkit_profile, 'Drupal link plugin uses the same profile as the old linkit plugin.');

    $format = $this->configFactory->get('filter.format.format_1');
    $this->assertNotNull($format->get('filters.linkit'), 'Linkit filter is enabled.');
    $this->assertTrue($format->get('filters.linkit.weight') < $format->get('filters.filter_html.weight'), 'Linkit filter is running before filter_html.');

    $format = $this->configFactory->get('filter.format.format_2');
    $this->assertNotNull($format->get('filters.linkit'), 'Linkit filter is enabled.');

    $format = $this->configFactory->get('filter.format.format_3');
    $this->assertNotNull($format->get('filters.linkit'), 'Linkit filter is enabled.');

    $htmlRestrictions = FilterFormat::load('format_1')->getHtmlRestrictions();
    $this->assertArrayHasKey("data-entity-type", $htmlRestrictions['allowed']['a']);
    $this->assertArrayHasKey("data-entity-uuid", $htmlRestrictions['allowed']['a']);
    $this->assertArrayHasKey("data-entity-substitution", $htmlRestrictions['allowed']['a']);

    $htmlRestrictions = FilterFormat::load('format_3')->getHtmlRestrictions();
    $this->assertArrayHasKey("data-entity-type", $htmlRestrictions['allowed']['a']);
    $this->assertArrayHasKey("data-entity-uuid", $htmlRestrictions['allowed']['a']);
  }

}
