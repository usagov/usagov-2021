<?php

namespace Drupal\scheduler\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Defines a theme negotiator for the Scheduler routes.
 */
class SchedulerThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    // Use the Scheduler theme negotiator for the user 'scheduled' tab.
    $applies = ($route_match->getRouteName() == 'view.scheduler_scheduled_content.user_page');
    return $applies;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    // Return the admin theme.
    $config = \Drupal::service('config.factory')->getEditable('system.theme');
    $admin_theme = $config->get('admin');
    return $admin_theme;
  }

}
