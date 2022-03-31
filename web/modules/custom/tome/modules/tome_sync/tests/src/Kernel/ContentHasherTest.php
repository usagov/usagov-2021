<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the content hasher works.
 *
 * @coversDefaultClass \Drupal\tome_sync\ContentHasher
 * @group tome_sync
 */
class ContentHasherTest extends TestBase {

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
   * @covers \Drupal\tome_sync\ContentHasher::writeHash
   * @covers \Drupal\tome_sync\EventSubscriber\ContentHasherEventSubscriber::writeHash
   */
  public function testContentHasher() {
    /** @var \Drupal\tome_sync\ContentHasherInterface $content_hasher */
    $content_hasher = \Drupal::service('tome_sync.content_hasher');

    $uuid = \Drupal::service('uuid')->generate();
    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
      'uuid' => $uuid,
    ]);
    $article->save();
    $content_name = TomeSyncHelper::getContentName($article);

    // If the hash in the database is different, the content is changed.
    $this->assertTrue(empty($content_hasher->getChangelist()['modified']));
    $content_hasher->writeHash('foo', $content_name);
    $this->assertFalse(empty($content_hasher->getChangelist()['modified']));

    // If the hash in the database is missing, the content is new.
    $this->assertTrue(empty($content_hasher->getChangelist()['added']));
    \Drupal::database()->truncate('tome_sync_content_hash')->execute();
    $this->assertFalse(empty($content_hasher->getChangelist()['added']));
    $this->assertTrue(empty($content_hasher->getChangelist()['modified']));

    // If the hash is missing completely, nothing happens.
    $article->delete();
    $this->assertTrue(empty($content_hasher->getChangelist()['added']));
    $this->assertTrue(empty($content_hasher->getChangelist()['modified']));

    // If the hash is only in the database, the content is deleted.
    $this->assertTrue(empty($content_hasher->getChangelist()['deleted']));
    $content_hasher->writeHash('foo', $content_name);
    $this->assertFalse(empty($content_hasher->getChangelist()['deleted']));

    // If the hashes are the same, nothing happens.
    $content_hasher->deleteHash($content_name);
    $this->assertTrue(empty($content_hasher->getChangelist()['deleted']));
    $this->assertTrue(empty($content_hasher->getChangelist()['added']));
    $this->assertTrue(empty($content_hasher->getChangelist()['modified']));
  }

}
