<?php

namespace Drupal\usagov_login;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UserRouteAccess implements ContainerInjectionInterface {

  public function __construct(
    private ImmutableConfig $config,
    private StateInterface $state,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      config: $container->get('config.factory')->get('usagov_login.settings'),
      state: $container->get('state'),
    );
  }

  public function checkAccess(AccountInterface $account): AccessResultForbidden|AccessResultAllowed {
    $loginPath = $this->config->get('sso_login_path');
    $forceLocalForm = $this->state->get('usagov_login_local_form', 0);

    if ($loginPath && !$forceLocalForm) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
