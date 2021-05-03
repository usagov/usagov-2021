<?php

namespace Drupal\Tests\scheduler\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;

/**
 * Base class for Scheduler javascript tests.
 *
 * @group scheduler
 */
abstract class SchedulerJavascriptTestBase extends WebDriverTestBase {

  use SchedulerSetupTrait;

  /**
   * The standard modules to load for all javascript tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = ['scheduler'];

  /**
   * The profile to install as a basis for testing.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Call the common set-up function defined in the trait.
    $this->schedulerSetUp();
  }

  /**
   * Flush cache.
   */
  protected function flushCache() {
    $module_handler = \Drupal::moduleHandler();
    $module_handler->invokeAll('cache_flush');
  }

}
