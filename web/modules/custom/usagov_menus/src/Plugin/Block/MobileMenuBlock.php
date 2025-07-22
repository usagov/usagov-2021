<?php

namespace Drupal\usagov_menus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: "usagov_mobile_menu_block",
  admin_label: new TranslatableMarkup("Mobile Menu Block"),
  category: new TranslatableMarkup("USAgov"),
)]
class MobileMenuBlock extends AbstractMenuBlock {

  /**
   * @var array<string, string>
   */
  private array $translations;

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function build(): array {
    $path = $this->request->getPathInfo();

    switch ($this->language->getId()) {
      case 'es':
        $menuID = 'left-menu-spanish';
        $affiliate_es = \Drupal::state()->get('usagov_affiliate_es', 'usagov_es_internal');
        $this->translations = [
          'home' => 'Página principal',
          'home_URL' => '/es',
          'close' => 'Cerrar',
          'search' => 'Buscar',
          'search_placeholder' => 'Busque en este sitio...',
          'search_affiliate' => $affiliate_es,
          'all_topics' => 'Todos los temas y servicios',
          'form_id' => 'usagov_es_internal-mobile',
        ];
        break;

      case 'en':
      default:
        $menuID = 'left-menu-english';
        $affiliate = \Drupal::state()->get('usagov_affiliate', 'usagov_en_all_gov');
        $this->translations = [
          'home' => 'Home',
          'home_URL' => '/',
          'close' => 'Close',
          'search' => 'Search',
          'search_placeholder' => 'Search all government',
          'search_affiliate' => $affiliate,
          'all_topics' => 'All topics and services',
          'form_id' => 'usagov_all_gov-mobile',
        ];
        break;
    }

    // Check if we're on a blog page and use the blog menu
    if (str_starts_with($path, '/blog/') || $path === '/blog') {
      return $this->buildBlogMenu();
    }

    if ($active = $this->trail->getActiveLink($menuID)) {
      $crumbs = $this->menuLinkManager->getParentIds($active->getPluginId());
      $items = $this->getMenuTreeItems($menuID, $crumbs, $active, maxLevels: -1);
      $twigVars = $this->prepareMenuItemsForTemplate($items, $active);

      return $this->renderItems($items, $twigVars, $menuID);
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
    ];

    return $this->renderItems($items, $twigVars, $menuID);
  }

  /**
   * Returns the render array to theme the navigation lists.
   *
   * @param array<string, mixed> $items
   * @param array<string, mixed> $twigVars
   * @return array<string, mixed>
   */
  private function renderItems(array $items, array $twigVars, string $menuID): array {
    $node = $this->routeMatch->getParameter('node');
    return array_merge(
      [
        '#theme' => 'usagov_menu_mobile',
        '#main_nav_items' => $items,
        '#node' => $node,
        '#translations' => $this->translations,

        // Ensure drupal knows this block should be cached per path.
        // and when the menu changes
        '#cache' => [
          'contexts' => ['url.path', 'url.query_args'],
          'tags' => ['config:system.menu.' . $menuID],
        ],
      ],
      $twigVars
    );
  }

  /**
   * @param array<string, mixed> $submenu A render-array with #items
   *
   * @return array<string, mixed>
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
      $menuItem = $menuItem[$key] ?? FALSE;

      if (!$menuItem && !$key) {
        // No active link in the menu? We should bail.
        // Template takes care of showing things.
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
      }
      else {
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
   * Build the blog menu for mobile navigation.
   *
   * @return array<string, mixed>
   */
  private function buildBlogMenu(): array {
    $menuID = 'usagov_blog_menu';

    if ($active = $this->trail->getActiveLink($menuID)) {
      $crumbs = $this->menuLinkManager->getParentIds($active->getPluginId());
      $items = $this->getMenuTreeItems($menuID, $crumbs, $active, maxLevels: -1);
      $twigVars = $this->prepareMenuItemsForTemplate($items, $active);

      return $this->renderItems($items, $twigVars, $menuID);
    }

    // Display the full blog menu structure with "Our Blog" at the top.
    $items = $this->getMenuTreeItems($menuID, [], NULL, FALSE, -1);

    $ourBlogItem = NULL;
    if (isset($items['#items'])) {
      foreach ($items['#items'] as $item) {
        if ($item['title'] === 'Our Blog') {
          $ourBlogItem = $item;
          break;
        }
      }
    }

    // If we found "Our Blog", structure it as the active trail with its children
    if ($ourBlogItem) {
      $twigVars = [
        '#active_trail' => [$ourBlogItem], // Put "Our Blog" in active trail
        '#found_active_item' => FALSE,
        '#active_item_has_children' => !empty($ourBlogItem['below']),
        '#siblings_of_active_item' => [],
        '#submenu' => $ourBlogItem['below'] ?? [], // Years are children of "Our Blog"
      ];
    }
    else {
      // Fallback if "Our Blog" not found
      $twigVars = [
        '#active_trail' => [],
        '#found_active_item' => FALSE,
        '#active_item_has_children' => TRUE,
        '#siblings_of_active_item' => [],
        '#submenu' => $items['#items'],
      ];
    }

    return $this->renderItems($items, $twigVars, $menuID);
  }

}
