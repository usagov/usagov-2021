<?php

namespace Drupal\usagov_menus\Menu;

use Drupal\Core\Database\Connection;
use Drupal\Core\Menu\MenuLinkManager;

/**
 * Menu link manager that can order route lookups by mlid.
 *
 * node_menus can leave several links pointing at the same node. The one with
 * the lowest mlid is the original; the rest are "false children". Callers that
 * need the original pass ['mlid' => 'ASC'] as the fourth argument to
 * loadLinksByRoute(), as views_menu_children_filter does via
 * patches/drupal/correctMenuChildren_d10.patch.
 */
class UsagovMenuLinkManager extends MenuLinkManager {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * Sets the database connection.
   */
  public function setDatabase(Connection $database): void {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   *
   * @param string $route_name
   *   The route name.
   * @param array<string, mixed> $route_parameters
   *   The route parameters.
   * @param string|null $menu_name
   *   (optional) Restrict the lookup to this menu.
   * @param array<string, string>|null $sort_order
   *   (optional) ['mlid' => 'ASC'|'DESC'] orders the links by menu_tree.mlid.
   *   Anything else keeps core's depth, weight, ID order.
   *
   * @return \Drupal\Core\Menu\MenuLinkInterface[]
   *   The menu links, keyed by plugin ID.
   */
  public function loadLinksByRoute($route_name, array $route_parameters = [], $menu_name = NULL, $sort_order = NULL) {
    $links = parent::loadLinksByRoute($route_name, $route_parameters, $menu_name);
    if (count($links) < 2 || !isset($sort_order['mlid'])) {
      return $links;
    }

    $direction = strtoupper($sort_order['mlid']) === 'DESC' ? 'DESC' : 'ASC';
    $ids = $this->database->select('menu_tree', 'mt')
      ->fields('mt', ['id'])
      ->condition('id', array_keys($links), 'IN')
      ->orderBy('mlid', $direction)
      ->execute()
      ->fetchCol();

    $sorted = [];
    foreach ($ids as $id) {
      $sorted[$id] = $links[$id];
    }
    // Keep any link the query did not return, in core's order.
    return $sorted + $links;
  }

}
