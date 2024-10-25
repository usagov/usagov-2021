<?php

namespace Drupal\usagov_menus\Plugin\Block;

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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  protected function getMenuTreeItems(
    string $menuID,
    array $crumbs = [],
    ?MenuLinkInterface $active = NULL,
    bool $closeLastTrail = FALSE,
    ?int $maxLevels = 3,
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
      if ($maxLevels > 0 && $depth >= $maxLevels && $children) {
        // Current link has children, so only show
        // grandparent through children.
        $params->setMinDepth($depth - 1);
      }
      elseif ($maxLevels > 0 && $depth >= $maxLevels) {
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
  protected function getParents(MenuLinkInterface $active): array {
    $parentUUID = $active->getParent();
    $crumbs = [$active->getPluginId()];

    while ($parentUUID) {
      array_unshift($crumbs, $parentUUID);
      $parent = $this->menuLinkManager->createInstance($parentUUID);
      $parentUUID = $parent->getParent();
    }

    return $crumbs;
  }

}
