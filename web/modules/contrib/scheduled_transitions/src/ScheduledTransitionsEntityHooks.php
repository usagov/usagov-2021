<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionAddForm;
use Drupal\scheduled_transitions\Form\ScheduledTransitionForm;
use Drupal\scheduled_transitions\Form\ScheduledTransitionsSettingsForm as SettingsForm;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsPermissions as Permissions;

/**
 * Entity related hooks for Scheduled Transitions module.
 */
class ScheduledTransitionsEntityHooks implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Array of IDs of Entity types using content moderation workflows.
   *
   * @var string[]
   */
  protected $moderatedEntityTypes;

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructs a new ScheduledTransitionsEntityHooks.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EntityTypeManagerInterface $entityTypeManager, ModerationInformationInterface $moderationInformation) {
    $this->configFactory = $configFactory;
    $this->entityTypeManager = $entityTypeManager;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Implements hook_entity_type_build().
   *
   * @see \scheduled_transitions_entity_type_build()
   */
  public function entityTypeBuild(array &$entityTypes): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entityTypes */
    foreach ($entityTypes as $entityType) {
      if (!$entityType->hasLinkTemplate('canonical') || !$entityType instanceof ContentEntityType) {
        continue;
      }
      if ($entityType->id() === 'scheduled_transition') {
        continue;
      }

      // Add our entity route provider.
      $routeProviders = $entityType->getRouteProviderClasses() ?: [];
      $routeProviders['scheduled_transitions'] = ScheduledTransitionsRouteProvider::class;
      $entityType->setHandlerClass('route_provider', $routeProviders);

      $canonicalPath = $entityType->getLinkTemplate('canonical');
      $entityType
        ->setFormClass(ScheduledTransitionsRouteProvider::FORM, ScheduledTransitionForm::class)
        ->setLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE, $canonicalPath . ScheduledTransitionsRouteProvider::CANONICAL_PATH_SUFFIX);

      $entityType
        ->setFormClass(ScheduledTransitionsRouteProvider::FORM_ADD, ScheduledTransitionAddForm::class)
        ->setLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE_ADD, $canonicalPath . ScheduledTransitionsRouteProvider::CANONICAL_PATH_SUFFIX_ADD);
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * @see \scheduled_transitions_entity_delete()
   */
  public function entityDelete(EntityInterface $entity): void {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $transitionsForEntity = $this->loadByHostEntity($entity);
    $transitionStorage->delete($transitionsForEntity);
  }

  /**
   * Implements hook_entity_revision_delete().
   *
   * @see \scheduled_transitions_entity_revision_delete()
   */
  public function entityRevisionDelete(EntityInterface $entity): void {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $transitionsForEntity = $this->loadByHostEntity($entity, TRUE);
    $transitionStorage->delete($transitionsForEntity);
  }

  /**
   * Implements hook_entity_access().
   *
   * @see \scheduled_transitions_entity_access()
   */
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    // Determines if a user has access to view or add scheduled transitions for
    // an entity. Users must always have the entity:bundle permission. If the
    // mirror operation config is enabled then we allow via that. Otherwise if
    // the mirror operation is off some custom code provided by the site must
    // respond with allowed for
    // ScheduledTransitionsPermissions::ENTITY_OPERATION* operations.
    $access = AccessResult::neutral();

    if ($operation === Permissions::ENTITY_OPERATION_VIEW_TRANSITIONS) {
      $access->cachePerPermissions();
      $permission = Permissions::viewScheduledTransitionsPermission($entity->getEntityTypeId(), $entity->bundle());
      if ($account->hasPermission($permission)) {
        $access->addCacheTags([SettingsForm::SETTINGS_TAG]);
        $mirrorOperation = $this->mirrorOperations('view scheduled transition');
        if (isset($mirrorOperation)) {
          $access = $access->orIf($entity->access($mirrorOperation, $account, TRUE));
        }
      }
      else {
        $access = $access->andIf(AccessResult::forbidden("The '$permission' permission is required."));
      }
    }
    elseif ($operation === Permissions::ENTITY_OPERATION_ADD_TRANSITION) {
      $access->cachePerPermissions();
      $permission = Permissions::addScheduledTransitionsPermission($entity->getEntityTypeId(), $entity->bundle());
      if ($account->hasPermission($permission)) {
        $access->addCacheTags([SettingsForm::SETTINGS_TAG]);
        $mirrorOperation = $this->mirrorOperations('add scheduled transition');
        if (isset($mirrorOperation)) {
          $access = $access->orIf($entity->access($mirrorOperation, $account, TRUE));
        }
      }
      else {
        $access = $access->andIf(AccessResult::forbidden("The '$permission' permission is required."));
      }
    }

    if ($operation === Permissions::ENTITY_OPERATION_RESCHEDULE_TRANSITIONS) {
      $access->cachePerPermissions();
      $permission = Permissions::rescheduleScheduledTransitionsPermission($entity->getEntityTypeId(), $entity->bundle());
      if ($account->hasPermission($permission)) {
        $access->addCacheTags([SettingsForm::SETTINGS_TAG]);
        $mirrorOperation = $this->mirrorOperations('reschedule scheduled transitions');
        if (isset($mirrorOperation)) {
          $access = $access->orIf($entity->access($mirrorOperation, $account, TRUE));
        }
      }
      else {
        $access = $access->andIf(AccessResult::forbidden("The '$permission' permission is required."));
      }
    }

    return $access;
  }

  /**
   * Get the operation to mirror to, if enabled.
   *
   * @param string $operation
   *   An operation to mirror to.
   *
   * @return string|null
   *   An operation, or NULL if not enabled.
   */
  protected function mirrorOperations(string $operation): ?string {
    $mirrorOperation = $this->configFactory->get('scheduled_transitions.settings')
      ->get('mirror_operations.' . $operation);
    return is_string($mirrorOperation) ? $mirrorOperation : NULL;
  }

  /**
   * Load a list of scheduled transitions by host entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param bool $revision_match
   *   TRUE to match revision too.
   *
   * @return \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface[]
   *   A list of scheduled transitions for the given entity.
   */
  protected function loadByHostEntity(EntityInterface $entity, bool $revision_match = FALSE): array {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $query = $transitionStorage->getQuery()
      ->condition('entity.target_id', $entity->id())
      ->condition('entity.target_type', $entity->getEntityTypeId())
      ->accessCheck(FALSE);
    if ($revision_match) {
      $query->condition('entity_revision_id', $entity->getRevisionId());
    }
    if ($entity instanceof TranslatableInterface && !$entity->isDefaultTranslation()) {
      $query->condition('entity_revision_langcode', $entity->language()->getId());
    }
    $ids = $query->execute();
    return $transitionStorage->loadMultiple($ids);
  }

}
