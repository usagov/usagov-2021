<?php

namespace Drupal\Tests\webform\FunctionalJavascript;

use Drupal\Core\Test\AssertMailTrait;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\webform\Traits\WebformBrowserTestTrait;
use Drupal\Tests\webform\Traits\WebformAssertLegacyTrait;
use Drupal\Tests\webform\Traits\WebformWebDriverTestTrait;

/**
 * Defines an abstract test base for webform JavaScript tests.
 */
abstract class WebformWebDriverTestBase extends WebDriverTestBase {

  use AssertMailTrait;
  use WebformBrowserTestTrait;
  use WebformAssertLegacyTrait;
  use WebformWebDriverTestTrait;

  /**
   * Set default theme to classy.
   *
   * @var string
   * @see https://www.drupal.org/node/3083055
   */
  protected $defaultTheme = 'classy';

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['webform'];

  /**
   * Webforms to load.
   *
   * @var array
   */
  protected static $testWebforms = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->loadWebforms(static::$testWebforms);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    $this->purgeSubmissions();
    parent::tearDown();
  }

}
