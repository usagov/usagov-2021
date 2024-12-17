<?php

namespace Drupal\usagov_wizard;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MenuChecker.
 *
 * Checks an entity against a nav menu and generates entities from the menu.
 */
class MenuChecker implements ContainerInjectionInterface {

  /**
   * Constructs a new MenuChecker object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Use this to build values for the entity that is passed in.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   Checks the current route to generate the entity.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   */
  final public function __construct(
    private EntityTypeManagerInterface $entity_type_manager,
    private CurrentRouteMatch $current_route_match,
    private EntityRepositoryInterface $entity_repository,
  ) {
  }

  /**
   * Creates a new instance of this class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container object used to retrieve necessary services.
   *
   * @return static
   *   A new instance of this class.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('entity.repository')
    );

  }//end create()

  /**
   * Retrieves the parent terms of a given taxonomy term.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term entity for which to retrieve the parent terms.
   *
   * @return int[]
   *   An array of term IDs representing the parent terms of the given term.
   *   Returns an empty array if the given term has no parent term or if the
   *   'parent' field is not set.
   */
  public function getTermParents(TermInterface $term): array {
    if ($term->hasField('parent') && !$term->get('parent')->isEmpty()) {
      $tid     = $term->id();
      $parents = $this->entity_type_manager->getStorage('taxonomy_term')->loadAllParents($tid);
      return array_keys($parents);
    }

    return [];

  }//end getTermParents()

  /**
   * Get the values in the field_heading to determine the third breadcrumb.
   */
  public function getHeadings(EntityInterface $term): array {
    $parents = $this->getTermParents($term);
    $headings = [];

    foreach ($parents as $parent) {
      $parent  = $this->entity_type_manager->getStorage('taxonomy_term')->load($parent);
      $heading = $parent->field_heading->value;
      $name    = $parent->get('name')->value;
      $id      = $parent->id();

      $headings[] = [
        'name'  => $name,
        'label' => $heading,
        'id'    => $id,
      ];
    }

    $headings = array_reverse($headings);
    return $headings;

  }//end getHeadings()

  /**
   * Retrieves target entities based on the provided menu name.
   *
   * @param string $langcode
   *   The current language code.
   *
   * @return array|void
   *   An array containing the target term IDs and menu entities.
   */
  public function getMenuEntities(string $langcode) {
    if ($langcode == 'en') {
      $menu_name = 'left-menu-english';
    }
    else {
      $menu_name = 'left-menu-spanish';
    }

    $menu_links = $this->entity_type_manager->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_name]);

    $menu_entities = [];

    foreach ($menu_links as $menu_entity) {
      // Check if the link leads to a taxonomy term and the route is not
      // external.
      $url = $menu_entity->getUrlObject();
      if ($url->isRouted()) {
        $route_parameters = $url->getRouteParameters();
        if (isset($route_parameters['taxonomy_term'])) {
          if (isset($menu_entity->parent)) {
            if ($menu_entity->field_custom_parent->value == 1) {
              $tid = $route_parameters['taxonomy_term'];
              $menu_taxonomy_links[$tid] = [];

              if ($menu_taxonomy_links) {
                if (isset($menu_entity->parent->value)) {
                  $primaryEntityUuid = $menu_entity->parent->value;
                  $primaryEntity = $this->entity_repository
                    ->loadEntityByUuid('menu_link_content', explode(':', $primaryEntityUuid));
                  $menu_taxonomy_links[$tid][0] = $primaryEntity;

                  // Load children of the parent entity.
                  $children = $this->entity_type_manager->getStorage('menu_link_content')
                    ->loadByProperties(
                        [
                          'menu_name' => $menu_name,
                          'enabled' => 1,
                          'parent' => $primaryEntityUuid,
                        ]
                      );

                  foreach ($children as $child) {
                    array_push($menu_taxonomy_links[$tid], $child);
                  }

                }
              }
            }
          }
        }//end if
      }//end if
    }

    if (isset($menu_taxonomy_links)) {
      return [
        'menu_entities' => $menu_taxonomy_links,
        'primary_entity' => $primaryEntity ?? NULL,
      ];
    }
    else {
      return [];
    }

  }//end getMenuEntities()

}//end class
