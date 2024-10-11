<?php

namespace Drupal\usagov_menus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\Item;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

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
class SidebarFirstBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const AGENCIES_NID_EN = 629;
  private const AGENCIES_NID_ES = 1696;
  private const STATES_NID_EN = 1885;
  private const STATES_NID_ES = 1870;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LanguageInterface $language,
    protected Request $request,
    protected ResettableStackedRouteMatchInterface $routeMatch,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected MenuLinkTreeInterface $menuTree,
    protected MenuActiveTrailInterface $trail,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

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
        return $this->buildFromParentNodeID($menuID, self::STATES_NID_EN);

      case str_starts_with($path, '/es/estados/'):
        return $this->buildFromParentNodeID($menuID, self::STATES_NID_ES);

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
  private function buildFromParentNodeID(string $menuID, $parentNodeID): array {
    $menu_links = $this->menuLinkManager->loadLinksByRoute( 'entity.node.canonical', ['node' => $parentNodeID], $menuID );
    $active = array_pop($menu_links);
    $crumbs = $this->getParents($active);
    $items = $this->getMenuTreeItems($menuID, $crumbs, $active);
    $leaf = [
      'url' => $this->request->getPathInfo(),
      'title' => $this->routeMatch->getParameter('node')->getTitle(),
    ];
    return $this->renderItems($items, $active, $leaf);
  }

  /**
   * Get the menu items for the path of crumbs passed.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getMenuTreeItems(
    string $menuID,
    array $crumbs = [],
    ?MenuLinkInterface $active = NULL,
    bool $closeLastTrail = FALSE,
  ): array {
    // Get siblings from menu.
    $params = new MenuTreeParameters();
    $params->onlyEnabledLinks();

    if ($crumbs) {
      $params->setActiveTrail($crumbs);
      $depth = count($crumbs);
    }
    else {
      $depth = 1;
    }

    if ($active) {
      $children = $this->menuLinkManager->getChildIds($active->getPluginId());
      $children = array_filter($children, function (string $uuid) {
        // Above, getChildIds returns children regardless of visibility.
        return $this->menuLinkManager->createInstance($uuid)->isEnabled();
      });

      // Check if the expanded menu is 3 or more levels deep and adjust
      // what we show based on if we have children elements to show.
      if ($depth >= 3 && $children) {
        // Current link has children, so only show
        // grandparent through children.
        $params->setMinDepth($depth - 1);
      }
      elseif ($depth >= 3) {
        // No children to show, display the menu starting
        // 2 Levels above us.
        $params->setMinDepth($depth - 2);
      }
    }
    else {
      // There's no active path, just show the top level
      // topic  menu link elements.
      $params->setMaxDepth(1);
    }

    if ($closeLastTrail) {
      // Don't open beyond the last link in $crumb.
      $params->setMaxDepth($depth);
    }

    $tree = $this->menuTree->load($menuID, $params);
    // Remove items not in trail.
    if ($crumbs) {
      $tree = array_filter($tree, function (MenuLinkTreeElement $item) {
        return $item->inActiveTrail;
      });
    }

    // Sort by menu weight and ensure user can access the
    // entities and nodes linked in the menu.
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    return $this->menuTree->build($tree);
  }

  /**
   * Find all the parents for the menu link.
   *
   * @return string[]
   *   Array of menu_link_content UUIDS.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getParents(MenuLinkInterface $active): array {
    $parentUUID = $active->getParent();
    $crumbs = [$active->getPluginId()];
    while ($parentUUID) {
      array_unshift($crumbs, $parentUUID);
      $parent = $this->menuLinkManager->createInstance($parentUUID);
      $parentUUID = $parent->getParent();
    }

    return $crumbs;
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
      $currentURL = $active->getUrlObject()->toString();
      if($leaf){
        $currentURL = $leaf['url'];
      }
      $menutree = reset($this->prepareMenuItemsForTemplate($items['#items'], $currentURL, $leaf));

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
  function prepareMenuItemsForTemplate($items, $currentURL, $leaf): array {
    $menuTree = [];
    foreach ($items as $item) {
      $below = NULL;
      if($item['in_active_trail']) {
        if( $item['below'] ){
          $below = $this->prepareMenuItemsForTemplate($item['below'], $currentURL, $leaf);
        } elseif( $leaf ) {
          // This $item is active with no children. So if a $leaf was provided, then it goes below this $item.
          $below = $this->prepareMenuItemsForTemplate([$leaf], $currentURL, null);
        }
      }
      $url = $item['url'];
      if( !is_string($url) ) {
        $url = $url->toString();
      }
      array_push($menuTree, (object)[
        'title' => $item['title'],
        'url' => $url,
        'active' => $item['in_active_trail'],
        'current' => $currentURL === $url,
        'below' => $below,
      ]);
    }
    return $menuTree;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      language: $container->get('language_manager')->getCurrentLanguage(),
      request: $container->get('request_stack')->getCurrentRequest(),
      routeMatch: $container->get('current_route_match'),
      menuLinkManager: $container->get('plugin.manager.menu.link'),
      menuTree: $container->get('menu.link_tree'),
      trail: $container->get('menu.active_trail'),
    );
  }

}
