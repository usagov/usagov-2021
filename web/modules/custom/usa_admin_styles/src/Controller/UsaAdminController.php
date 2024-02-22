<?php

namespace Drupal\usa_admin_styles\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\system\SystemManager;

/**
* Provides a route response for the "USA tools" page in the admin area
*/
class UsaAdminController extends ControllerBase {

  public function __construct(MenuLinkTreeInterface $menu_link_tree, SystemManager $system_manager) {
    $this->menuLinkTree = $menu_link_tree;
    $this->systemManager = $system_manager;
  }

  // Returns a page with some instructions and the same list of links as are in the menu.
  public function mainPage() {

    // Loosely based on Drupal\system\Controller\SystemController::overview.
    // That function assumes links will have blocks associated with them, which is not the case
    // when we have provided a URL-based link instead of a route.
    $link_id = 'usa_admin_styles.main';
    $parameters = new MenuTreeParameters();
    $parameters->setRoot($link_id)->excludeRoot()->setTopLevelOnly()->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load(NULL, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $tree_access_cacheability = new CacheableMetadata();
    $blocks = [];
    foreach ($tree as $key => $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      $link = $element->link;
      $block['title'] = $link->getTitle();
      $block['description'] = $link->getDescription();
      $block['content'] = [
        '#theme' => 'admin_block_content',
        '#content' => $this->systemManager->getAdminBlock($link),
      ];

      // If this is a link to a URL (for example, the URL of a view) rather than
      // to a controller specified by a route, getAdminBlock won't have returned anything.
      // Check for a valid URL and its access, and construct the relevant content instead.
      if (empty($block['content']['#content'])) {
        $url = $link->getUrlObject();
        if ($url && $url->access()) {
          // Create block content that mimics what a routed element with a controller
          // would produce.
          $link_info = [
            'title' => $link->getTitle(),
            'url'   => $url,
            'options' => [],
            'description' => $block['description'],
          ];
          $block['content']['#content'] = [$key => $link_info];
        }
      }
      $blocks[$key] = $block;

    }

    if ($blocks) {
      ksort($blocks);
      $build = [
        '#theme' => 'admin_page',
        '#blocks' => $blocks,
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
    else {
      $build = [
        '#markup' => $this->t('You do not have any administrative items.'),
      ];
      $tree_access_cacheability->applyTo($build);
      return $build;
    }
  }

}
