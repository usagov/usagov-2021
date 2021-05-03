<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the path alias normalization works.
 *
 * @group tome_sync
 */
class PathAliasTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'path_alias',
    'tome_sync',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('tome_sync', ['tome_sync_content_hash']);
    $this->installEntitySchema('path_alias');
  }

  /**
   * Tests that exporting/importing path alias entities works.
   */
  public function testAliases() {
    /** @var \Symfony\Component\Serializer\Serializer $serializer */
    $serializer = \Drupal::service('serializer');

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
    ]);
    $article->save();

    $article2 = Node::create([
      'type' => 'article',
      'title' => 'My article',
    ]);
    $article2->save();

    $alias = PathAlias::create([
      'path' => '/node/' . $article->id(),
      'alias' => '/node/' . $article2->id(),
    ]);
    $alias->save();

    $data = $storage->read(TomeSyncHelper::getContentName($alias));

    $this->assertEquals($data['path'][0]['value'], '/node/' . $article->uuid());
    $this->assertEquals($data['alias'][0]['value'], '/node/' . $article2->uuid());

    /** @var \Drupal\path_alias\Entity\PathAlias $exported_alias */
    $exported_alias = $serializer->denormalize($data, PathAlias::class, 'json');
    $this->assertEquals($exported_alias->getPath(), '/node/' . $article->id());
    $this->assertEquals($exported_alias->getAlias(), '/node/' . $article2->id());

    $index_file = Settings::get('tome_content_directory', '../content') . '/meta/index.json';
    $this->assertFileExists($index_file);
    $index = json_decode(file_get_contents($index_file), TRUE);
    $alias_name = TomeSyncHelper::getContentName($alias);
    $article_name = TomeSyncHelper::getContentName($article);
    $articl2_name = TomeSyncHelper::getContentName($article2);
    $this->assertArrayHasKey($alias_name, $index);
    $this->assertContains($article_name, $index[$alias_name]);
    $this->assertContains($articl2_name, $index[$alias_name]);
  }

}
