<?php

namespace Drupal\autosave_form\Form;

use Drupal\autosave_form\Ajax\OpenAutosaveDisabledDialog;
use Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for autosave form handlers.
 *
 * @ingroup entity_api
 */
class AutosaveEntityFormHandler implements AutosaveEntityFormHandlerInterface, EntityHandlerInterface {

  use DependencySerializationTrait;
  use AutosaveFormAlterTrait {
    autosaveFormAjax as traitAutosaveFormAjax;
    formAlter as traitFormAlter;
  }

  /**
   * The type of the entity for whose form the autosave form is used.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity storage.
   *
   * Will be used to load the unchanged entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The autosave form storage.
   *
   * @var \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface
   */
  protected $autosaveEntityFormStorage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The factory for expirable key value stores used by form cache.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirableFactory;

  /**
   * Defines whether the conflict module is enabled or not.
   *
   * @var bool
   */
  protected $conflictEnabled;

  /**
   * Initializes an instance of the autosave form controller.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface $autosave_entity_form_storage
   *   The autosave form storage service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $key_value_expirable_factory
   *   The key value expirable factory, used to create key value expirable
   *   stores for the form cache and form state cache.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, AutosaveEntityFormStorageInterface $autosave_entity_form_storage, TimeInterface $time, DateFormatterInterface $date_formatter, ConfigFactoryInterface $config_factory, KeyValueExpirableFactoryInterface $key_value_expirable_factory, ModuleHandlerInterface $module_handler) {
    $this->entityTypeId = $entity_type->id();
    $this->entityType = $entity_type;
    $this->entityStorage = $entity_type_manager->getStorage($entity_type->id());
    $this->currentUser = $current_user;
    $this->autosaveEntityFormStorage = $autosave_entity_form_storage;
    $this->time = $time;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
    $this->keyValueExpirableFactory = $key_value_expirable_factory;
    $this->conflictEnabled = $module_handler->moduleExists('conflict');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('autosave_form.entity_form_storage'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
      $container->get('keyvalue.expirable'),
      $container->get('module_handler')
    );
  }

  /**
   * Performs the needed alterations to the entity form.
   *
   * @param array $form
   *   The entity form to be altered to provide the autosave functionality.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function formAlter(array &$form, FormStateInterface $form_state) {
    $this->traitFormAlter($form, $form_state);
    // Flag the entity with the current autosave session ID.
    $form['#entity_builders'][] = [static::class, 'entityFormEntityBuild'];
  }

  /**
   * Ajax callback for autosaving content entity forms.
   */
  public function autosaveFormAjax($form, FormStateInterface $form_state) {
    $response = $this->traitAutosaveFormAjax($form, $form_state);

    $timestamp = $form_state->getTemporaryValue('autosave_form_last_autosave_timestamp');
    if ($timestamp == 'entity_saved_meanwhile') {
      $input = $form_state->getUserInput();
      // Autosave has run already or this is the first run.
      if (is_numeric($input['autosave_form_last_autosave_timestamp']) || empty($input['autosave_form_last_autosave_timestamp'])) {
        // Alert the user about turning off autosave and deleting all the
        // autosaved states.
        $message = $this->configFactory->get('autosave_form.messages')
          ->get('entity_saved_in_background_alert_message');

        $options = [
          'width' => 'auto',
          'closeOnEscape' => FALSE,
        ];
        $response->addCommand(new OpenAutosaveDisabledDialog($this->t('Autosave has been disabled'), $message, $options));

        // Reset the last autosaved timestamp on the page.
        $response->addCommand(new InvokeCommand('input[name="autosave_form_last_autosave_timestamp"]', 'attr', ['value', (string) $this->t('Autosave is turned off.')]));
        // Stop the autosave submissions.
        $attachments = [];
        $attachments['drupalSettings']['autosaveForm']['autosaveFormRunning'] = FALSE;
        $response->addAttachments($attachments);
      }
    }

    return $response;
  }

  /**
   * Entity builder method.
   *
   * Flags the entity with the autosave form session ID.
   *
   * @param string $entity_type
   *   The type of the entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose form is being built.
   */
  public static function entityFormEntityBuild($entity_type, EntityInterface $entity, array $form, FormStateInterface $form_state) {
    if ($autosave_form_session_id = static::getAutosaveFormSessionID($form_state)) {
      $entity->{static::AUTOSAVE_SESSION_ID} = $autosave_form_session_id;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isAutosaveSubmitValid(FormStateInterface $form_state) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);

    // Check that the entity is still valid.
    if ($entity instanceof EntityChangedInterface) {
      $changed_time = $entity->getChangedTime();

      $input = $form_state->getUserInput();
      $changed_form_value = isset($input['changed']) ? $input['changed'] : NULL;
      $entity->setChangedTime($changed_form_value ?: $changed_time);

      if (!$this->conflictEnabled && ($unchanged = $this->entityStorage->loadUnchanged($entity->id())) && ($unchanged->getChangedTimeAcrossTranslations() > $entity->getChangedTimeAcrossTranslations())) {
        $form_state->setTemporaryValue('autosave_form_last_autosave_timestamp', 'entity_saved_meanwhile');
        return FALSE;
      }
      else {
        $entity->setChangedTime($changed_time);
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAutosavedFormState(FormStateInterface $form_state, $autosave_form_session_id, $uid) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    return $this->autosaveEntityFormStorage->getFormState($form_id, $entity->getEntityTypeId(), $entity->id(), $entity->language()->getId(), $uid, $autosave_form_session_id);
  }

  /**
   * {@inheritdoc}
   */
  public function storeState(FormStateInterface $form_state, $autosave_form_session_id, $autosave_timestamp, $uid) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    $this->autosaveEntityFormStorage->storeEntityAndFormState($form_id, $autosave_form_session_id, $entity->getEntityTypeId(), $entity->id(), $entity->language()->getId(), $uid, $autosave_timestamp, $entity, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAutosavedTimestamp(FormStateInterface $form_state, $uid) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    return $entity->isNew() ? NULL : $this->autosaveEntityFormStorage->getLastAutosavedStateTimestamp($form_id, $entity->getEntityTypeId(), $entity->id(), $entity->language()->getId(), $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeCurrentAutosavedState(FormStateInterface $form_state, $uid) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    $this->autosaveEntityFormStorage->purgeAutosavedEntityState($entity->getEntityTypeId(), $entity->id(), $this->getAutosaveFormSessionID($form_state), $form_id, $entity->language()->getId(), $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function purgeAllAutosavedStates(FormStateInterface $form_state, $uid) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    $this->autosaveEntityFormStorage->purgeAutosavedEntityState($entity->getEntityTypeId(), $entity->id(), NULL, $form_id, $entity->language()->getId(), $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function isAutosaveEnabled(FormStateInterface $form_state) {
    list($form_id, $entity) = $this->getFormIDandEntity($form_state);
    $allowed = !$entity->isNew() && !$this->currentUser->isAnonymous();
    return $allowed;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAutosaveSessionID(EntityInterface $entity) {
    if (isset($entity->{static::AUTOSAVE_SESSION_ID})) {
      return $entity->{static::AUTOSAVE_SESSION_ID};
    }
    else {
      return NULL;
    }
  }

  /**
   * Retrieves the form ID and the form entity object from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The first value is the form ID and the second the form entity object.
   */
  protected function getFormIDandEntity(FormStateInterface $form_state) {
    /** @var EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $form_id = $form_object->getFormId();
    $entity = $form_object->getEntity();
    return [$form_id, $entity];
  }

}
