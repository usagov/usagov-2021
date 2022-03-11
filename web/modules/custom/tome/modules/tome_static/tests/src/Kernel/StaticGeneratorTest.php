<?php

namespace Drupal\Tests\tome_static\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Site\Settings;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use Drupal\node\Entity\Node;
use Drupal\Tests\tome_base\Kernel\TestBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that static site generation works.
 *
 * @coversDefaultClass \Drupal\tome_static\StaticGenerator
 * @group tome_static
 */
class StaticGeneratorTest extends TestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'tome_static',
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
   * @covers \Drupal\tome_static\EventSubscriber\LanguagePathSubscriber::collectPaths
   * @covers \Drupal\tome_static\EventSubscriber\EntityPathSubscriber::collectPaths
   * @covers \Drupal\tome_static\EventSubscriber\EntityPathSubscriber::replacePathPlaceholder
   */
  public function testGetPaths() {
    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');
    $base_dir = Settings::get('tome_static_directory');

    DateFormat::create([
      'pattern' => 'D, m/d/Y - H:i',
      'id' => 'medium',
    ])->save();

    $this->assertUnsortedEquals([
      '/',
      '/french',
      '_entity:user:en:0',
    ], $static->getPaths());

    $article = Node::create(['type' => 'article', 'title' => 'My article']);
    $article->addTranslation('fr', ['title' => 'My french article']);
    $article->save();

    $this->assertUnsortedEquals([
      '/',
      '_entity:node:en:1',
      '_entity:node:fr:1',
      '/french',
      '_entity:user:en:0',
    ], $static->getPaths());

    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['tome_static_path_exclude'] = ['_entity:node:fr:1', '/french'];
    new Settings($settings);

    $this->assertUnsortedEquals([
      '/',
      '_entity:node:en:1',
      '_entity:user:en:0',
    ], $static->getPaths());

    $static->requestPath('_entity:node:en:1');
    $this->assertFileExists("$base_dir/node/1/index.html");
    $static->requestPath('_entity:node:fr:1');
    $this->assertFileExists("$base_dir/french/node/1/index.html");

    \Drupal::service('file_system')->delete("$base_dir/node/1/index.html");
    \Drupal::service('file_system')->delete("$base_dir/french/node/1/index.html");
    $article->setUnpublished();
    $article->save();
    \Drupal::entityTypeManager()->getAccessControlHandler('node')->resetCache();
    $static->requestPath('/');
    $static->requestPath('_entity:node:en:1');
    $this->assertFileNotExists("$base_dir/node/1/index.html");
    $static->requestPath('_entity:node:fr:1');
    $this->assertFileExists("$base_dir/french/node/1/index.html");
  }

  /**
   * @covers \Drupal\tome_static\EventSubscriber\LanguagePathSubscriber::collectPaths
   */
  public function testLanguagePathPrefix() {
    $this->enableModules(['tome_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $this->assertUnsortedEquals([
      '/',
      '/tome-test/page',
      '/tome-test/complex-page',
      '/tome-test/pager-page',
      '/tome-test/oembed-page',
      '/french',
      '/french/tome-test/page',
      '/french/tome-test/complex-page',
      '/french/tome-test/pager-page',
      '/french/tome-test/oembed-page',
      '_entity:user:en:0',
    ], $static->getPaths());

    $this->config('language.negotiation')->set('url.prefixes', [
      'en' => 'english',
      'fr' => 'french',
    ])->save();

    $this->assertUnsortedEquals([
      '/',
      '/tome-test/complex-page',
      '/tome-test/oembed-page',
      '/tome-test/page',
      '/tome-test/pager-page',
      '/english',
      '/english/tome-test/complex-page',
      '/english/tome-test/oembed-page',
      '/english/tome-test/page',
      '/english/tome-test/pager-page',
      '/french',
      '/french/tome-test/complex-page',
      '/french/tome-test/oembed-page',
      '/french/tome-test/page',
      '/french/tome-test/pager-page',
      '_entity:user:en:0',
    ], $static->getPaths());
  }

  /**
   * @covers \Drupal\tome_static\EventSubscriber\LanguagePathSubscriber::collectPaths
   */
  public function testDomainNegotiation() {
    $this->config('language.negotiation')
      ->set('url.source', LanguageNegotiationUrl::CONFIG_DOMAIN)
      ->set('url.domains', [
        'en' => 'example.com',
        'fr' => 'example.fr',
      ])
      ->save();

    $article = Node::create(['type' => 'article', 'title' => 'My article']);
    $article->addTranslation('fr', ['title' => 'My french article']);
    $article->save();

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $this->assertUnsortedEquals([
      '/',
      '_entity:node:en:1',
      '_entity:user:en:0',
    ], $static->getPaths());

    $request = Request::create('http://example.fr');
    \Drupal::requestStack()->push($request);

    \Drupal::service('language_manager')->reset();

    $this->assertUnsortedEquals([
      '/',
      '_entity:node:fr:1',
    ], $static->getPaths());
  }

  /**
   * @covers \Drupal\tome_static\StaticGenerator::requestPath
   */
  public function testRequestPath() {
    $this->enableModules(['tome_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $static->requestPath('/tome-test/page');
    $this->assertStringContainsString('Hello, world', file_get_contents(Settings::get('tome_static_directory') . '/tome-test/page/index.html'));
  }

  /**
   * @covers \Drupal\tome_static\StaticGenerator::requestPath
   * @covers \Drupal\tome_static\StaticGenerator::getCssAssets
   * @covers \Drupal\tome_static\StaticGenerator::getHtmlAssets
   * @covers \Drupal\tome_static\StaticGenerator::exportPaths
   */
  public function testComplexHtml() {
    $this->enableModules(['tome_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $invoke_paths = $static->requestPath('/tome-test/complex-page');
    $module_path = drupal_get_path('module', 'tome_test');
    $base_dir = Settings::get('tome_static_directory');

    $this->assertContains("/$module_path/assets/srcset-test1.png", $invoke_paths);
    $this->assertContains("/$module_path/assets/srcset-test2.png", $invoke_paths);
    $this->assertContains("/$module_path/assets/srcset-test3.png", $invoke_paths);
    $this->assertContains("/$module_path/assets/poster-test.png", $invoke_paths);
    $this->assertContains('/random/path', $invoke_paths);
    $this->assertContains('/absolute/path', $invoke_paths);
    $this->assertNotContains('http://www.google.com/external/path', $invoke_paths);
    $this->assertContains('/path/with/destination?bar=baz', $invoke_paths);
    $this->assertContains('/random/iframe', $invoke_paths);
    $this->assertContains("/$module_path/js/complex.js?v=1", $invoke_paths);
    $this->assertContains('/use.svg', $invoke_paths);
    $this->assertContains('/twitter_image.png', $invoke_paths);
    $this->assertContains('/og_image.png', $invoke_paths);
    $this->assertNotContains('data:image/jpeg;base64,x', $invoke_paths);

    $static->exportPaths($invoke_paths);
    $this->assertStringContainsString('Hello, complex world', file_get_contents("$base_dir/tome-test/complex-page/index.html"));
    $this->assertFileExists("$base_dir/$module_path/css/complex.css");
    $this->assertFileExists("$base_dir/$module_path/js/complex.js");
    $this->assertFileExists("$base_dir/$module_path/assets/druplicon-1.png");
    $this->assertFileExists("$base_dir/$module_path/assets/file with space.png");
    $this->assertFileExists("$base_dir/core/misc/druplicon.png");
  }

  /**
   * @covers \Drupal\tome_static\EventSubscriber\PagerPathSubscriber::modifyDestination
   * @covers \Drupal\tome_static\EventSubscriber\PagerPathSubscriber::modifyHtml
   * @covers \Drupal\tome_static\EventSubscriber\PagerPathSubscriber::modifyUrl
   */
  public function testPagers() {
    $this->enableModules(['tome_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $invoke_paths = $static->requestPath('/tome-test/pager-page');
    $this->assertContains('/tome-test/pager-page?page=0', $invoke_paths);
    $this->assertContains('/tome-test/pager-page?page=1', $invoke_paths);
    $this->assertContains('/foobar?page=2', $invoke_paths);
    $base_dir = Settings::get('tome_static_directory');
    $contents = file_get_contents("$base_dir/tome-test/pager-page/index.html");
    $this->assertStringContainsString('href="/tome-test/pager-page"', $contents);
    $this->assertStringContainsString('href="/tome-test/pager-page/page/2"', $contents);
    $this->assertStringContainsString('href="/foobar/page/3"', $contents);
  }

  /**
   * @covers \Drupal\tome_static\EventSubscriber\MediaOembedPathSubscriber::modifyDestination
   * @covers \Drupal\tome_static\EventSubscriber\MediaOembedPathSubscriber::modifyHtml
   * @covers \Drupal\tome_static\EventSubscriber\MediaOembedPathSubscriber::modifyUrl
   */
  public function testOembed() {
    $this->enableModules(['tome_test']);

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    $invoke_paths = $static->requestPath('/tome-test/oembed-page');
    $query = 'url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3Duqmq5FBP_T0&max_width=0&max_height=0&hash=ON_e21QvgL9euuUSW2TP_s1x62ZmFzzZNuIHbsQpXNg';
    $this->assertContains("/media/oembed?$query", $invoke_paths);
    $request_paths = $static->exportPaths($invoke_paths);
    $this->assertContains("/media/oembed?$query", $request_paths);
    $static->requestPath("/media/oembed?$query");

    $base_dir = Settings::get('tome_static_directory');
    $modified_path = "/media/oembed/" . md5(urldecode($query));
    $contents = file_get_contents("$base_dir/tome-test/oembed-page/index.html");
    $this->assertStringContainsString("src='$modified_path'", $contents);
    $this->assertFileExists("$base_dir/$modified_path" . '/index.html');
  }

  /**
   * @covers \Drupal\tome_static\EventSubscriber\EntityPathSubscriber::collectPaths
   */
  public function testExcludedEntityTypes() {
    $this->enableModules(['link', 'block_content']);
    $this->installEntitySchema('block_content');

    /** @var \Drupal\tome_static\StaticGenerator $static */
    $static = \Drupal::service('tome_static.generator');

    BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
    ])->save();

    BlockContent::create([
      'info' => 'block',
      'type' => 'basic',
      'langcode' => 'en',
    ])->save();

    $this->assertNotContains('_entity:block_content:en:1', $static->getPaths());
  }

  /**
   * Asserts that two arrays are equal, ignoring sort order.
   *
   * @param array $array1
   *   The first array to compare.
   * @param array $array2
   *   The second array to compare.
   */
  protected function assertUnsortedEquals(array $array1, array $array2) {
    sort($array1);
    sort($array2);
    $this->assertEquals($array1, $array2);
  }

}
