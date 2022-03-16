<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\node\Entity\Node;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\pathauto\PathautoState;
use Drupal\Tests\tome_base\Kernel\TestBase;

/**
 * Tests that the pathuto integration works.
 *
 * @group tome_sync
 */
class PathautoTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'path_alias',
    'pathauto',
    'ctools',
    'path',
    'token',
    'tome_sync',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('tome_sync', ['tome_sync_content_hash']);
    $this->installEntitySchema('path_alias');

    DateFormat::create([
      'pattern' => 'D, m/d/Y - H:i',
      'id' => 'fallback',
    ])->save();

    $pattern = PathautoPattern::create([
      'id' => mb_strtolower($this->randomMachineName()),
      'type' => 'canonical_entities:node',
      'pattern' => '/node/[node:title]',
    ]);
    $pattern->save();
  }

  /**
   * Tests that exporting/importing nodes with patterns works.
   */
  public function testPathauto() {
    /** @var \Drupal\Core\Entity\EntityRepository $repository */
    $repository = \Drupal::service('entity.repository');
    /** @var \Drupal\tome_sync\Importer $importer */
    $importer = \Drupal::service('tome_sync.importer');
    /** @var \Drupal\tome_sync\Exporter $exporter */
    $exporter = \Drupal::service('tome_sync.exporter');

    // Make sure normal entities work with our pattern.
    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
    ]);
    $article->save();
    $source = '/' . $article->toUrl()->getInternalPath();
    \Drupal::service('path_alias.manager')->cacheClear($source);
    $this->assertNotEquals($source, \Drupal::service('path_alias.manager')->getAliasByPath($source, 'en'));

    // Test that imported entities that use pathauto do not have aliases
    // automatically created, since path_alias entities should be imported
    // anyway.
    // @see tome_sync_pathauto_alias_alter()
    $uuid = \Drupal::service('uuid')->generate();
    $article = Node::create([
      'type' => 'article',
      'title' => 'Another article',
      'uuid' => $uuid,
      'path' => [
        'path' => '',
        'pathauto' => PathautoState::CREATE,
      ],
    ]);
    $exporter->exportContent($article);
    $importer->importContent('node', $uuid);
    $article = $repository->loadEntityByUuid('node', $uuid);
    $source = '/' . $article->toUrl()->getInternalPath();
    \Drupal::service('path_alias.manager')->cacheClear($source);
    $this->assertEquals($source, \Drupal::service('path_alias.manager')->getAliasByPath($source, 'en'));
  }

}
