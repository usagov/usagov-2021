<?php

namespace Drupal\usagov_wizard;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class MenuChecker implements ContainerInjectionInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MenuChecker object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getTermParents(EntityInterface $term) {
    if ($term->hasField('parent') && !$term->get('parent')->isEmpty()) {
      $tid = $term->id();
      $parents = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadAllParents($tid);
      return array_keys($parents);
    }
  }

  public function getTargetEntities(string $menu_name) {
    $menu_links = $this->entityTypeManager->getStorage('menu_link_content')
      ->loadByProperties(['menu_name' => $menu_name]);

    $menu_entities = [];

    foreach ($menu_links as $menu_entity) {
      // Check if the link leads to a taxonomy term and the route is not external.
      $url = $menu_entity->getUrlObject();
      if ($url->isRouted()) {
        $route_parameters = $url->getRouteParameters();
        if (isset($route_parameters['taxonomy_term'])) {
          $target_tids[] = $route_parameters['taxonomy_term'];
          $menu_entity = $menu_entity->parent->getEntity();
          if ($menu_entity->field_custom_parent->value == 1) {
          $parentEntityUuid = $menu_entity->parent->value;
          $parentEntity = \Drupal::service('entity.repository')
            ->loadEntityByUuid('menu_link_content', explode(':', $parentEntityUuid));
          $menu_entities[] = $parentEntity;
          // Load children of the parent entity
            $children = $this->entityTypeManager->getStorage('menu_link_content')
              ->loadByProperties([
                'menu_name' => 'left-menu-english',
                'enabled' => 1,
                'parent' => $parentEntityUuid,
              ]);
            foreach ($children as $child) {
              $menu_entities[] = $child;
            }
          }
        }
      }
    }
    if (isset($target_tids)) {
      return [
        'target_tids' => $target_tids,
        'menu_entities' => $menu_entities
      ];
    } else {
      return [];
    }
  }

  public function getMenuParent(EntityInterface $entity) {
    $termParents = $this->getTermParents($entity);
    if (isset($termParents['test'])) {
      dpm($termParents['test']);
    }
  }

}
