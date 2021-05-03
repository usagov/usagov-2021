<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm as SettingsForm;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface;
use Symfony\Component\Routing\Route;

/**
 * Check if entity supports content moderation.
 *
 * Supports revisions, has active workflow, etc.
 */
class SupportsContentModerationAccessCheck implements AccessInterface {

  /**
   * Value of 'applies_to' in service tag.
   */
  public const ACCESS_CHECK_ID = '_scheduled_transitions_supports_content_moderation';

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Utilities for Scheduled Transitions module.
   *
   * @var \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface
   */
  protected $scheduledTransitionsUtility;

  /**
   * Constructs a new SupportsContentModerationAccessCheck.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   * @param \Drupal\scheduled_transitions\ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility
   *   Utilities for Scheduled Transitions module.
   */
  public function __construct(ModerationInformationInterface $moderationInformation, ScheduledTransitionsUtilityInterface $scheduledTransitionsUtility) {
    $this->moderationInformation = $moderationInformation;
    $this->scheduledTransitionsUtility = $scheduledTransitionsUtility;
  }

  /**
   * Checks the entity supports content moderation.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    /** @var string $routeEntityType */
    $routeEntityTypeId = $route
      ->getOption(ScheduledTransitionsRouteProvider::ROUTE_ENTITY_TYPE);

    $entity = $route_match->getParameter($routeEntityTypeId);
    if ($entity instanceof ContentEntityInterface) {
      $access = (new CacheableMetadata())
        ->addCacheableDependency($entity)
        ->addCacheTags([SettingsForm::SETTINGS_TAG]);
      $enabledBundles = $this->scheduledTransitionsUtility->getBundles();
      if (in_array($entity->bundle(), $enabledBundles[$entity->getEntityTypeId()] ?? [], TRUE) && $this->moderationInformation->isModeratedEntity($entity)) {
        return AccessResult::allowed()->addCacheableDependency($access);
      }
      return AccessResult::forbidden('Scheduled transitions not supported on this entity.')
        ->addCacheableDependency($access);
    }
    return AccessResult::forbidden('No entity provided.');
  }

}
