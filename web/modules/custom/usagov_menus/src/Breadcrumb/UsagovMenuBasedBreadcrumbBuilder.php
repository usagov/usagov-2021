<?php

namespace Drupal\usagov_menus\Breadcrumb;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\menu_breadcrumb\MenuBasedBreadcrumbBuilder;
use Drupal\node\NodeInterface;
use Drupal\usagov_menus\Menu\UsagovMenuActiveTrail;

/**
 * Menu breadcrumb builder whose derived active trail prefers the original link.
 *
 * With "Derive MenuActiveTrail from RouteMatch" on, menu_breadcrumb builds a
 * trail for the given route with `new MenuActiveTrail(...)`, so the mlid
 * ordering from UsagovMenuActiveTrail never applies. The published-pages CSV
 * (PublishedPagesCommands) depends on that derived mode, one RouteMatch per
 * node.
 *
 * applies() is copied from menu_breadcrumb 2.0.1. The only intended difference
 * is that the derived trail is a UsagovMenuActiveTrail. The parent keeps its
 * state in private properties, so this class writes it through the parent's
 * setters, and the taxonomy branch also checks for a node before passing it to
 * setTaxonomyAttachment(). Re-diff against
 * MenuBasedBreadcrumbBuilder::applies() whenever menu_breadcrumb is updated.
 */
class UsagovMenuBasedBreadcrumbBuilder extends MenuBasedBreadcrumbBuilder {

  /**
   * The path matcher, passed to each derived active trail.
   */
  protected PathMatcherInterface $usagovPathMatcher;

  /**
   * Sets the path matcher.
   */
  public function setUsagovPathMatcher(PathMatcherInterface $path_matcher): void {
    $this->usagovPathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {
    // @todo Remove null safe operator in Drupal 12.0.0, see
    //   https://www.drupal.org/project/drupal/issues/3459277.
    $cacheable_metadata?->addCacheContexts(['route']);

    // This may look heavyweight for applies() but we have to check all ways the
    // current path could be attached to the selected menus before turning over
    // breadcrumb building (and caching) to another builder.  Generally this
    // should not be a problem since it will then fall back to the system (path
    // based) breadcrumb builder which caches a breadcrumb no matter what.
    if (!$this->config->get('determine_menu')) {
      return FALSE;
    }
    // Don't breadcrumb the admin pages, if disabled on config options:
    if ($this->config->get('disable_admin_page') && $this->adminContext->isAdminRoute($route_match->getRouteObject())) {
      return FALSE;
    }
    // No route name means no active trail:
    $route_name = $route_match->getRouteName();
    if (!$route_name) {
      return FALSE;
    }

    // This might be a "node" with no fields, e.g. a route to a "revision" URL,
    // so we don't check for taxonomy fields on unfieldable nodes:
    $node_object = $route_match->getParameters()->get('node');
    $node_is_fieldable = $node_object instanceof FieldableEntityInterface;

    // Make sure menus are selected, and breadcrumb text strings, are displayed
    // in the content rather than the (default) interface language:
    $this->setContentLanguage($this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());

    // Check each selected menu, in turn, until a menu or taxonomy match found:
    // then cache its state for building & caching in build() and exit.
    $menus = $this->getMenus();
    uasort($menus, function ($a, $b) {
      return SortArray::sortByWeightElement($a, $b);
    });
    foreach ($menus as $menu_name => $params) {

      // Look for current path on any enabled menu.
      if (!empty($params['enabled'])) {

        // Skip over any menu that's not in the current content language,
        // if and only if the "language handling" option set for that menu.
        // NOTE this menu option is added late, so we check its existence first.
        if (array_key_exists('langhandle', $params) && $params['langhandle']) {
          $menu_objects = $this->entityTypeManager->getStorage('menu')
            ->loadByProperties(['id' => $menu_name]);
          if ($menu_objects) {
            $menu_language = reset($menu_objects)->language()->getId();
            if ($menu_language != $this->getContentLanguage() &&
              $menu_language !== Language::LANGCODE_NOT_SPECIFIED &&
              $menu_language !== Language::LANGCODE_NOT_APPLICABLE) {
              continue;
            }
          }
        }

        if ($this->config->get('derived_active_trail')) {
          // Do not use the global MenuActiveTrail service because we need one
          // which is aware of the given routeMatch, not of the global one.
          // USAGov: use UsagovMenuActiveTrail so the mlid ordering applies.
          $menuActiveTrail = new UsagovMenuActiveTrail($this->menuLinkManager, $route_match, $this->cacheMenu, $this->lock);
          $menuActiveTrail->setUsagovPathMatcher($this->usagovPathMatcher);
          $trail_ids = $menuActiveTrail->getActiveTrailIds($menu_name);
        }
        else {
          // Default, for the majority & compatibility with historical use and
          // other modules: use the global (injected) MenuActiveTrail service.
          $trail_ids = $this->menuActiveTrail->getActiveTrailIds($menu_name);
        }
        $trail_ids = array_filter($trail_ids);
        if ($trail_ids) {
          $this->setMenuName($menu_name);
          // @phpstan-ignore argument.type (menu_breadcrumb documents the trail as a string; it is an array of IDs.)
          $this->setMenuTrail($trail_ids);
          $this->clearTaxonomyAttachment();
          return TRUE;
        }
      }

      // Look for a "taxonomy attachment" by node field, regardless of language.
      if (!empty($params['taxattach']) && $node_is_fieldable) {

        // Check all taxonomy terms applying to the current page.
        foreach ($node_object->getFields() as $field) {
          if ($field->getSetting('target_type') == 'taxonomy_term') {

            // In general these entity references will support multiple
            // values so we check all terms in the order they are listed.
            foreach ($field->referencedEntities() as $term) {
              $url = $term->toUrl();
              $route_links = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters(), $menu_name);
              if (!empty($route_links) && $node_object instanceof NodeInterface) {
                // Successfully found taxonomy attachment, so pass to build():
                // - the menu in which we have found the attachment
                // - the effective menu trail of the taxonomy-attached node
                // - the node itself (in build() we will find its title & URL)
                $taxonomy_term_link = reset($route_links);
                $taxonomy_term_id = $taxonomy_term_link->getPluginId();
                $this->setMenuName($menu_name);
                // @phpstan-ignore argument.type (menu_breadcrumb documents the trail as a string; it is an array of IDs.)
                $this->setMenuTrail($this->menuLinkManager->getParentIds($taxonomy_term_id));
                $this->setTaxonomyAttachment($node_object);
                return TRUE;
              }
            }
          }
        }
      }
    }
    // No more menus to check...
    return FALSE;
  }

  /**
   * Clears the parent's private $taxonomyAttachment.
   *
   * setTaxonomyAttachment() only accepts a node, but applies() must reset it to
   * NULL when a menu trail (not a taxonomy term) matched.
   */
  private function clearTaxonomyAttachment(): void {
    \Closure::bind(function (): void {
      // @phpstan-ignore assign.propertyType (menu_breadcrumb types it as a Node, but its own applies() stores NULL.)
      $this->taxonomyAttachment = NULL;
    }, $this, MenuBasedBreadcrumbBuilder::class)();
  }

}
