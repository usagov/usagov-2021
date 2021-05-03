<?php

namespace Drupal\scheduled_publish\Service;

use DateTime;
use DateTimeZone;
use Drupal\Component\Datetime\Time;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\scheduled_publish\Plugin\Field\FieldType\ScheduledPublish;

/**
 * Class ScheduledPublishCron
 *
 * @package Drupal\scheduled_publish\Service
 */
class ScheduledPublishCron {

  /**
   * @var array
   */
  public static $supportedTypes = [
    'node',
    'media',
  ];

  /**
   * @var EntityTypeBundleInfo
   */
  private $entityBundleInfoService;

  /**
   * @var EntityFieldManager
   */
  private $entityFieldManager;

  /**
   * @var EntityTypeManager
   */
  private $entityTypeManager;

  /**
   * @var Time
   */
  private $dateTime;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * ScheduledPublishCron constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Drupal\Component\Datetime\TimeInterface $dateTime
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   */
  public function __construct(EntityTypeBundleInfoInterface $entityBundleInfo, EntityFieldManagerInterface $entityFieldManager, EntityTypeManagerInterface $entityTypeManager, TimeInterface $dateTime, ModerationInformationInterface $moderation_info, LoggerChannelFactoryInterface $logger) {
    $this->entityBundleInfoService = $entityBundleInfo;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->dateTime = $dateTime;
    $this->moderationInfo = $moderation_info;
    $this->logger = $logger;
  }

  /**
   *  Run field updates
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function doUpdate(): void {
    foreach (self::$supportedTypes as $supportedType) {
      $this->doUpdateFor($supportedType);
    }
  }

  /**
   * Run field update for specific entity type
   *
   * @param $entityType
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function doUpdateFor($entityType) {
    $bundles = $this->entityBundleInfoService->getBundleInfo($entityType);

    foreach ($bundles as $bundleName => $value) {

      $scheduledFields = $this->getScheduledFields($entityType, $bundleName);
      if (\count($scheduledFields) > 0) {
        foreach ($scheduledFields as $scheduledField) {
          $query = $this->entityTypeManager->getStorage($entityType)
            ->getQuery('AND');
          $query->condition($entityType === 'media' ? 'bundle' : 'type', $bundleName);
          $query->condition($scheduledField, NULL, 'IS NOT NULL');
          $query->accessCheck(FALSE);
          $query->latestRevision();
          $entities = $query->execute();
          foreach ($entities as $entityRevision => $entityId) {
            $entity = $this->entityTypeManager->getStorage($entityType)
              ->loadRevision($entityRevision);
            $this->updateEntityField($entity, $scheduledField);
          }
        }
      }
    }
  }

  /**
   * Returns scheduled publish fields
   *
   * @param string $entityTypeName
   * @param string $bundleName
   *
   * @return array
   */
  private function getScheduledFields(string $entityTypeName, string $bundleName): array {
    $scheduledFields = [];
    $fields = $this->entityFieldManager
      ->getFieldDefinitions($entityTypeName, $bundleName);
    foreach ($fields as $fieldName => $field) {
      /** @var FieldConfig $field */
      if (strpos($fieldName, 'field_') !== FALSE) {
        if ($field->getType() === 'scheduled_publish') {
          $scheduledFields[] = $fieldName;
        }
      }
    }

    return $scheduledFields;
  }

  /**
   * Update scheduled publish fields
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param string $scheduledField
   *
   * @throws \Exception
   */
  private function updateEntityField(ContentEntityBase $entity, string $scheduledField): void {
    /** @var FieldItemList $scheduledEntity */
    $scheduledEntity = $entity->get($scheduledField);
    $scheduledValue = $scheduledEntity->getValue();
    if (empty($scheduledValue)) {
      return;
    }

    foreach ($scheduledValue as $key => $value) {
      if ($this->getTimestampFromIso8601($value['value']) <= $this->dateTime->getCurrentTime()) {
        unset($scheduledValue[$key]);
        $this->updateEntity($entity, $value['moderation_state'], $scheduledField, $scheduledValue);
      }
    }
  }

  /**
   * Returns timestamp from ISO-8601 datetime
   *
   * @param string $dateIso8601
   *
   * @return int
   * @throws \Exception
   */
  private function getTimestampFromIso8601(string $dateIso8601): int {
    $datetime = new DateTime($dateIso8601, new DateTimeZone(ScheduledPublish::STORAGE_TIMEZONE));
    $datetime->setTimezone(new \DateTimeZone(date_default_timezone_get()));

    return $datetime->getTimestamp();
  }

  /**
   * Updates entity
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param string $moderationState
   * @param string $scheduledPublishField
   * @param $scheduledValue
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function updateEntity(ContentEntityBase $entity, string $moderationState, string $scheduledPublishField, $scheduledValue): void {
    $entity->set($scheduledPublishField, $scheduledValue);
    // Only make valid transitions.
    if ($this->isValidStateChange($entity, $moderationState)) {
      $currentModerationState = $entity->get('moderation_state')
        ->getValue()[0]['value'];
      $entity->set('moderation_state', $moderationState);
    }
    $entity->save();

    // Log valid transitions.
    if (isset($currentModerationState)) {
      $entity_info = $entity->label() . ' (' . $entity->id() . ')';
      $this->logger->get('scheduled_publish')
        ->info('The moderation state of @entity was changed from @orig_status to @current_status',
          ['@entity' => $entity_info, '@orig_status' => $currentModerationState, '@current_status' => $moderationState]);
    }
  }

  /**
   * Checks if the state change is valid.
   *
   * @param \Drupal\Core\Entity\ContentEntityBase $entity
   * @param string $moderationState
   *
   * @return boolean
   */
  protected function isValidStateChange(ContentEntityBase $entity, string $moderationState): bool {
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    $current_state = $entity->moderation_state->value ? $workflow->getTypePlugin()->getState($entity->moderation_state->value) : $workflow->getTypePlugin()->getInitialState($entity);
    $transitions = $current_state->getTransitions();
    foreach ($transitions as $key => $value) {
      if ($value->to()->id() === $moderationState) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
