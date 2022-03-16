<?php

namespace Drupal\Tests\tome_static_super_cache\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_static_super_cache\SuperStaticCache;
use Drupal\user\Entity\User;

/**
 * Tests that the super static cache works.
 *
 * @coversDefaultClass \Drupal\tome_static_super_cache\SuperStaticCache
 * @group tome
 */
class SuperStaticCacheTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_static',
    'tome_static_super_cache',
    'node',
  ];

  /**
   * @covers \Drupal\tome_static_super_cache\SuperStaticCache::deleteAll
   */
  public function testSuperCache() {
    User::create([
      'uid' => 0,
      'status' => 0,
      'name' => '',
    ])->save();

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    DateFormat::create([
      'pattern' => 'D, m/d/Y - H:i',
      'id' => 'medium',
    ])->save();

    /** @var \Drupal\node\Entity\Node $article */
    $article = Node::create(['type' => 'article', 'title' => 'My article']);
    $article->save();

    $path = '_entity:node:en:' . $article->id();

    $this->assertContains($path, $static->getPaths());

    $static->requestPath($path);

    $this->assertNotContains($path, $static->getPaths());

    drupal_flush_all_caches();

    $this->assertNotContains($path, $static->getPaths());

    $GLOBALS[SuperStaticCache::FULL_REBUILD_KEY] = TRUE;

    drupal_flush_all_caches();

    $this->assertContains($path, $static->getPaths());
  }

}
