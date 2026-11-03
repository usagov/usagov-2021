<?php

namespace Drupal\usagov_menus\Menu;

use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Active trail that picks the original node_menus link for a route.
 *
 * Follows Drupal 11's getActiveLink(): links for the current route, then
 * <front> links on the front page, and the first enabled one wins. Both
 * lookups are ordered by mlid, so the original link beats "false children".
 */
class UsagovMenuActiveTrail extends MenuActiveTrail {

  /**
   * The path matcher.
   *
   * Named apart from core's $pathMatcher, which only exists from Drupal 11.2.
   */
  protected PathMatcherInterface $usagovPathMatcher;

  /**
   * Sets the path matcher.
   */
  public function setUsagovPathMatcher(PathMatcherInterface $path_matcher): void {
    $this->usagovPathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveLink($menu_name = NULL) {
    $links = [];

    $route_name = $this->routeMatch->getRouteName();
    // The route name is NULL on a default (not custom) 403 page.
    if ($route_name) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      $links = $this->loadLinksByMlid($route_name, $route_parameters, $menu_name);
    }

    // <front> is an alias for the front page, so its links match too.
    if ($this->usagovPathMatcher->isFrontPage()) {
      $links = array_merge($links, $this->loadLinksByMlid('<front>', [], $menu_name));
    }

    foreach ($links as $link) {
      if ($link->isEnabled()) {
        return $link;
      }
    }

    return NULL;
  }

  /**
   * Loads the links for a route, ordered by mlid when the manager supports it.
   *
   * @param string $route_name
   *   The route name.
   * @param array<string, mixed> $route_parameters
   *   The route parameters.
   * @param string|null $menu_name
   *   (optional) Restrict the lookup to this menu.
   *
   * @return \Drupal\Core\Menu\MenuLinkInterface[]
   *   The menu links, keyed by plugin ID.
   */
  protected function loadLinksByMlid(string $route_name, array $route_parameters, ?string $menu_name): array {
    if ($this->menuLinkManager instanceof UsagovMenuLinkManager) {
      return $this->menuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name, ['mlid' => 'ASC']);
    }
    return $this->menuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name);
  }

}
