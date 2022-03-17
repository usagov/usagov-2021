<?php

namespace Drupal\tome_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\HtmlResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Contains test pages for the tome module.
 *
 * @codingStandardsIgnoreFile
 */
class TestController extends ControllerBase {

  /**
   * Outputs a test page.
   */
  public function testPage() {
    $response = new HtmlResponse();
    $response->setContent('Hello, world');
    return $response;
  }

  /**
   * Outputs a more complex test page.
   */
  public function testComplexPage(Request $request) {
    $module_path = drupal_get_path('module', 'tome_test');
    return [
      '#children' => "<p>Hello, complex world</p>
<img src=\"/$module_path/assets/druplicon-1.png\">
<img src=\"/$module_path/assets/file%20with%20space.png\">
<img srcset=\"/$module_path/assets/srcset-test1.png 320w, /$module_path/assets/srcset-test2.png 420w\">
<source srcset=\"/$module_path/assets/srcset-test3.png\">
<video poster=\"/$module_path/assets/poster-test.png\">
<a href='/random/path'></a>
<a href='http://www.google.com/external/path'></a>
<a href='" . $request->getSchemeAndHttpHost() . "/absolute/path'></a>
<a href='/path/with/destination?destination=foo&bar=baz'></a>
<iframe src='/random/iframe'></iframe>
<img src=\"data:image/jpeg;base64,x\" />
<meta name=\"twitter:image\" content=\"/twitter_image.png\" />
<meta property=\"og:image\" content=\"/og_image.png\" />
<svg><use xlink:href='/use.svg'></use></svg>",
      '#attached' => [
        'library' => [
          'tome_test/complex_page',
        ],
      ],
    ];
  }

  /**
   * Outputs a more page with pagers.
   */
  public function testPageWithPagers() {
    return [
      '#markup' => '<a href="?page=0">Page 1</a><a href="?page=1">Page 2</a><a href="/foobar?page=2">Page 3</a>',
      '#allowed_tags' => ['a'],
    ];
  }

  /**
   * Outputs a more page with oembed.
   */
  public function testPageWithOembed() {
    return [
      '#markup' => '<iframe src=\'/media/oembed?url=https%3A//www.youtube.com/watch%3Fv%3Duqmq5FBP_T0&amp;max_width=0&amp;max_height=0&amp;hash=ON_e21QvgL9euuUSW2TP_s1x62ZmFzzZNuIHbsQpXNg\'></iframe>',
      '#allowed_tags' => ['iframe'],
    ];
  }

  /**
   * Outputs an embed page.
   */
  public function fakeOembed() {
    return [
      '#markup' => 'Hello world!',
    ];
  }

}
