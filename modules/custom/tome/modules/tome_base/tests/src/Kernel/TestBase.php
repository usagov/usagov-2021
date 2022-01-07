<?php

namespace Drupal\Tests\tome_base\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Base class for tome tests.
 */
abstract class TestBase extends KernelTestBase {

  use EntityReferenceTestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
    'language',
    'system',
    'field',
    'node',
    'text',
    'user',
    'file',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpFilesystem();

    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['tome_files_directory'] = $this->siteDirectory . '/files/tome/files';
    $settings['tome_content_directory'] = $this->siteDirectory . '/files/tome/content';
    $settings['tome_static_directory'] = $this->siteDirectory . '/files/tome/static';
    new Settings($settings);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['user', 'node']);
    $this->installEntitySchema('file');
    $this->installSchema('node', 'node_access');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('system', 'sequences');

    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->config('language.negotiation')->set('url.prefixes', [
      'en' => '',
      'fr' => 'french',
    ])->save();

    \Drupal::service('kernel')->rebuildContainer();

    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager */
    $content_translation_manager = $this->container->get('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'page', TRUE);
    $content_translation_manager->setEnabled('node', 'article', TRUE);

    $anonymous_role = Role::load(Role::ANONYMOUS_ID);
    $anonymous_role->grantPermission('access content');
    $anonymous_role->save();

    $this->createEntityReferenceField('node', 'page', 'field_reference', 'Reference', 'node');
  }

}
