<?php

namespace Drupal\Tests\redirect\Kernel\Migrate\d6;

use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\migrate_drupal\Kernel\MigrateDrupalTestBase;


/**
 * Tests the d6_path_redirect source plugin.
 *
 * @group redirect
 */
class PathRedirectTest extends MigrateDrupalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['redirect', 'link', 'path_alias'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('redirect');
    $this->loadFixture( __DIR__ . '/../../../../../tests/fixtures/drupal6.php');

    $this->executeMigrations(['d6_path_redirect']);
  }

  /**
   * Tests the Drupal 6 path redirect to Drupal 8 migration.
   */
  public function testPathRedirect() {

    /** @var Redirect $redirect */
    $redirect = Redirect::load(5);
    $this->assertSame($this->getMigration('d6_path_redirect')
      ->getIdMap()
      ->lookupDestinationIds([5]), [[$redirect->id()]]);
    $this->assertSame("/test/source/url", $redirect->getSourceUrl());
    $this->assertSame("base:test/redirect/url", $redirect->getRedirectUrl()->toUriString());

    $redirect = Redirect::load(7);
    $this->assertSame("/test/source/url2", $redirect->getSourceUrl());
    $this->assertSame("http://test/external/redirect/url?foo=bar&biz=buz", $redirect->getRedirectUrl()->toUriString());
  }
}
