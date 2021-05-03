<?php

namespace Drupal\content_lock\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class acts on config save event.
 */
class SettingsSaveEventSubscriber implements EventSubscriberInterface {

  protected $entityTypeManager;

  /**
   * SettingsSaveEventSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * On config save.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The save event.
   */
  public function onSave(ConfigCrudEvent $event) {

    if ($event->getConfig()->getName() == 'content_lock.settings') {

      foreach (array_filter($event->getConfig()->get('types')) as $type => $value) {
        // Skip if the entity type does not exist.
        if (!$this->entityTypeManager->getDefinition($type, FALSE)) {
          continue;
        }

        // Create an action config for all activated entity types.
        $action = $this->entityTypeManager->getStorage('action')->loadByProperties([
          'plugin' => 'entity:break_lock:' . $type,
        ]);
        if (empty($action)) {
          $action = $this->entityTypeManager->getStorage('action')->create([
            'id' => $type . '_break_lock_action',
            'label' => 'Break lock ' . $type,
            'plugin' => 'entity:break_lock:' . $type,
            'type' => $type,
          ]);
          $action->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
