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
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\simplify_menu\MenuItems;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Block(
  id: "usagov_mobile_menu_block",
  admin_label: new TranslatableMarkup("Mobile Menu Block"),
  category: new TranslatableMarkup("USAgov"),
)]
class MobileMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private array $translations;

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
   */
  public function build(): array {
    switch ($this->language->getId()) {
      case 'es':
        $menuID = 'left-menu-spanish';
        $this->translations = [
          'home' =>  'Página principal',
          'home_URL' =>  '/es',
          'close' =>  'Cerrar',
          'search' =>  'Buscar',
          'search_placeholder' =>  'Busque en este sitio...',
          'search_affiliate' =>  'usagov_es_internal',
          'all_topics' =>  'Todos los temas y servicios',
          'phone_URL' =>  '/es/llamenos',
          'form_id' => 'usagov_es_internal-mobile',
        ];
        break;
      case 'en':
      default:
        $menuID = 'left-menu-english';
        $this->translations = [
          'home' =>  'Home',
          'home_URL' =>  '/',
          'close' =>  'Close',
          'search' =>  'Search',
          'search_placeholder' =>  'Search all government',
          'search_affiliate' =>  'usagov_all_gov',
          'all_topics' =>  'All topics and services',
          'phone_URL' =>  '/phone',
          'form_id' => 'usagov_all_gov-mobile'
        ];
        break;
    }

    if ($active = $this->trail->getActiveLink($menuID)) {
      $crumbs = $this->menuLinkManager->getParentIds($active->getPluginId());
      $items = $this->getMenuTreeItems($menuID, $crumbs, $active);
      $twigVars = $this->prepareMenuItemsForTemplate($items, $active);
      return $this->renderItems($items, $active, $twigVars);

    }

    // We're not in the menu.
    // Display first level of this menu.
    $items = $this->getMenuTreeItems($menuID);

    $twigVars = [
      '#active_trail' => [], // tells twig to render submenu
      '#found_active_item' => FALSE,
      '#active_item_has_children' => TRUE,
      '#siblings_of_active_item' => [],
      '#submenu' => $items['#items'],
    ];;
    return $this->renderItems($items, $twigVars);


    // The active key isn't correctly set if there are query params, while
    // active_path key is set for some paths. The template depends on this
    // key being set correctly to show siblins.
    switch (TRUE) {
      case ($this->request->getPathInfo() === '/agency-index'):
        $items = $this->fixActiveAgencyItem($items, '/agency-index');
        break;
      case str_starts_with($this->request->getPathInfo(), '/es/indice-agencias'):
        $items = $this->fixActiveAgencyItem($items, '/es/indice-agencias');
        break;
    }

    $node = $this->routeMatch->getParameter('node');
    return $this->renderItems($items, $node);
  }

  private function fixActiveAgencyItem(array $items, string $path): array
  {
    $activeParent = array_filter(
      $items['menu_tree'],
      fn($item) => $item['active_trail'] === TRUE
    );

    $activeKey = array_key_first($activeParent);
    array_walk(
      $items['menu_tree'][$activeKey]['submenu'],
      function(&$item) use ($path) {
        if ($item['url'] === $path) {
          $item['active'] = TRUE;
        }
      }
    );

    return $items;
  }
  /**
   * Returns the render array to theme the navigation lists.
   */
  private function renderItems(
    array $items,
    array $twigVars
  ): array {

    $node = $this->routeMatch->getParameter('node');
    return array_merge(
      [
        '#theme' => 'usagov_menu_mobile',
        '#main_nav_items' => $items,
        '#node' => $node,
        '#translations' => $this->translations,

        // Ensure drupal knows this block should be cached per path.
        '#cache' => [
          'contexts' => ['url.path', 'url.query_args'],
        ]
      ],
      $twigVars
    );
  }

  /**
   * @param array $submenu
   *
   * @return array
   */
  private function prepareMenuItemsForTemplate(array $submenu, MenuLinkInterface $active): array {
    $active_trail = [];
    $found_active_item = FALSE;
    $active_item_has_children = FALSE;
    $siblings_of_active_item = NULL;

    $currentURL = $active->getUrlObject()->toString();

    // Create an array of the active trail items from each level of the
    // menu (up to the active item)
    $submenu = $submenu['#items'];
    while ($submenu && !$found_active_item) {
      $menuItem = array_filter($submenu, fn($item) => $item['in_active_trail'] === TRUE);
      $key = array_key_first($menuItem);
      $menuItem = $menuItem[$key];

      if (!$menuItem) {
        // No active link in the menu? We should bail.
        break;
      }

      // we're done when we find the current page
      if ($menuItem['url']->toString() === $currentURL) {
        $menuItem['active'] = TRUE;
        $found_active_item = TRUE;
        if (!empty($menuItem['below'])) {
          $active_item_has_children = TRUE;
          $submenu = $menuItem['below'];
        }
        else {
          $submenu[$key]['active'] = TRUE;
          $siblings_of_active_item = $submenu;
          $submenu = [];
        }
      } else {
        $submenu = $menuItem['below'] ?: [];
      }

      // add to active trail
      $active_trail[] = $menuItem;
    }

    return [
      '#active_trail' => $active_trail,
      '#found_active_item' => $found_active_item,
      '#active_item_has_children' => $active_item_has_children,
      '#siblings_of_active_item' => $siblings_of_active_item,
      '#submenu' => $submenu,
    ];
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
}
