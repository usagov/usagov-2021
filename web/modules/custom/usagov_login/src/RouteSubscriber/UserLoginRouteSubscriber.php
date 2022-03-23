<?php

namespace Drupal\usagov_login\RouteSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class UserLoginRouteSubscriber extends RouteSubscriberBase {

  /**
   * @inheritDoc
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $route->setRequirement('_custom_access', 'Drupal\usagov_login\UserRouteAccess::checkAccess');
    }
  }

}
