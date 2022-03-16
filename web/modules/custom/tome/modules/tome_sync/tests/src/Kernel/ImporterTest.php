<?php

namespace Drupal\Tests\tome_sync\Kernel;

use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the importer works.
 *
 * @coversDefaultClass \Drupal\tome_sync\Importer
 * @group tome_sync
 */
class ImporterTest extends TestBase {

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
   * @covers \Drupal\tome_sync\Importer::importContent
   */
  public function testImportContent() {
    /** @var \Drupal\Core\Entity\EntityRepository $repository */
    $repository = \Drupal::service('entity.repository');

    /** @var \Drupal\tome_sync\Exporter $exporter */
    $exporter = \Drupal::service('tome_sync.exporter');
    /** @var \Drupal\tome_sync\Importer $importer */
    $importer = \Drupal::service('tome_sync.importer');

    $uuid = \Drupal::service('uuid')->generate();
    $article = Node::create([
      'type' => 'article',
      'title' => 'My article',
      'uuid' => $uuid,
    ]);
    $exporter->exportContent($article);

    $this->assertFalse((bool) $repository->loadEntityByUuid('node', $uuid));
    $importer->importContent('node', $uuid);
    $this->assertTrue((bool) $repository->loadEntityByUuid('node', $uuid));

    $translation = $article->addTranslation('fr', ['title' => 'My french article']);
    $exporter->exportContent($translation);

    $this->assertFalse($repository->loadEntityByUuid('node', $uuid)->hasTranslation('fr'));
    $importer->importContent('node', $uuid, 'fr');
    $this->assertTrue($repository->loadEntityByUuid('node', $uuid)->hasTranslation('fr'));

    $this->assertEquals([
      [TomeSyncHelper::getContentName($article)],
      [TomeSyncHelper::getContentName($article->getTranslation('fr'))],
    ], $importer->getChunkedNames());

    $article->setTitle('New title');
    $exporter->exportContent($article);
    $this->assertEquals($repository->loadEntityByUuid('node', $uuid)->getTitle(), 'My article');
    $importer->importContent('node', $uuid);
    $this->assertTrue($repository->loadEntityByUuid('node', $uuid)->hasTranslation('fr'));
    $this->assertEquals($repository->loadEntityByUuid('node', $uuid)->getTitle(), 'New title');

    $translation->setTitle('New french title');
    $exporter->exportContent($translation);
    $this->assertEquals($repository->loadEntityByUuid('node', $uuid)->getTranslation('fr')->getTitle(), 'My french article');
    $importer->importContent('node', $uuid, 'fr');
    $this->assertEquals($repository->loadEntityByUuid('node', $uuid)->getTranslation('fr')->getTitle(), 'New french title');
  }

}
