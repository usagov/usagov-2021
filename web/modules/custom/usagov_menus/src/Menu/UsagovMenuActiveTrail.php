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
 *
 * Unlike core, when no link is enabled the route's first link by mlid still
 * wins. Some pages (error and thank-you pages, for example) are kept out of
 * the menu with a disabled link but still need their section's breadcrumb and
 * sidebar.
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
    $route_links = [];
    $front_links = [];

    $route_name = $this->routeMatch->getRouteName();
    // The route name is NULL on a default (not custom) 403 page.
    if ($route_name) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      $route_links = $this->loadLinksByMlid($route_name, $route_parameters, $menu_name);
    }

    // <front> is an alias for the front page, so its links match too.
    if ($this->usagovPathMatcher->isFrontPage()) {
      $front_links = $this->loadLinksByMlid('<front>', [], $menu_name);
    }

    foreach (array_merge($route_links, $front_links) as $link) {
      if ($link->isEnabled()) {
        return $link;
      }
    }

    // Only the route's own links fall back to a disabled one: a disabled
    // <front> link (such as a menu's hidden "Home") must not become active.
    return $route_links ? reset($route_links) : NULL;
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
