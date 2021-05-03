<?php

namespace Drupal\Tests\metatag\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use Drupal\Tests\token\Functional\TokenTestTrait;

/**
 * Verify that metatag token generation is working.
 *
 * @group metatag
 */
class MetatagTokenTest extends BrowserTestBase {

  use TokenTestTrait;
  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'field_ui',
    'user',
    'token',
    'token_module_test',
    'metatag',
    'metatag_open_graph',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('system_breadcrumb_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->drupalLogin($this->rootUser);
    $this->fieldUIAddNewField('/admin/config/people/accounts', 'metatags', 'Metatags', 'metatag');
  }

  /**
   * Test current-page metatag token generation.
   */
  public function testMetatagCurrentPageTokens() {
    $user = $this->createUser([]);
    $this->drupalGet($user->toUrl('edit-form'));
    $this->submitForm([
      'field_metatags[0][basic][abstract]' => 'My abstract',
      'field_metatags[0][open_graph][og_title]' => 'My OG Title',
      'field_metatags[0][open_graph][og_image]' => 'Image 1,Image 2',
    ], 'Save');

    $tokens = [
      // Test globally configured metatags.
      '[current-page:metatag:title]' => sprintf('%s | %s', $user->getAccountName(), $this->config('system.site')
        ->get('name')),
      '[current-page:metatag:description]' => $this->config('system.site')
        ->get('name'),
      '[current-page:metatag:canonical-url]' => $user->toUrl('canonical', ['absolute' => TRUE])
        ->toString(),
      // Test entity overridden metatags.
      '[current-page:metatag:abstract]' => 'My abstract',
      // Test metatags provided by a submodule.
      '[current-page:metatag:og-title]' => 'My OG Title',
      // Test metatags that can contain multiple values.
      '[current-page:metatag:og_image]' => 'Image 1,Image 2',
      '[current-page:metatag:og_image:0]' => 'Image 1',
      '[current-page:metatag:og_image:1]' => 'Image 2',
    ];
    $this->assertPageTokens($user->toUrl(), $tokens);
  }

  /**
   * Test entity token generation.
   */
  public function testMetatagEntityTokens() {
    $user = $this->createUser();
    $this->drupalGet($user->toUrl('edit-form'));
    $this->submitForm([
      'field_metatags[0][basic][abstract]' => 'My abstract',
      'field_metatags[0][open_graph][og_title]' => 'My OG Title',
      'field_metatags[0][open_graph][og_image]' => 'Image 1,Image 2',
    ], 'Save');

    $tokens = [
      // Test globally configured metatags.
      '[user:field_metatags:title]' => sprintf('%s | %s', $user->getAccountName(), $this->config('system.site')->get('name')),
      '[user:field_metatags:description]' => $this->config('system.site')->get('name'),
      '[user:field_metatags:canonical-url]' => $user->toUrl('canonical', ['absolute' => TRUE])->toString(),
      // Test entity overridden metatags.
      '[user:field_metatags:abstract]' => 'My abstract',
      // Test metatags provided by a submodule.
      '[user:field_metatags:og-title]' => 'My OG Title',
      // Test metatags that can contain multiple values.
      '[user:field_metatags:og_image]' => 'Image 1,Image 2',
      '[user:field_metatags:og_image:0]' => 'Image 1',
      '[user:field_metatags:og_image:1]' => 'Image 2',
    ];

    $this->assertPageTokens($user->toUrl(), $tokens, ['user' => $user]);
  }

}
