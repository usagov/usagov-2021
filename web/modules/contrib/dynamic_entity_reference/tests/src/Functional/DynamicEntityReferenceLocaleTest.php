<?php

namespace Drupal\Tests\dynamic_entity_reference\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\Gettext;

/**
 * Tests localization aspects of the module.
 *
 * @group dynamic_entity_reference
 */
class DynamicEntityReferenceLocaleTest extends DynamicEntityReferenceTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $file = new \stdClass();
    $file->uri = \Drupal::service('file_system')->realpath(drupal_get_path('module', 'dynamic_entity_reference') . '/tests/test.de.po');
    $file->langcode = 'de';
    Gettext::fileToDatabase($file, []);

    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->config('system.site')->set('default_langcode', 'de')->save();
    // Rebuild the container to update the default language container variable.
    $this->rebuildContainer();
  }

}
