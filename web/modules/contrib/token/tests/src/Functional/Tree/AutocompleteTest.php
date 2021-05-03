<?php

namespace Drupal\Tests\token\Functional\Tree;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\token\Functional\TokenTestBase;

/**
 * Test token autocomplete.
 *
 * @group token
 */
class AutocompleteTest extends TokenTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node'];

  /**
   * Tests autocomplete for node tokens.
   */
  public function testNodeAutocomplete() {
    $url_prefix = "token/autocomplete/node/";

    $url = $url_prefix . 'Title of [nod';
    $response = Json::decode($this->drupalGet($url, [
      'query' => [
        '_format' => 'json',
      ],
    ]));

    $this->assertArrayHasKey('[node:nid]', $response);
    $this->assertArrayHasKey('[node:author]', $response);
    $this->assertArrayHasKey('[node:url]', $response);
    $this->assertArrayHasKey('[node:url:', $response);

    $url = $url_prefix . 'Title of [node:url:';
    $response = Json::decode($this->drupalGet($url, [
      'query' => [
        '_format' => 'json',
      ],
    ]));

    $this->assertArrayHasKey('[node:url:path]', $response);
    $this->assertArrayHasKey('[node:url:absolute]', $response);
  }

  /**
   * Tests autocomplete for user tokens.
   */
  public function testUserAutocomplete() {
    $url_prefix = "token/autocomplete/user/";

    $url = $url_prefix . 'Name of the [us';
    $response = Json::decode($this->drupalGet($url, [
      'query' => [
        '_format' => 'json',
      ],
    ]));

    $this->assertArrayHasKey('[user:uid]', $response);
    $this->assertArrayHasKey('[user:original]', $response);
    $this->assertArrayHasKey('[user:url]', $response);
    $this->assertArrayHasKey('[user:url:', $response);

    $url = $url_prefix . 'Title of [user:original:';
    $response = Json::decode($this->drupalGet($url, [
      'query' => [
        '_format' => 'json',
      ],
    ]));

    $this->assertArrayHasKey('[user:original:uid]', $response);
  }
}
