<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the exporter works.
 *
 * @group tome_sync
 */
class ExporterTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_sync',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('tome_sync', ['tome_sync_content_hash']);
    if (\Drupal::entityTypeManager()->hasDefinition('path_alias')) {
      $this->installEntitySchema('path_alias');
    }
  }

  /**
   * @covers \Drupal\tome_sync\Exporter::exportContent
   */
  public function testExportContent() {
    /** @var \Symfony\Component\Serializer\Serializer $serializer */
    $serializer = \Drupal::service('serializer');

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $user = $this->createUser();

    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
      'uid' => $user->id(),
    ]);
    $article->save();

    $page = Node::create([
      'type' => 'page',
      'title' => 'My page',
      'field_reference' => [
        'target_id' => $article->id(),
      ],
      'uid' => $user->id(),
    ]);
    $page->save();

    $data = $storage->read(TomeSyncHelper::getContentName($page));
    $exported_page = $serializer->denormalize($data, Node::class, 'json');
    $this->assertEquals($page->uuid(), $exported_page->uuid());
    $this->assertEquals($page->getTitle(), $exported_page->getTitle());
    $this->assertEquals($article->id(), $exported_page->field_reference->target_id);

    $page->addTranslation('fr', ['title' => 'My french page'])->save();
    $data = $storage->read(TomeSyncHelper::getContentName($page->getTranslation('fr')));
    $exported_page = $serializer->denormalize($data, Node::class, 'json');
    $this->assertEquals('My french page', $exported_page->getTitle());

    $index_file = Settings::get('tome_content_directory', '../content') . '/meta/index.json';
    $this->assertFileExists($index_file);
    $index = json_decode(file_get_contents($index_file), TRUE);
    $user_name = TomeSyncHelper::getContentName($user);
    $page_name = TomeSyncHelper::getContentName($page);
    $article_name = TomeSyncHelper::getContentName($article);
    $this->assertArrayHasKey($user_name, $index);
    $this->assertArrayHasKey($page_name, $index);
    $this->assertArrayHasKey($article_name, $index);
    $this->assertEmpty($index[$user_name]);
    $this->assertContains($article_name, $index[$page_name]);
    $this->assertContains($user_name, $index[$page_name]);
    $this->assertContains($user_name, $index[$article_name]);
  }

  /**
   * @covers \Drupal\tome_sync\Exporter::deleteContentExport
   */
  public function testDeleteContentExport() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');
    $article = Node::create(['type' => 'article', 'title' => 'My article']);
    $article->save();
    $this->assertTrue($storage->exists(TomeSyncHelper::getContentName($article)));

    $article_name = TomeSyncHelper::getContentName($article);

    $index_file = Settings::get('tome_content_directory', '../content') . '/meta/index.json';
    $this->assertFileExists($index_file);
    $index = json_decode(file_get_contents($index_file), TRUE);
    $this->assertArrayHasKey($article_name, $index);

    $article->addTranslation('fr', ['title' => 'My french article'])->save();
    $name = TomeSyncHelper::getContentName($article->getTranslation('fr'));
    $this->assertTrue($storage->exists($name));

    $article->removeTranslation('fr');
    $article->save();
    $this->assertFalse($storage->exists($name));

    $article->addTranslation('fr', ['title' => 'My french article'])->save();
    $this->assertTrue($storage->exists($name));

    $article->delete();
    $this->assertFalse($storage->exists(TomeSyncHelper::getContentName($article)));
    $this->assertFalse($storage->exists($name));

    $index = json_decode(file_get_contents($index_file), TRUE);
    $this->assertArrayNotHasKey($article_name, $index);
  }

  /**
   * @covers \Drupal\tome_sync\EventSubscriber\ConfigEventSubscriber::configDelete
   * @covers \Drupal\tome_sync\EventSubscriber\LanguageConfigEventSubscriber::configDelete
   */
  public function testDeleteConfigExport() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('config.storage.sync');

    \Drupal::configFactory()->getEditable('foo.bar')->save();
    $this->assertTrue($storage->exists('foo.bar'));

    /** @var \Drupal\language\Config\LanguageConfigFactoryOverrideInterface $config_factory_override */
    $config_factory_override = \Drupal::service('language.config_factory_override');
    $override = $config_factory_override->getOverride('fr', 'foo.bar');
    $override->setData(['bonjour' => 'monde'])->save();
    $collection = $storage->createCollection($override->getStorage()->getCollectionName());
    $this->assertTrue($collection->exists('foo.bar'));

    \Drupal::configFactory()->getEditable('foo.bar')->delete();
    $this->assertFalse($storage->exists('foo.bar'));
    $this->assertFalse($collection->exists('foo.bar'));
  }

  /**
   * @covers \Drupal\tome_sync\EventSubscriber\ConfigEventSubscriber::configSave
   * @covers \Drupal\tome_sync\EventSubscriber\LanguageConfigEventSubscriber::configSave
   */
  public function testExportConfig() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('config.storage.sync');
    $data = ['hello' => 'world'];
    \Drupal::configFactory()->getEditable('foo.bar')
      ->setData($data)
      ->save();
    $this->assertEquals($data, $storage->read('foo.bar'));

    $data = ['bonjour' => 'monde'];
    /** @var \Drupal\language\Config\LanguageConfigFactoryOverrideInterface $config_factory_override */
    $config_factory_override = \Drupal::service('language.config_factory_override');
    $override = $config_factory_override->getOverride('fr', 'foo.bar');
    $override->setData($data)->save();
    $collection = $storage->createCollection($override->getStorage()->getCollectionName());
    $this->assertEquals($data, $collection->read('foo.bar'));
  }

  /**
   * @covers \Drupal\tome_sync\EventSubscriber\ConfigEventSubscriber::configRename
   * @covers \Drupal\tome_sync\EventSubscriber\LanguageConfigEventSubscriber::configSave
   * @covers \Drupal\tome_sync\EventSubscriber\LanguageConfigEventSubscriber::configDelete
   */
  public function testRenameConfig() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('config.storage.sync');
    $data = ['hello' => 'world'];
    \Drupal::configFactory()->getEditable('foo.bar')
      ->setData($data)->save();
    $this->assertEquals($data, $storage->read('foo.bar'));

    $override_data = ['bonjour' => 'monde'];
    /** @var \Drupal\language\Config\LanguageConfigFactoryOverrideInterface $config_factory_override */
    $config_factory_override = \Drupal::service('language.config_factory_override');
    $override = $config_factory_override->getOverride('fr', 'foo.bar');
    $override->setData($override_data)->save();
    $collection = $storage->createCollection($override->getStorage()->getCollectionName());
    $this->assertEquals($override_data, $collection->read('foo.bar'));

    \Drupal::configFactory()->rename('foo.bar', 'bar.baz');
    $this->assertEquals($data, $storage->read('bar.baz'));
    $this->assertFalse($storage->exists('foo.bar'));

    // @todo Add test coverage when https://www.drupal.org/project/drupal/issues/2981444
    // is fixed.
    // $this->assertEquals($override_data, $collection->read('bar.baz'));
    $this->assertFalse($collection->exists('foo.bar'));
  }

  /**
   * @covers \Drupal\tome_sync\Exporter::getContentToExport
   */
  public function testExcludedTypes() {
    $this->enableModules(['content_moderation', 'workflows']);
    $this->installEntitySchema('content_moderation_state');
    $this->assertArrayNotHasKey('content_moderation_state', \Drupal::service('tome_sync.exporter')->getContentToExport());
  }

}
