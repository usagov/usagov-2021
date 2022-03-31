<?php

namespace Drupal\Tests\tome_sync\Functional;

use Drupal\Core\Site\Settings;
use Drupal\Tests\book\Functional\BookTestTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests that book support works.
 *
 * @group tome_sync
 */
class BookTest extends BrowserTestBase {

  use BookTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'book',
    'block',
    'tome_sync',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * A user with permission to view a book and access printer-friendly version.
   *
   * @var object
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('page_title_block');

    // Note: The format for ::writeSettings is really weird.
    $this->writeSettings([
      'settings' => [
        'tome_content_directory' => (object) [
          'value' => $this->siteDirectory . '/files/tome/content',
          'required' => TRUE,
        ],
        'tome_book_outline_directory' => (object) [
          'value' => $this->siteDirectory . '/files/tome/extra',
          'required' => TRUE,
        ],
      ],
    ]);
    $this->bookAuthor = $this->drupalCreateUser([
      'create new books',
      'create book content',
      'edit own book content',
      'add content to books',
    ]);
    $this->webUser = $this->drupalCreateUser([
      'access printer-friendly version',
    ]);
  }

  /**
   * @covers \Drupal\tome_sync\EventSubscriber\BookEventSubscriber::exportBookOutlines
   * @covers \Drupal\tome_sync\EventSubscriber\BookEventSubscriber::importBookOutlines
   */
  public function testBook() {
    // Create a book.
    $nodes = $this->createBook();
    $book = $this->book;

    // Assert that the exported JSON is not empty.
    $index_file = Settings::get('tome_book_outline_directory') . '/book_outlines.json';
    $this->assertFileExists($index_file);
    $outlines = json_decode(file_get_contents($index_file), TRUE);
    $this->assertNotEmpty($outlines);

    // Delete the book outline from the database.
    \Drupal::database()->truncate('book')->execute();

    // Trigger an import.
    \Drupal::service('tome_sync.book_event_subscriber')->importBookOutlines();

    // Test the book.
    $this->drupalLogin($this->webUser);
    $this->checkBookNode($book, [$nodes[0], $nodes[3], $nodes[4]], FALSE, FALSE, $nodes[0], []);
    $this->checkBookNode($nodes[0], [$nodes[1], $nodes[2]], $book, $book, $nodes[1], [$book]);
    $this->checkBookNode($nodes[1], NULL, $nodes[0], $nodes[0], $nodes[2], [$book, $nodes[0]]);
    $this->checkBookNode($nodes[2], NULL, $nodes[1], $nodes[0], $nodes[3], [$book, $nodes[0]]);
    $this->checkBookNode($nodes[3], NULL, $nodes[2], $book, $nodes[4], [$book]);
    $this->checkBookNode($nodes[4], NULL, $nodes[3], $book, FALSE, [$book]);
  }

}
