<?php

namespace Drupal\usagov_menus\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    protected MenuLinkTreeInterface $menuTree,
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

    $menuItems = new MenuItems($this->menuTree);
    $items = $menuItems->getMenuTree($menuID);



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
    array $main_nav_items,
    $node,
  ): array {
    // Create an array of the active trail items from each level of the
    // menu (up to the active item)
    $active_trail = [];
    $found_active_item = false;
    $active_item_has_children = false;
    $siblings_of_active_item = null;
    $submenu = $main_nav_items['menu_tree'];

    // Loop up to 10 levels deep in the menu for the active item.
    for ($i = 0; $i < 11; $i++) {
      if (!$found_active_item) {
        $menu_item = array_filter($submenu, fn($item) => $item['active_trail'] === TRUE);

        // Should we break out of this loop if we do not have an active trail?
        if ($menu_item) {
          $menu_item = array_pop($menu_item);
          // add current item to active trail
          $active_trail[] = $menu_item;

          if ($menu_item['active']) {
            $found_active_item = TRUE;
            if (isset($menu_item['submenu'])) {
              $active_item_has_children = TRUE;
            }
            else {
              $siblings_of_active_item = $submenu;
            }
          }

          // if we have a child submenu, set it to the next $submenu to
          // inspect if we haven't found the active item yet.
          $submenu = $menu_item['submenu'] ?? [];

        }
      }
    }


    return [
      '#theme' => 'usagov_menu_mobile',
      '#main_nav_items' => $main_nav_items,
      '#node' => $node,
      '#translations' => $this->translations,

      '#active_trail' => $active_trail,
      '#found_active_item' => $found_active_item,
      '#active_item_has_children' => $active_item_has_children,
      '#sibblings_of_active_item' => $siblings_of_active_item,
      '#submenu' => $submenu,
      // Ensure drupal knows this block should be cached per path.
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args'],
      ]
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
      menuTree: $container->get('menu.link_tree'),
    );
  }
}
