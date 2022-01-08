<?php

namespace Drupal\Tests\tome_sync\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\tome_sync\ContentIndexerTrait;
use Drupal\tome_sync\TomeSyncHelper;

/**
 * Tests that the import partial form works.
 *
 * @group tome_sync
 */
class ImportPartialFormTest extends BrowserTestBase {

  use ContentIndexerTrait;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'tome_sync',
    'config',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Note: The format for ::writeSettings is really weird.
    $this->writeSettings([
      'settings' => [
        'tome_content_directory' => (object) [
          'value' => $this->siteDirectory . '/files/tome/content',
          'required' => TRUE,
        ],
      ],
    ]);
    $this->createContentType([
      'type' => 'article',
    ]);
    $this->drupalLogin($this->createUser(['use tome sync', 'synchronize configuration']));
    $this->fileSystem = \Drupal::service('file_system');
  }

  /**
   * Tests that the import partial form works.
   */
  public function testImportPartialForm() {
    /** @var \Drupal\tome_sync\JsonFileStorage $storage */
    $storage = \Drupal::service('tome_sync.storage.content');

    $node1 = $this->createNode([
      'type' => 'article',
    ]);
    $node1_name = TomeSyncHelper::getContentName($node1);
    $node2 = $this->createNode([
      'type' => 'article',
    ]);
    $node2_name = TomeSyncHelper::getContentName($node2);

    // Test initial state.
    $this->drupalGet('/admin/config/tome/sync/import-partial');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Synchronize content and files');
    $assert_session->pageTextContains('No content has been changed or deleted');

    // Delete content.
    $storage->delete($node1_name);

    // Modify content.
    $content = $storage->read($node2_name);
    $content['title'] = ['Foobar'];
    $storage->write($node2_name, $content);

    // Add content.
    $node3 = Node::create([
      'type' => 'article',
      'title' => 'My article',
      'uuid' => \Drupal::service('uuid')->generate(),
    ]);
    $data = \Drupal::service('serializer')->normalize($node3, 'json');
    $node3_name = TomeSyncHelper::getContentName($node3);
    $storage->write($node3_name, $data);
    $this->indexContent($node3);

    // Check that the page displays the correct information.
    $this->drupalGet('/admin/config/tome/sync/import-partial');
    $assert_session->pageTextNotContains('No content has been changed or deleted');
    $this->assertStringContainsString($node1_name, $assert_session->elementExists('css', '[data-drupal-selector="edit-deleted"]')->getText());
    $this->assertStringContainsString($node2_name, $assert_session->elementExists('css', '[data-drupal-selector="edit-modified"]')->getText());
    $this->assertStringContainsString($node3_name, $assert_session->elementExists('css', '[data-drupal-selector="edit-added"]')->getText());

    // Submit the form and verify that changes were actually made.
    $this->submitForm([], 'Submit');
    $assert_session->pageTextContains('Import complete');
    $assert_session->pageTextContains('No content has been changed or deleted');
  }

}
