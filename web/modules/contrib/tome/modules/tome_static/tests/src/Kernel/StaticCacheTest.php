<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\user\Entity\User;

/**
 * Tests that static cache works.
 *
 * @coversDefaultClass \Drupal\tome_static\StaticCache
 * @group tome
 */
class StaticCacheTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_static',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    User::create([
      'uid' => 0,
      'status' => 0,
      'name' => '',
    ])->save();
  }

  /**
   * @covers \Drupal\tome_static\StaticGenerator::getPaths
   */
  public function testCache() {
    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');
    $base_dir = Settings::get('tome_static_directory');

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

    $article->save();

    $this->assertContains($path, $static->getPaths());

    $static->requestPath($path);

    $this->assertNotContains($path, $static->getPaths());

    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['tome_static_cache_exclude'] = [$path];
    new Settings($settings);

    $this->assertContains($path, $static->getPaths());

    $settings['tome_static_cache_exclude'] = ['/_entity:node:.*/'];
    new Settings($settings);

    $this->assertContains($path, $static->getPaths());

    $settings['tome_static_cache_exclude'] = [];
    new Settings($settings);

    $this->assertNotContains($path, $static->getPaths());

    $this->assertFileExists("$base_dir/node/1/index.html");
    $article->delete();
    $static->cleanupStaticDirectory();
    $this->assertFileNotExists("$base_dir/node/1/index.html");
  }

}
