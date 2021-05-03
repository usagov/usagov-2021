<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates permissions for host entity types for scheduled transitions.
 */
class ScheduledTransitionsPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Entity operation for viewing transitions for an individual entity.
   */
  public const ENTITY_OPERATION_VIEW_TRANSITIONS = 'view scheduled transition';

  /**
   * Entity operation for adding transitions to an individual entity.
   */
  public const ENTITY_OPERATION_ADD_TRANSITION = 'add scheduled transition';

  /**
   * Entity operation for rescheduling all transitions for an individual entity.
   */
  public const ENTITY_OPERATION_RESCHEDULE_TRANSITIONS = 'reschedule scheduled transitions';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Utilities for Scheduled Transitions module.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface
   */
  protected $scheduledTransitionsUtility;

  /**
   * Constructs a new ScheduledTransitionsPermissions.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle information service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility
   *   Utilities for Scheduled Transitions module.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, TranslationInterface $stringTranslation, ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->stringTranslation = $stringTranslation;
    $this->scheduledTransitionsUtility = $scheduledTransitionsUtility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('string_translation'),
      $container->get('scheduled_transitions.utility')
    );
  }

  /**
   * Generate dynamic permissions.
   *
   * @return array
   *   An array of permissions.
   */
  public function permissions(): array {
    $permissions = [];

    $bundleInfo = $this->scheduledTransitionsUtility->getBundles();
    foreach ($bundleInfo as $entityTypeId => $bundles) {
      $entityBundleInfo = $this->bundleInfo->getBundleInfo($entityTypeId);
      foreach ($bundles as $bundleId) {
        $tArgs = [
          '@entity_type' => $this->entityTypeManager->getDefinition($entityTypeId)->getLabel(),
          '@bundle' => $entityBundleInfo[$bundleId]['label'] ?? '',
        ];
        $viewPermission = static::viewScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$viewPermission] = ['title' => $this->t('View scheduled transitions for @entity_type:@bundle entities', $tArgs)];
        $addPermission = static::addScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$addPermission] = ['title' => $this->t('Add scheduled transitions for @entity_type:@bundle entities', $tArgs)];
        $reschedulePermission = static::rescheduleScheduledTransitionsPermission($entityTypeId, $bundleId);
        $permissions[$reschedulePermission] = ['title' => $this->t('Reschedule scheduled transitions for @entity_type:@bundle entities', $tArgs)];
      }
    }

    return $permissions;
  }

  /**
   * Creates a permission for viewing new scheduled transitions for a bundle.
   *
   * @param string $entityTypeId
   *   An entity type ID.
   * @param string $bundle
   *   A bundle ID.
   *
   * @return string
   *   Permission ID for this bundle.
   */
  public static function viewScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return sprintf('view scheduled transitions %s %s', $entityTypeId, $bundle);
  }

  /**
   * Creates a permission for creating new scheduled transitions for a bundle.
   *
   * @param string $entityTypeId
   *   An entity type ID.
   * @param string $bundle
   *   A bundle ID.
   *
   * @return string
   *   Permission ID for this bundle.
   */
  public static function addScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return sprintf('add scheduled transitions %s %s', $entityTypeId, $bundle);
  }

  /**
   * Creates a permission for rescheduling scheduled transitions for a bundle.
   *
   * @param string $entityTypeId
   *   An entity type ID.
   * @param string $bundle
   *   A bundle ID.
   *
   * @return string
   *   Permission ID for this bundle.
   */
  public static function rescheduleScheduledTransitionsPermission(string $entityTypeId, string $bundle): string {
    return sprintf('reschedule scheduled transitions %s %s', $entityTypeId, $bundle);
  }

}
