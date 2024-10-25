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

  private array $translations;

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    switch ($this->language->getId()) {
      case 'es':
        $menuID = 'left-menu-spanish';
        $this->translations = [
          'home' => 'Página principal',
          'home_URL' => '/es',
          'close' => 'Cerrar',
          'search' => 'Buscar',
          'search_placeholder' => 'Busque en este sitio...',
          'search_affiliate' => 'usagov_es_internal',
          'all_topics' => 'Todos los temas y servicios',
          'phone_URL' => '/es/llamenos',
          'form_id' => 'usagov_es_internal-mobile',
        ];
        break;

      case 'en':
      default:
        $menuID = 'left-menu-english';
        $this->translations = [
          'home' => 'Home',
          'home_URL' => '/',
          'close' => 'Close',
          'search' => 'Search',
          'search_placeholder' => 'Search all government',
          'search_affiliate' => 'usagov_all_gov',
          'all_topics' => 'All topics and services',
          'phone_URL' => '/phone',
          'form_id' => 'usagov_all_gov-mobile'
        ];
        break;
    }

    if ($active = $this->trail->getActiveLink($menuID)) {
      $crumbs = $this->menuLinkManager->getParentIds($active->getPluginId());
      $items = $this->getMenuTreeItems($menuID, $crumbs, $active, maxLevels: -1);
      $twigVars = $this->prepareMenuItemsForTemplate($items, $active);
      return $this->renderItems($items, $twigVars);

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
    return $this->renderItems($items, $twigVars);
  }

  /**
   * Returns the render array to theme the navigation lists.
   */
  private function renderItems(array $items, array $twigVars): array {

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
   * @param array $submenu A render-array with #items
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

}
