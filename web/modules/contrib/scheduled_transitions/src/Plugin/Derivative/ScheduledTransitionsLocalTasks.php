<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Plugin\Derivative;

use Drupal\scheduled_transitions\Plugin\Menu\LocalTask\ScheduledTransitionsLocalTask;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Scheduled transitions tab for entities.
 */
class ScheduledTransitionsLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The base plugin ID.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new ScheduledTransitionsLocalTask.
   *
   * @param string $base_plugin_id
   *   The base plugin ID.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct($base_plugin_id, EntityTypeManagerInterface $entity_type_manager) {
    $this->basePluginId = $base_plugin_id;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $base_plugin_id,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entityType) {
      if ($entityType->hasLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE)) {
        $entityTypeId = $entityType->id();
        $this->derivatives["$entityTypeId.scheduled_transitions"] = [
          'class' => ScheduledTransitionsLocalTask::class,
          'route_name' => ScheduledTransitionsRouteProvider::getScheduledTransitionRouteName($entityType),
          // Title is overridden by class.
          'title' => $this->t('Scheduled transitions'),
          'base_route' => "entity.$entityTypeId.canonical",
          // Weight it after nodes' Edit, Delete, Versions.
          'weight' => 30,
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
