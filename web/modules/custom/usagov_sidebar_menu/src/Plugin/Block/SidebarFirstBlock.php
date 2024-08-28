<?php

namespace Drupal\usagov_sidebar_menu\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Breadcrumb\ChainBreadcrumbBuilderInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a USAGOV Custom Sidebar Menu Block.
 */
#[Block(
  id: "usagov_sidebarfirst_block",
  admin_label: new TranslatableMarkup("Left Menu Sidebar Block"),
  category: new TranslatableMarkup("USAgov")
)]
class SidebarFirstBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected LanguageInterface $language,
    protected Request $request,
    protected RendererInterface $renderer,
    protected ChainBreadcrumbBuilderInterface $breadcrumbManager,
    protected MenuLinkTreeInterface $menuTree,
    protected MenuActiveTrailInterface $trail,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // TODO move some of these to block config?
    switch ($this->language->getId()) {
      case 'es':
        $leftMenuName = 'página';
        $menuID = 'left-menu-spanish';
        $navAriaLabel = 'Secundaria';
        break;

      case 'en':
      default:
        $leftMenuName = 'page';
        $menuID = 'left-menu-english';
        $navAriaLabel = 'Secondary';
        break;
    }

    switch (TRUE) {
      case str_starts_with('/agencies/', $this->request->getUri()):
      case str_starts_with('/states/', $this->request->getUri()):
        $output = 'menu--agency-sidebar.html.twig';
        break;

      case str_starts_with('/es/agencias/', $this->request->getUri()):
      case str_starts_with('/es/estados/', $this->request->getUri()):
        $output = 'menu--es-agency-sidebar.html.twig';
        break;

      default:
        $output = 'menus-explore';
        break;
    }

//    $crumbs = $this->breadcrumbManager->build(
//      RouteMatch::createFromRequest($this->request)
//    );
//    // Remove homepage.
//    /** @var \Drupal\Core\Link[] */
//    $links = array_filter($crumbs->getLinks(), function (Link $crumb) {
//      return $crumb->getUrl()->getRouteName() !== '<front>';
//    });
    // Get siblings from menu.
    $params = new MenuTreeParameters();
    $menuIDs = $this->trail->getActiveTrailIds($menuID);
    $params->setActiveTrail($menuIDs);
    $tree = $this->menuTree->load($menuID, $params);
    // remove items not in trail
    $tree = array_filter($tree, function(MenuLinkTreeElement $item) {
      return $item->inActiveTrail;
    });

    // Sort by menu weight.
    $tree = $this->menuTree->transform($tree, [
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $items = $tree;

    /*
    foreach ($tree as $menuItem) {
      $items[] = $menuItem;

      if (false && $menuItem->subtree) {
        foreach ($menuItem->subtree as $subMenuItem) {
          $items[] = $subMenuItem;
          $suburl = $subMenuItem->link->getUrlObject();
          $output .= sprintf(
            '<li>SUBTREE: <a href="%s">%s</a></li>',
            $suburl->toString(),
            $subMenuItem->link->getTitle(),
          );

          if ($subMenuItem->inActiveTrail && $subMenuItem->hasChildren) {
            foreach ($subMenuItem->subtree as $sibling) {
              $items[] = $sibling;
              $output .= sprintf(
                '<li>SIBLING: <a href="%s">%s</a></li>',
                $sibling->link->getUrlObject()->toString(),
                $sibling->link->getTitle(),
              );
            }
          }

        }
      }
    }
    */

    $items = $this->menuTree->build($items);
    return [
      '#theme' => 'usagov_menu_sidebar',
      '#start_item' => $items['#items'][array_key_first($items['#items'])],
      '#depth' => 0,
      '#nav_aria_label' => $navAriaLabel,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      language: $container->get('language_manager')->getCurrentLanguage(),
      request: $container->get('request_stack')->getCurrentRequest(),
      renderer: $container->get('renderer'), // remove?
      breadcrumbManager: $container->get('breadcrumb'), // remove?
      menuTree: $container->get('menu.link_tree'),
      trail: $container->get('menu.active_trail'),
    );
  }
}
