<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the link item normalization works.
 *
 * @group tome_sync
 */
class LinkItemTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'link',
    'tome_sync',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('tome_sync', ['tome_sync_content_hash']);
    $this->installEntitySchema('path_alias');

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_link',
      'type' => 'link',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_link',
      'bundle' => 'article',
      'settings' => ['link_type' => LinkItemInterface::LINK_GENERIC],
    ])->save();
  }

  /**
   * Tests that exporting/importing link field data works.
   */
  public function testLinkFields() {
    /** @var \Symfony\Component\Serializer\Serializer $serializer */
    $serializer = \Drupal::service('serializer');

    /** @var \Drupal\Core\Config\StorageInterface $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $article1 = Node::create([
      'type' => 'article',
      'title' => 'Article 1',
    ]);
    $article1->save();

    $article2 = Node::create([
      'type' => 'article',
      'title' => 'My article',
      'field_link' => [
        'uri' => 'entity:node/' . $article1->id(),
      ],
    ]);
    $article2->save();

    $data = $storage->read(TomeSyncHelper::getContentName($article2));

    $this->assertEquals($data['field_link'][0]['uri'], 'entity:node/' . $article1->uuid());

    /** @var \\Drupal\node\Entity\Node $exported_node */
    $exported_node = $serializer->denormalize($data, Node::class, 'json');
    $this->assertEquals($exported_node->get('field_link')->get(0)->get('uri')->getValue(), 'entity:node/' . $article1->id());

    $index_file = Settings::get('tome_content_directory', '../content') . '/meta/index.json';
    $this->assertFileExists($index_file);
    $index = json_decode(file_get_contents($index_file), TRUE);
    $article1_name = TomeSyncHelper::getContentName($article1);
    $article2_name = TomeSyncHelper::getContentName($article2);
    $this->assertArrayHasKey($article2_name, $index);
    $this->assertContains($article1_name, $index[$article2_name]);
  }

}
