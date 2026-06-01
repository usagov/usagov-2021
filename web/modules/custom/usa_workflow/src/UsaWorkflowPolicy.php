<?php

namespace Drupal\usa_workflow;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\TranslatableRevisionableStorageInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\EntityOwnerInterface;
use Drupal\workflows\StateInterface;

/**
 * Central policy logic for USA workflow restrictions.
 */
class UsaWorkflowPolicy {
  use StringTranslationTrait;

  public const OPERATION_PUBLISH = 'publish';
  public const OPERATION_DELETE = 'delete';

  public function __construct(
    private ConfigFactoryInterface $configFactory,
    private ModerationInformationInterface $moderationInformation,
    private EntityTypeManagerInterface $entityTypeManager,
    private EntityTypeBundleInfoInterface $bundleInfo,
  ) {}

  /**
   * Returns whether the module is enabled and active for an operation.
   */
  public function isOperationEnabled(string $operation): bool {
    if (!$this->getSettings()->get('enabled')) {
      return FALSE;
    }

    return match ($operation) {
      self::OPERATION_PUBLISH => (bool) $this->getSettings()->get('restrict_self_publish'),
      self::OPERATION_DELETE => (bool) $this->getSettings()->get('restrict_self_delete'),
      default => FALSE,
    };
  }

  /**
   * Returns whether an entity is in scope for a restriction.
   */
  public function isEntityInScope(ContentEntityInterface $entity, string $operation): bool {
    if (!$this->isOperationEnabled($operation)) {
      return FALSE;
    }

    if (!$this->moderationInformation->isModeratedEntity($entity)) {
      return FALSE;
    }

    return !$this->isBundleExcluded($entity, $operation);
  }

  /**
   * Returns whether the publish restriction applies to this action.
   */
  public function isSelfPublishRestricted(
    ContentEntityInterface $entity,
    AccountInterface $account,
    ?StateInterface $targetState = NULL,
  ): bool {
    if (!$this->isEntityInScope($entity, self::OPERATION_PUBLISH)) {
      return FALSE;
    }

    if ($account->hasPermission('usa approve own content')) {
      return FALSE;
    }

    if ($targetState && !$this->isPublishedState($targetState)) {
      return FALSE;
    }

    return $this->isLatestRevisionAuthor($entity, $account);
  }

  /**
   * Returns whether the delete restriction applies to this action.
   */
  public function isSelfDeleteRestricted(
    ContentEntityInterface $entity,
    AccountInterface $account,
  ): bool {
    if (!$this->isEntityInScope($entity, self::OPERATION_DELETE)) {
      return FALSE;
    }

    if ($account->hasPermission('usa delete own content')) {
      return FALSE;
    }

    return $this->isLatestRevisionAuthor($entity, $account);
  }

  /**
   * Returns bundle options for the settings form.
   *
   * @return array<string, array{title: string, options: array<string, string>}>
   *   Grouped checkbox options keyed by entity type ID.
   */
  public function getConfigurableBundleOptions(): array {
    $options = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$this->moderationInformation->canModerateEntitiesOfEntityType($entity_type)) {
        continue;
      }

      $bundles = [];
      foreach ($this->bundleInfo->getBundleInfo($entity_type_id) as $bundle_id => $bundle_info) {
        if (empty($bundle_info['workflow'])) {
          continue;
        }

        $bundles[$this->buildBundleKey($entity_type_id, $bundle_id)] = (string) $this->t('@label (@id)', [
          '@label' => $bundle_info['label'] ?? $bundle_id,
          '@id' => $bundle_id,
        ]);
      }

      if ($bundles) {
        asort($bundles);
        $label = (string) ($entity_type->getCollectionLabel() ?: $entity_type->getLabel() ?: $entity_type_id);
        $options[$entity_type_id] = [
          'title' => $label,
          'options' => $bundles,
        ];
      }
    }

    ksort($options);
    return $options;
  }

  /**
   * Returns the current content entity from a route match, if any.
   */
  public function getContentEntityFromRoute(RouteMatchInterface $routeMatch): ?ContentEntityInterface {
    foreach ($routeMatch->getParameters()->all() as $parameter) {
      if ($parameter instanceof ContentEntityInterface) {
        return $parameter;
      }
    }

    return NULL;
  }

  /**
   * Returns TRUE when the target state is a published state.
   */
  public function isPublishedState(?StateInterface $state): bool {
    return $state !== NULL
      && method_exists($state, 'isPublishedState')
      && $state->isPublishedState();
  }

  /**
   * Returns TRUE when the account owns the latest revision.
   */
  public function isLatestRevisionAuthor(ContentEntityInterface $entity, AccountInterface $account): bool {
    if (!$account->id()) {
      return FALSE;
    }

    $latest_revision_author = $this->getLatestRevisionAuthorId($entity, $account);
    return $latest_revision_author !== NULL && (string) $latest_revision_author === (string) $account->id();
  }

  /**
   * Builds a config key for an entity type and bundle.
   */
  public function buildBundleKey(string $entityTypeId, string $bundleId): string {
    return $entityTypeId . ':' . $bundleId;
  }

  /**
   * Returns the active module settings.
   */
  public function getSettings(): ImmutableConfig {
    return $this->configFactory->get('usa_workflow.settings');
  }

  /**
   * Returns TRUE when the bundle is excluded for an operation.
   */
  protected function isBundleExcluded(ContentEntityInterface $entity, string $operation): bool {
    $config_key = match ($operation) {
      self::OPERATION_PUBLISH => 'excluded_publish_bundles',
      self::OPERATION_DELETE => 'excluded_delete_bundles',
      default => NULL,
    };

    if (!$config_key) {
      return FALSE;
    }

    $excluded = $this->getSettings()->get($config_key) ?? [];
    return in_array($this->buildBundleKey($entity->getEntityTypeId(), $entity->bundle()), $excluded, TRUE);
  }

  /**
   * Returns the latest revision author ID for an entity when available.
   */
  protected function getLatestRevisionAuthorId(
    ContentEntityInterface $entity,
    ?AccountInterface $fallbackAccount = NULL,
  ): ?int {
    if ($entity->isNew()) {
      return $fallbackAccount?->id() ? (int) $fallbackAccount->id() : NULL;
    }

    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if (!$storage instanceof RevisionableStorageInterface) {
      return $this->extractRevisionAuthorId($entity) ?? $this->extractOwnerId($entity);
    }

    $revision_id = NULL;
    if ($storage instanceof TranslatableRevisionableStorageInterface) {
      $revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $entity->language()->getId());
    }
    $revision_id ??= $storage->getLatestRevisionId($entity->id());

    if (!$revision_id) {
      return $this->extractRevisionAuthorId($entity) ?? $this->extractOwnerId($entity);
    }

    $latest_revision = $storage->loadRevision($revision_id);
    if (!$latest_revision instanceof ContentEntityInterface) {
      return $this->extractRevisionAuthorId($entity) ?? $this->extractOwnerId($entity);
    }

    if (!$entity->isDefaultTranslation()
      && $latest_revision->hasTranslation($entity->language()->getId())) {
      $latest_revision = $latest_revision->getTranslation($entity->language()->getId());
    }

    return $this->extractRevisionAuthorId($latest_revision) ?? $this->extractOwnerId($latest_revision);
  }

  /**
   * Returns the revision author ID for an entity when available.
   */
  protected function extractRevisionAuthorId(ContentEntityInterface $entity): ?int {
    if ($entity instanceof RevisionLogInterface) {
      $revision_user_id = $entity->getRevisionUserId();
      return $revision_user_id !== NULL ? (int) $revision_user_id : NULL;
    }

    $revision_user_key = $entity->getEntityType()->getKey('revision_user');
    if ($revision_user_key
      && $entity->hasField($revision_user_key)
      && !$entity->get($revision_user_key)->isEmpty()) {
      $field = $entity->get($revision_user_key);
      $value = $field->target_id ?? $field->value ?? NULL;
      return $value !== NULL ? (int) $value : NULL;
    }

    return NULL;
  }

  /**
   * Returns the owner ID for an entity when available.
   */
  protected function extractOwnerId(ContentEntityInterface $entity): ?int {
    if ($entity instanceof EntityOwnerInterface) {
      $owner_id = $entity->getOwnerId();
      return $owner_id !== NULL ? (int) $owner_id : NULL;
    }

    return NULL;
  }

}
