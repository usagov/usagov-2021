<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\redirect\Entity\Redirect;
use Drupal\Tests\tome_base\Kernel\TestBase;

/**
 * Tests that static redirect generation works.
 *
 * @coversDefaultClass \Drupal\tome_static\StaticGenerator
 * @group legacy
 */
class StaticRedirectTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_static',
    'link',
    'redirect',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('redirect');
    // @see \Drupal\redirect\RedirectChecker::canRedirect
    \Drupal::requestStack()->getCurrentRequest()->server->set('SCRIPT_NAME', 'index.php');
  }

  /**
   * @covers \Drupal\tome_static\StaticGenerator::requestPath
   * @covers \Drupal\tome_static\EventSubscriber\RedirectPathSubscriber::collectPaths
   * @covers \Drupal\tome_static\EventSubscriber\RedirectPathSubscriber::replacePathPlaceholder
   */
  public function testRedirect() {
    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');
    $base_dir = Settings::get('tome_static_directory');

    $redirect = Redirect::create();
    $redirect->setSource('test');
    $redirect->setRedirect('node');
    $redirect->setStatusCode(301);
    $redirect->save();

    $invoke_paths = $static->requestPath('_redirect:' . $redirect->id());
    $this->assertFileExists("$base_dir/test/index.html");
    $this->assertStringContainsString('<meta http-equiv="refresh"', file_get_contents("$base_dir/test/index.html"));
    $this->assertContains('/node', $invoke_paths);
  }

}
