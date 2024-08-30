<?php

namespace Drupal\usagov_sidebar_menu\Plugin\Block;

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
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a USAGOV Custom Sidebar Menu Block.
 */
#[Block(
  id: "usagov_sidebarfirst_block",
  admin_label: new TranslatableMarkup("Left Menu Sidebar Block"),
  category: new TranslatableMarkup("USAgov"),
)]
class SidebarFirstBlock extends BlockBase implements ContainerFactoryPluginInterface {

  private const AGENCIES_NID_ES = 1696;
  private const AGENCIES_NID_EN = 629;

  private const STATES_NID_ES = 1870;
  private const STATES_NID_EN = 676;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LanguageInterface $language,
    protected Request $request,
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
        $navAriaLabel = 'Secundaria';
        break;

      case 'en':
      default:
        $menuID = 'left-menu-english';
        $navAriaLabel = 'Secondary';
        break;
    }

    switch (TRUE) {
      case str_starts_with($this->request->getPathInfo(), '/agencies/'):
      case str_starts_with($this->request->getPathInfo(), '/es/agencias/'):
        // These items aren't part of a menu.
        return $this->buildAgencySidebar($menuID, $navAriaLabel);

      case str_starts_with($this->request->getPathInfo(), '/states/'):
      case str_starts_with($this->request->getPathInfo(), '/es/estados/'):
        // These items aren't part of a menu.
        return $this->buildStatesSidebar($menuID, $navAriaLabel);

      default:
        return $this->buildFromMenu($menuID, $navAriaLabel);
    }
  }

  /**
   * Builds the left navigation based on the current page's menu item.
   */
  private function buildFromMenu(string $menuID, string $navAriaLabel): array {
    $crumbs = $this->trail->getActiveTrailIds($menuID);
    $active = $this->trail->getActiveLink($menuID);
    if (!$active) {
      // We're not in the menu.
      return [];
    }

    $items = $this->getMenuTreeItems($crumbs, $menuID);
    return $this->renderItems($items, $navAriaLabel, $active);
  }

  /**
   * Builds the left navigation for an agency page.
   */
  private function buildAgencySidebar(string $menuID, string $navAriaLabel): array {
    // Get our parent.
    $parentNodeID = match ($this->language->getId()) {
      'es' => self::AGENCIES_NID_ES,
      default => self::AGENCIES_NID_EN,
    };

    // @todo need to display the current link under the A-Z parent
    $menu_links = $this->menuLinkManager->loadLinksByRoute(
      'entity.node.canonical', ['node' => $parentNodeID],
      $menuID
    );

    $active = array_pop($menu_links);
    if (!$active) {
      throw new \RuntimeException("Can't find active link");
    }

    $crumbs = $this->getParents($active);
    $items = $this->getMenuTreeItems($crumbs, $menuID);

    return $this->renderItems($items, $navAriaLabel, $active);
  }

  /**
   * Display the left nav for state pages.
   */
  private function buildStatesSidebar(string $menuID, string $navAriaLabel): array {
    // @todo need to display the current link under the State parent
    $parentNodeID = match ($this->language->getId()) {
      'es' => self::STATES_NID_ES,
      default => self::STATES_NID_EN,
    };
    $menu_links = $this->menuLinkManager->loadLinksByRoute(
      'entity.node.canonical', ['node' => $parentNodeID],
      $menuID
    );

    $active = array_pop($menu_links);
    $crumbs = $this->getParents($active);

    $items = $this->getMenuTreeItems($crumbs, $menuID);

    return $this->renderItems($items, $navAriaLabel, $active);
  }

  /**
   * Get the menu items for the path of crumbs passed.
   *
   * @return array
   *   A renderable array.
   */
  public function getMenuTreeItems(array $crumbs, string $menuID): array {
    // @todo Tome caches the menu and active trail ids when path count > 1.
    $this->trail->clear();

    // Get siblings from menu.
    $params = new MenuTreeParameters();
    $params->setActiveTrail($crumbs);

    if (($depth = count($crumbs)) > 3) {
      $params->setMinDepth($depth - 3);
    }

    $tree = $this->menuTree->load($menuID, $params);
    // Remove items not in trail.
    $tree = array_filter($tree, function (MenuLinkTreeElement $item) {
      return $item->inActiveTrail;
    });

    // Sort by menu weight.
    $tree = $this->menuTree->transform($tree, [
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
    string $navAriaLabel,
    MenuLinkInterface $active,
  ): array {
    if (!empty($items['#items'])) {
      return [
        '#theme' => 'usagov_menu_sidebar',
        '#start_item' => $items['#items'][array_key_first($items['#items'])],
        '#depth' => 0,
        '#nav_aria_label' => $navAriaLabel,
        '#current' => [
          'url' => $active->getUrlObject()->toString(),
          'title' => $active->getTitle(),
        ],
        // @todo fix below
        '#page_type_is' => 'Basic Page',
        '#is_spanish_menu' => $this->language->getId() === 'es',
      ];
    }

    trigger_error('No left nav menu items found', E_USER_WARNING);
    return [];
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
      menuLinkManager: $container->get('plugin.manager.menu.link'),
      menuTree: $container->get('menu.link_tree'),
      trail: $container->get('menu.active_trail'),
    );
  }

}
