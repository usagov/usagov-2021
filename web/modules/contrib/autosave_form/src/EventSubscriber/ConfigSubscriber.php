<?php

namespace Drupal\autosave_form\EventSubscriber;

use Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Purges autosave states on configuration changes.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The autosave form storage.
   *
   * @var \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface
   */
  protected $autosaveEntityFormStorage;

  /**
   * Constructs a new class object.
   *
   * @param \Drupal\autosave_form\Storage\AutosaveEntityFormStorageInterface $autosave_entity_form_storage
   *   The autosave entity form storage.
   */
  public function __construct(AutosaveEntityFormStorageInterface $autosave_entity_form_storage) {
    $this->autosaveEntityFormStorage = $autosave_entity_form_storage;
  }

  /**
   * Purges all autosave states in case a form-related config is saved.
   *
   * Deletes all autosaved states if an important property of a form related
   * config is changed. We cannot know if some kind of an inline reference has
   * been used and its display has been changed, which is why we purge the whole
   * autosave storage. Otherwise a much more complex detection mechanism will
   * be needed. We though try to prevent the purging in case a change on an
   * property is made, which doesn't influence the form functionality - e.g. if
   * the label of a field is changed then we don't have to purge the autosave
   * states.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    $saved_config = $event->getConfig();
    $name = $saved_config->getName();

    // We try to prevent purging autosave states unnecessarily by defining
    // properties, which are allowed to change and don't have an influence on
    // the form functionality.
    $allowed_changes = [];
    $purge_autosave_states = TRUE;
    if (strpos($name, 'field.field.') === 0) {
      $allowed_changes = [
        'dependencies',
        'label',
        'description',
        'required',
        'default_value',
        'default_value_callback',
      ];
    }
    elseif (strpos($name, 'field.storage.') === 0) {
      $allowed_changes = [
        'dependencies',
        'module',
        'indexes',
        'persist_with_no_fields',
        'custom_storage',
      ];
    }
    elseif (strpos($name, 'core.entity_form_display.') === 0) {
      $allowed_changes = [
        'hidden',
      ];
    }
    elseif (strpos($name, 'user.role.') === 0) {
      $allowed_changes = [
        'label',
        'weight',
      ];
    }
    else {
      $purge_autosave_states = FALSE;
    }

    if ($purge_autosave_states) {
      $property_names = array_diff(array_keys($saved_config->getRawData()), $allowed_changes);
      foreach ($property_names as $property_name) {
        if ($event->isChanged($property_name)) {
          $this->autosaveEntityFormStorage->purgeAutosavedEntitiesStates();
          break;
        }
      }
    }
  }

  /**
   * Purges all autosave states in case a form-related config is deleted.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigDelete(ConfigCrudEvent $event) {
    $deleted_config = $event->getConfig();
    $name = $deleted_config->getName();

    $purge_autosave_states = (strpos($name, 'field.field.') === 0) ||
      (strpos($name, 'field.storage.') === 0) ||
      (strpos($name, 'core.entity_form_display.') === 0) ||
      (strpos($name, 'user.role.') === 0);

    if ($purge_autosave_states) {
      $this->autosaveEntityFormStorage->purgeAutosavedEntitiesStates();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onConfigSave'];
    $events[ConfigEvents::DELETE][] = ['onConfigDelete'];
    return $events;
  }

}
