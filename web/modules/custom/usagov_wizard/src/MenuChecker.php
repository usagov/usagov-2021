<?php

namespace Drupal\usagov_wizard;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MenuChecker.
 *
 * Checks an entity against a nav menu and generates entities from the menu.
 */
class MenuChecker implements ContainerInjectionInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  public $currentRouteMatch;

  /**
   * Constructs a new MenuChecker object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Use this to build values for the entity that is passed in.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   Checks the current route to generate the entity.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    CurrentRouteMatch $current_route_match,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRouteMatch = $current_route_match;

  }//end __construct()

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
          $container->get('current_route_match')
      );

  }//end create()

  /**
   * Retrieves the parent terms of a given taxonomy term.
   *
   * @param \Drupal\Core\Entity\EntityInterface $term
   *   The taxonomy term entity for which to retrieve the parent terms.
   *
   * @return int[]
   *   An array of term IDs representing the parent terms of the given term.
   *   Returns an empty array if the given term has no parent term or if the
   *   'parent' field is not set.
   */
  public function getTermParents(EntityInterface $term) {
    if ($term->hasField('parent') && !$term->get('parent')->isEmpty()) {
      $tid     = $term->id();
      $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($tid);
      return array_keys($parents);
    }

  }//end getTermParents()

  /**
   * Get the values in the field_heading to determine the third breadcrumb.
   */
  public function getHeadings(EntityInterface $term) {
    $parents = $this->getTermParents($term);

    foreach ($parents as $parent) {
      $parent  = $this->entityTypeManager->getStorage('taxonomy_term')->load($parent);
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
   * @return array
   *   An array containing the target term IDs and menu entities.
   */
  public function getTargetEntities(string $langcode) {
    if ($langcode == 'en') {
      $menu_name = 'left-menu-english';
    }
    else {
      $menu_name = 'left-menu-spanish';
    }

    $menu_links = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['menu_name' => $menu_name]);

    $menu_entities = [];

    foreach ($menu_links as $menu_entity) {
      // Check if the link leads to a taxonomy term and the route is not
      // external.
      $url = $menu_entity->getUrlObject();
      if ($url->isRouted()) {
        $route_parameters = $url->getRouteParameters();
        if (isset($route_parameters['taxonomy_term'])) {
          $target_tids[] = $route_parameters['taxonomy_term'];
          $menu_entity   = $menu_entity->parent->getEntity();

          /*
          The field_custom_parent was created as a field only visible
          in the left-nav menu EN and ES form displays. The intention
          of the field is to handle situations where the same term
          is linked to multiple times in the same menu. The following
          logic check will narrow down the duplicates to only look for the
          entity that has field_custom_parent checked. */
          if ($menu_entity->field_custom_parent->value == 1) {
            $parentEntityUuid = $menu_entity->parent->value;
            $parentEntity     = \Drupal::service('entity.repository')->loadEntityByUuid('menu_link_content', explode(':', $parentEntityUuid));
            $menu_entities[]  = $parentEntity;
            // Load children of the parent entity.
            $children = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(
                  [
                    'menu_name' => $menu_name,
                    'enabled'   => 1,
                    'parent'    => $parentEntityUuid,
                  ]
              );
            foreach ($children as $child) {
              $menu_entities[] = $child;
            }
          }
        }//end if
      }//end if
    }//end foreach

    if (isset($target_tids)) {
      return [
        'target_tids'   => $target_tids,
        'menu_entities' => $menu_entities,
      ];
    }
    else {
      return [];
    }

  }//end getTargetEntities()

  /**
   * Check for matches between arrays of entity ids.
   *
   * The two arrays to check contain menu entities marked with "custom_parent"
   * set to true and the current page and all of its parents' entity id.
   *
   * @param array $entity_array_1
   *   Array of entities.
   * @param array $entity_array_2
   *   Array of entities.
   *
   * @return array
   *   An array containing the target term IDs and menu entities.
   */
  public function getMatchingEntities(array $entity_array_1, array $entity_array_2) {
    $matching_tid = array_intersect($entity_array_1, $entity_array_2);

    $matching_tid = implode(',', $matching_tid);

    return $matching_tid;
  }

}//end class
