<?php

namespace Drupal\usagov_menus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a Custom Sidebar Navigation block.
 *
 * Provides a USAGOV Custom Sidebar Menu Block. Replaces customizations of the
 * system provided menu that were in twig previously.
 *
 * Depends on usagov_twig_vars for getting the pagetype variable.
 *
 * A short summary of the behavior of the sidebar nav:
 *  - It can show up to 3 levels
 *  - The levels shown depend on the existence of parents and children of the
 *    current page
 *  - It will attempt to show parent, current page (plus siblings), and children
 *  - If there are no children, it will try to show grandparent, parent, and
 *    current page (plus siblings)
 *  - Agency and State nodes have custom behavior to show 3 levels plus a link
 *    to the current page.
 */
#[Block(
  id: "usagov_sidebarfirst_block",
  admin_label: new TranslatableMarkup("Left Menu Sidebar Block"),
  category: new TranslatableMarkup("USAgov"),
)]
class SidebarFirstBlock extends AbstractMenuBlock {

  private const AGENCIES_NID_EN = 629;
  private const AGENCIES_NID_ES = 1696;
  private const STATES_NID_EN = 1885;
  private const STATES_NID_ES = 1870;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function build(): array {
    $menuID = match ($this->language->getId()) {
      'es' => 'left-menu-spanish',
      default => 'left-menu-english',
    };
    $path = $this->request->getPathInfo();

    switch (TRUE) {
      case str_starts_with($path, '/agencies/'):
        return $this->buildFromParentNodeID($menuID, self::AGENCIES_NID_EN);

      case str_starts_with($path, '/es/agencias/'):
        return $this->buildFromParentNodeID($menuID, self::AGENCIES_NID_ES);

      case str_starts_with($path, '/states/'):
        return $this->buildFromParentNodeID($menuID, self::STATES_NID_EN, closeLastTrail: TRUE);

      case str_starts_with($path, '/es/estados/'):
        return $this->buildFromParentNodeID($menuID, self::STATES_NID_ES, closeLastTrail: TRUE);

      default:
        return $this->buildFromMenu($menuID);
    }
  }

  /**
   * Builds the left navigation based on the current page's menu item.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function buildFromMenu(string $menuID): array {
    if ($active = $this->trail->getActiveLink($menuID)) {
      $crumbs = $this->menuLinkManager->getParentIds($active->getPluginId());
      $items = $this->getMenuTreeItems($menuID, $crumbs, $active);
      return $this->renderItems($items, $active);
    }

    // We're not in the menu.
    // Display first level of this menu.
    $items = $this->getMenuTreeItems($menuID);
    return $this->renderItems($items);
  }

  /**
   * Builds the left navigation for an agency or state page.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function buildFromParentNodeId(string $menuID, $parentNodeID, bool $closeLastTrail = FALSE): array {
    $menu_links = $this->menuLinkManager->loadLinksByRoute('entity.node.canonical', ['node' => $parentNodeID], $menuID);
    $active = array_pop($menu_links);
    $crumbs = $this->getParents($active);
    $items = $this->getMenuTreeItems($menuID, $crumbs, $active, $closeLastTrail);
    $leaf = [
      'url' => $this->request->getPathInfo(),
      'title' => $this->routeMatch->getParameter('node')->getTitle(),
    ];
    return $this->renderItems($items, $active, $leaf);
  }

  /**
   * Returns the render array to theme the navigation lists.
   */
  private function renderItems(
    array $items,
    ?MenuLinkInterface $active = NULL,
    array $leaf = [],
  ): array {

    if (!empty($items['#items'])) {
      $currentURL = $active?->getUrlObject()->toString();
      if ($leaf) {
        $currentURL = $leaf['url'];
      }

      $menutree = $this->prepareMenuItemsForTemplate($items['#items'], $currentURL, $leaf);
      $menutree = reset($menutree);

      $theme = [
        '#theme' => 'usagov_menu_sidebar',
        '#menutree' => $menutree,
        '#lang' => $this->language->getId(),
      ];

      // Ensure drupal knows this block should be cached per path.
      $theme['#cache'] = [
        'contexts' => ['url.path', 'url.query_args'],
      ];
      return $theme;
    }

    return [];
  }

  /**
   * prepareMenuItemsForTemplate() takes a tree of menu items, the current page's URL,
   * and an optional leaf to supply current page values when the current page is not in this menu.
   *
   * Returns a new tree containing only the items and values needed for the sidebar twig template.
   */
  private function prepareMenuItemsForTemplate($items, $currentURL, $leaf): array {
    $menuTree = [];
    foreach ($items as $item) {
      $below = NULL;
      $in_active_trail = $item['in_active_trail'] ?? FALSE;
      if ($in_active_trail) {
        if ($item['below']) {
          $below = $this->prepareMenuItemsForTemplate($item['below'], $currentURL, $leaf);
        }
        elseif ($leaf) {
          // This $item is active with no children. So if a $leaf was provided, then it goes below this $item.
          $below = $this->prepareMenuItemsForTemplate([$leaf], $currentURL, NULL);
        }
      }
      $url = $item['url'];
      if (!is_string($url)) {
        $url = $url->toString();
      }
      array_push($menuTree, (object) [
        'title' => $item['title'],
        'url' => $url,
        'active' => $in_active_trail,
        'current' => $currentURL === $url,
        'below' => $below,
      ]);
    }
    return $menuTree;
  }

}
