<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that exports are updated when fields are deleted.
 *
 * @group tome_sync
 */
class FieldDeletionTest extends TestBase {

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
  }

  /**
   * @covers \Drupal\tome_sync\Exporter::exportContent
   */
  public function testDeleteField() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $field_config = node_add_body_field(NodeType::load('article'));
    node_add_body_field(NodeType::load('page'));

    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
    ]);
    $article->save();

    $page = Node::create([
      'type' => 'page',
      'title' => 'My page',
    ]);
    $page->save();

    $data = $storage->read(TomeSyncHelper::getContentName($article));
    $this->assertArrayHasKey('body', $data);

    $data = $storage->read(TomeSyncHelper::getContentName($page));
    $this->assertArrayHasKey('body', $data);

    $field_config->delete();

    $data = $storage->read(TomeSyncHelper::getContentName($article));
    $this->assertArrayNotHasKey('body', $data);

    $data = $storage->read(TomeSyncHelper::getContentName($page));
    $this->assertArrayHasKey('body', $data);

    FieldStorageConfig::loadByName('node', 'body')->delete();

    $data = $storage->read(TomeSyncHelper::getContentName($page));
    $this->assertArrayNotHasKey('body', $data);
  }

  /**
   * @covers \Drupal\tome_sync\Exporter::exportContent
   */
  public function testDeleteFieldDuringSync() {
    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $field_config = node_add_body_field(NodeType::load('article'));
    node_add_body_field(NodeType::load('page'));

    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
    ]);
    $article->save();

    $data = $storage->read(TomeSyncHelper::getContentName($article));
    $this->assertArrayHasKey('body', $data);

    /** @var \Drupal\Core\Config\ConfigInstallerInterface $installer */
    $installer = \Drupal::service('config.installer');
    $installer->setSyncing(TRUE);
    $field_config->delete();
    $installer->setSyncing(FALSE);

    $data = $storage->read(TomeSyncHelper::getContentName($article));
    $this->assertArrayHasKey('body', $data);
  }

}
