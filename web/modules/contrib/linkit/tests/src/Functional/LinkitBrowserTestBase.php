<?php

namespace Drupal\Tests\linkit\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides a base class for Linkit functional tests.
 */
abstract class LinkitBrowserTestBase extends BrowserTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['linkit', 'linkit_test', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user without the 'administer linkit profiles' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->placeBlock('page_title_block');
    $this->placeBlock('local_tasks_block');
    $this->placeBlock('local_actions_block');
    $this->placeBlock('system_messages_block');

    $this->adminUser = $this->drupalCreateUser(['administer linkit profiles']);
    $this->webUser = $this->drupalCreateUser();
  }

}
