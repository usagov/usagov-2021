<?php

namespace Drupal\usagov_login;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class UserRouteAccess {

  public function checkAccess(AccountInterface $account) {
    $config = \Drupal::config('usagov_login.settings');
    $loginPath = $config->get('sso_login_path');

    $forceLocalForm = \Drupal::state()->get('usagov_login_local_form', 0);

    if ($loginPath && !$forceLocalForm) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
