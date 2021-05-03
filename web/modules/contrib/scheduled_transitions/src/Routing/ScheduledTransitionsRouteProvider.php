<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\EntityRouteProviderInterface;
use Drupal\scheduled_transitions\Access\SupportsContentModerationAccessCheck as AccessCheck;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route provider for host entities of Scheduled Transitions.
 */
class ScheduledTransitionsRouteProvider implements EntityRouteProviderInterface {

  /**
   * Name of the link template for scheduled transitions form.
   *
   * Link template for scheduled transitions. This should not exist if the
   * entity does not have a canonical template.
   */
  public const LINK_TEMPLATE = 'scheduled_transitions';

  public const FORM = 'scheduled_transitions_entity_form';

  public const CANONICAL_PATH_SUFFIX = '/scheduled-transitions';

  public const LINK_TEMPLATE_ADD = 'scheduled_transition_add';

  public const FORM_ADD = 'scheduled_transitions_add_form';

  public const CANONICAL_PATH_SUFFIX_ADD = '/scheduled-transitions/add';

  public const ROUTE_ENTITY_TYPE = '_scheduled_transitions_entity_type';

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = new RouteCollection();
    $entityTypeId = $entity_type->id();

    if ($entity_type->hasLinkTemplate(static::LINK_TEMPLATE)) {
      $path = $entity_type->getLinkTemplate('canonical') . static::CANONICAL_PATH_SUFFIX;
      $route = (new Route($path))
        ->addDefaults([
          '_title' => 'Scheduled transitions',
          '_entity_form' => $entityTypeId . '.' . static::FORM,
        ])
        // @todo Better permissions
        ->setRequirement('_entity_access', $entityTypeId . '.' . Permissions::ENTITY_OPERATION_VIEW_TRANSITIONS)
        ->setRequirement(AccessCheck::ACCESS_CHECK_ID, 'TRUE')
        ->setOption('_admin_route', TRUE)
        ->setOption(static::ROUTE_ENTITY_TYPE, $entityTypeId)
        ->setOption('parameters', [
          $entityTypeId => ['type' => 'entity:' . $entityTypeId],
        ]);
      $collection->add(static::getScheduledTransitionRouteName($entity_type), $route);
    }

    if ($entity_type->hasLinkTemplate(static::LINK_TEMPLATE_ADD)) {
      $path = $entity_type->getLinkTemplate('canonical') . static::CANONICAL_PATH_SUFFIX_ADD;
      $route = (new Route($path))
        ->addDefaults([
          '_title' => 'Add scheduled transition',
          '_entity_form' => $entityTypeId . '.' . static::FORM_ADD,
        ])
        // @todo Better permissions
        ->setRequirement('_entity_access', $entityTypeId . '.' . Permissions::ENTITY_OPERATION_ADD_TRANSITION)
        ->setRequirement(AccessCheck::ACCESS_CHECK_ID, 'TRUE')
        ->setOption('_admin_route', TRUE)
        ->setOption(static::ROUTE_ENTITY_TYPE, $entityTypeId)
        ->setOption('parameters', [
          $entityTypeId => ['type' => 'entity:' . $entityTypeId],
        ]);
      $collection->add(static::getScheduledTransitionAddRouteName($entity_type), $route);
    }

    return $collection;
  }

  /**
   * Get the route name for scheduled transition form for an entity type..
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   An entity type.
   *
   * @return string
   *   The route name.
   */
  public static function getScheduledTransitionRouteName(EntityTypeInterface $entityType) {
    return sprintf('entity.%s.scheduled_transitions', $entityType->id());
  }

  /**
   * Get the route name for scheduled transition form for an entity type..
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   An entity type.
   *
   * @return string
   *   The route name.
   */
  public static function getScheduledTransitionAddRouteName(EntityTypeInterface $entityType) {
    return sprintf('entity.%s.scheduled_transition_add', $entityType->id());
  }

}
