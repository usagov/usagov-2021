<?php

namespace Drupal\tome_sync;

use Drupal\Core\Session\UserSession;

/**
 * Shared methods for handling account switching.
 */
trait AccountSwitcherTrait {

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Switches the current user to the admin.
   */
  protected function switchToAdmin() {
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));
  }

  /**
   * Switches the current user back.
   */
  protected function switchBack() {
    $this->accountSwitcher->switchBack();
  }

}
