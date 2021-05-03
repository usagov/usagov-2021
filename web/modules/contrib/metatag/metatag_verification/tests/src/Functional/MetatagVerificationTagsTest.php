<?php

namespace Drupal\Tests\metatag_verification\Functional;

use Drupal\Tests\metatag\Functional\MetatagTagsTestBase;

/**
 * Tests that each of the Metatag Verification tags work correctly.
 *
 * @group metatag
 */
class MetatagVerificationTagsTest extends MetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'metatag_verification',
  ];

  /**
   * {@inheritdoc}
   */
  protected $tags = [
    'baidu',
    'bing',
    'facebook_domain_verification',
    'google',
    'norton_safe_web',
    'pinterest',
    'pocket',
    'yandex',
    'zoom_domain_verification',
  ];

  /**
   * Each of these meta tags has a different tag name vs its internal name.
   */
  protected function getTestTagName($tag_name) {
    if ($tag_name == 'baidu') {
      $tag_name = 'baidu-site-verification';
    }
    elseif ($tag_name == 'bing') {
      $tag_name = 'msvalidate.01';
    }
    elseif ($tag_name == 'facebook_domain_verification') {
      $tag_name = 'facebook-domain-verification';
    }
    elseif ($tag_name == 'google') {
      $tag_name = 'google-site-verification';
    }
    elseif ($tag_name == 'norton_safe_web') {
      $tag_name = 'norton-safeweb-site-verification';
    }
    elseif ($tag_name == 'pinterest') {
      $tag_name = 'p:domain_verify';
    }
    elseif ($tag_name == 'pocket') {
      $tag_name = 'pocket-site-verification';
    }
    elseif ($tag_name == 'yandex') {
      $tag_name = 'yandex-verification';
    }
    elseif ($tag_name == 'zoom_domain_verification') {
      $tag_name = 'zoom-domain-verification';
    }

    return $tag_name;
  }

}
