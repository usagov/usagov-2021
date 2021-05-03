<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Entity;

use DateTimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\ScheduledTransitionsUtility;
use Drupal\user\UserInterface;
use Drupal\workflows\WorkflowInterface;

/**
 * Scheduled Transition entity.
 *
 * @ContentEntityType(
 *   id = "scheduled_transition",
 *   label = @Translation("scheduled transition"),
 *   label_singular = @Translation("Scheduled transition"),
 *   label_plural = @Translation("scheduled transitions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count scheduled transition",
 *     plural = "@count scheduled transitions"
 *   ),
 *   label_collection = @Translation("Scheduled transitions"),
 *   base_table = "scheduled_transition",
 *   data_table = "scheduled_transition",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "author",
 *   },
 *   internal = TRUE,
 *   admin_permission = "view all scheduled transitions",
 *   handlers = {
 *     "access" = "Drupal\scheduled_transitions\ScheduledTransitionsAccessControlHandler",
 *     "list_builder" = "Drupal\scheduled_transitions\ScheduledTransitionsListBuilder",
 *     "views_data" = "Drupal\scheduled_transitions\ScheduledTransitionsViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "reschedule" = "Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionRescheduleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\scheduled_transitions\Routing\ScheduledTransitionRouteProvider",
 *     }
 *   },
 *   links = {
 *     "collection" = "/admin/content/scheduled-transitions",
 *     "delete-form" = "/admin/scheduled-transition/{scheduled_transition}/delete",
 *     "reschedule-form" = "/admin/scheduled-transition/{scheduled_transition}/reschedule",
 *   },
 * )
 */
class ScheduledTransition extends ContentEntityBase implements ScheduledTransitionInterface {

  /**
   * Option to recreate non default head.
   */
  public const OPTION_RECREATE_NON_DEFAULT_HEAD = 'recreate_non_default_head';

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['entity'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(\t('Entity'))
      ->setDescription(\t('The entity this scheduled transition is for.'))
      ->setRequired(TRUE)
      ->setCardinality(1);

    // Only supports entities with integer IDs.
    $fields['entity_revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(\t('Content entity revision ID'))
      ->setDescription(\t('The revision ID of the entity this scheduled transition is for.'))
      ->setRequired(TRUE)
      ->setCardinality(1);

    $fields['entity_revision_langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(\t('Content entity revision language'))
      ->setDescription(\t('The revision language of the entity this scheduled transition is for.'))
      ->setRequired(FALSE)
      ->setCardinality(1);

    $fields['author'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\t('Authored by'))
      ->setDescription(\t('The user who initiated the scheduled transition.'))
      ->setSetting('target_type', 'user');

    // Workflow is recorded so scheduled transitions can be cleaned up if they
    // are deleted. Similar to 'bundle' column on field tables.
    $fields['workflow'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(\t('Workflow'))
      ->setDescription(\t('The workflow of the state.'))
      ->setSetting('target_type', 'workflow')
      ->setRequired(TRUE);

    $fields['moderation_state'] = BaseFieldDefinition::create('string')
      ->setLabel(\t('Moderation state'))
      ->setDescription(\t('The new state of the content.'))
      ->setRequired(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(\t('Authored on'))
      ->setDescription(\t('The time that the scheduled transition was created.'));

    $fields['transition_on'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(\t('Transition on'))
      ->setDescription(\t('The time scheduled transition should happen.'))
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ]);

    $fields['locked_on'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(\t('Locked on'))
      ->setDescription(\t('The time a job was created to process the transition.'));

    $fields['options'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Options'))
      ->setDescription(t('Arbitrary settings for scheduled transition.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function createFrom(WorkflowInterface $workflow, string $state, RevisionableInterface $revision, DateTimeInterface $dateTime, AccountInterface $author) {
    /** @var static $scheduledTransition */
    $scheduledTransition = static::create();
    $scheduledTransition
      ->setState($workflow, $state)
      ->setEntity($revision)
      ->setTransitionDate($dateTime)
      ->setAuthor($author);
    return $scheduledTransition;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ?EntityInterface {
    return $this->get('entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(RevisionableInterface $revision) {
    $this->set('entity', $revision);
    $this->setEntityRevisionId($revision->getRevisionId());
    $this->setEntityRevisionLanguage($revision->language()->getId());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityRevisionId() {
    return $this->get('entity_revision_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityRevisionId($revisionId) {
    assert(is_int($revisionId) || is_string($revisionId));
    $this->set('entity_revision_id', $revisionId);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityRevisionLanguage(): ?string {
    return $this->get('entity_revision_langcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityRevisionLanguage(string $langCode) {
    $this->entity_revision_langcode = $langCode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor(): ?UserInterface {
    return $this->get('author')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthor(AccountInterface $author) {
    $this->author = $author->id();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflow(): ?WorkflowInterface {
    return $this->get('workflow')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setState(WorkflowInterface $workflow, string $state) {
    $this->workflow = $workflow->id();
    $this->moderation_state = $state;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): string {
    return $this->get('moderation_state')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionDate(): \DateTimeInterface {
    return new \DateTime('@' . $this->getTransitionTime());
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitionTime(): int {
    return (int) $this->get('transition_on')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTransitionDate(DateTimeInterface $dateTime) {
    $this->set('transition_on', $dateTime->getTimestamp());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTransitionTime(int $time) {
    $this->set('transition_on', $time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setLockedOn(int $time) {
    $this->set('locked_on', $time);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions(): array {
    $options = $this->get('options')->first();
    if (isset($options)) {
      return $options->getValue();
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate(): array {
    $tags = parent::getCacheTagsToInvalidate();
    $entity = $this->getEntity();
    if ($entity) {
      $tags[] = ScheduledTransitionsUtility::createScheduledTransitionsCacheTag($entity);
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    parent::invalidateTagsOnSave($update);
    $entity = $this->getEntity();
    if ($entity) {
      $tags[] = ScheduledTransitionsUtility::createScheduledTransitionsCacheTag($entity);
    }
    Cache::invalidateTags($tags);
  }

}
