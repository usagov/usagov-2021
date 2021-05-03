<?php

namespace Drupal\Tests\scheduler\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\scheduler\Traits\SchedulerSetupTrait;

/**
 * Base class to provide common browser test setup.
 */
abstract class SchedulerBrowserTestBase extends BrowserTestBase {

  use SchedulerSetupTrait;

  /**
   * The standard modules to load for all browser tests.
   *
   * Additional modules can be specified in the tests that need them.
   *
   * @var array
   */
  protected static $modules = ['scheduler', 'dblog'];

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

    $this->schedulerSetUp();

  }

}
