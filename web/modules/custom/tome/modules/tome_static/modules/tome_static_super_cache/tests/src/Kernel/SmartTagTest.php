<?php

namespace Drupal\Tests\tome_static_super_cache\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;

/**
 * Tests that the smart tag views cache plugin works.
 *
 * @coversDefaultClass \Drupal\tome_static_super_cache\Plugin\views\cache\SmartTag
 * @group tome
 */
class SmartTagTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_static',
    'tome_static_super_cache',
    'tome_static_super_cache_test',
    'node',
    'views',
  ];

  /**
   * @covers \Drupal\tome_static_super_cache\Plugin\views\cache\SmartTag::getCacheTags
   */
  public function testSmartTag() {
    $this->installConfig(['tome_static_super_cache_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    DateFormat::create([
      'pattern' => 'D, m/d/Y - H:i',
      'id' => 'medium',
    ])->save();

    /** @var \Drupal\node\Entity\Node $article */
    $article = Node::create(['type' => 'article', 'title' => 'My article']);

    drupal_static_reset('tome_static_super_cache_entity_insert');
    $article->save();

    $this->assertContains('/test', $static->getPaths());

    $static->requestPath('/test');

    $this->assertNotContains('/test', $static->getPaths());

    drupal_static_reset('tome_static_super_cache_entity_insert');
    $article->save();

    $this->assertContains('/test', $static->getPaths());

    $static->requestPath('/test');

    $this->assertNotContains('/test', $static->getPaths());

    /** @var \Drupal\node\Entity\Node $article */
    $article = Node::create(['type' => 'article', 'title' => 'My other article']);
    $article->setUnpublished();

    drupal_static_reset('tome_static_super_cache_entity_insert');
    $article->save();

    $this->assertNotContains('/test', $static->getPaths());

    $article->setPublished();

    drupal_static_reset('tome_static_super_cache_entity_insert');
    $article->save();

    $this->assertContains('/test', $static->getPaths());

    $static->requestPath('/test');

    $this->assertNotContains('/test', $static->getPaths());
  }

}
