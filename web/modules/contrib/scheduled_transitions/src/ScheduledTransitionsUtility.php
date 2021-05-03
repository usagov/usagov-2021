<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm as SettingsForm;

/**
 * Utilities for Scheduled Transitions module.
 */
class ScheduledTransitionsUtility implements ScheduledTransitionsUtilityInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

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
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Cache bin ID for enabled bundled cache.
   */
  protected const CID_SCHEDULED_TRANSITIONS_BUNDLES = 'scheduled_transitions_enabled_bundles';

  /**
   * Constructs a new ScheduledTransitionsUtility.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle information service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(ConfigFactoryInterface $configFactory, CacheBackendInterface $cache, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, ModerationInformationInterface $moderationInformation) {
    $this->configFactory = $configFactory;
    $this->cache = $cache;
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(EntityInterface $entity): array {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $ids = $transitionStorage->getQuery()
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->execute();
    return $transitionStorage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicableBundles(): array {
    $bundles = [];

    $bundleInfo = $this->bundleInfo->getAllBundleInfo();
    foreach ($bundleInfo as $entityTypeId => $entityTypeBundles) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $entityTypeBundles = array_filter($entityTypeBundles, function ($bundleId) use ($entityType): bool {
        return $this->moderationInformation->shouldModerateEntitiesOfBundle($entityType, $bundleId);
      }, \ARRAY_FILTER_USE_KEY);
      $bundles[$entityTypeId] = array_keys($entityTypeBundles);
    }

    return array_filter($bundles);
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles(): array {
    $enabledBundlesCache = $this->cache->get(static::CID_SCHEDULED_TRANSITIONS_BUNDLES);
    if ($enabledBundlesCache !== FALSE) {
      return $enabledBundlesCache->data ?? [];
    }

    $enabledBundles = $this->configFactory->get('scheduled_transitions.settings')
      ->get('bundles');
    $enabledBundles = array_map(
      function (array $bundleConfig) {
        return sprintf('%s:%s', $bundleConfig['entity_type'], $bundleConfig['bundle']);
      },
      is_array($enabledBundles) ? $enabledBundles : []
    );

    $applicableBundles = $this->getApplicableBundles();
    foreach ($applicableBundles as $entityTypeId => &$bundles) {
      $bundles = array_filter($bundles, function (string $bundle) use ($entityTypeId, $enabledBundles) {
        return in_array($entityTypeId . ':' . $bundle, $enabledBundles);
      });
    }

    $applicableBundles = array_filter($applicableBundles);
    $this->cache->set(static::CID_SCHEDULED_TRANSITIONS_BUNDLES, $applicableBundles, Cache::PERMANENT, [SettingsForm::SETTINGS_TAG]);
    return $applicableBundles;
  }

  /**
   * Creates a cache tag for scheduled transitions related to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return string
   *   Cache tag to add to lists showing scheduled transitions for an entity.
   */
  public static function createScheduledTransitionsCacheTag(EntityInterface $entity): string {
    return sprintf('scheduled_transitions_for:%s:%s', $entity->getEntityTypeId(), $entity->id());
  }

}
