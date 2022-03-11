<?php

namespace Drupal\tome_sync\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigRenameEvent;
use Drupal\Core\Config\StorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the config export directory synced with config CRUD operations.
 *
 * @internal
 */
class ConfigEventSubscriber implements EventSubscriberInterface {

  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * Constructs the ConfigEventSubscriber object.
   *
   * @param \Drupal\Core\Config\StorageInterface $config_storage
   *   The config storage.
   */
  public function __construct(StorageInterface $config_storage) {
    $this->configStorage = $config_storage;
  }

  /**
   * Reacts to a save event.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function configSave(ConfigCrudEvent $event) {
    if (!\Drupal::isConfigSyncing() && !isset($GLOBALS['_tome_sync_installing'])) {
      $config = $event->getConfig();
      $this->configStorage->write($config->getName(), $config->getRawData());
    }
  }

  /**
   * Reacts to delete event.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function configDelete(ConfigCrudEvent $event) {
    if (!\Drupal::isConfigSyncing() && !isset($GLOBALS['_tome_sync_installing'])) {
      $this->configStorage->delete($event->getConfig()->getName());
    }
  }

  /**
   * Reacts to rename event.
   *
   * @param \Drupal\Core\Config\ConfigRenameEvent $event
   *   The configuration event.
   */
  public function configRename(ConfigRenameEvent $event) {
    if (!\Drupal::isConfigSyncing() && !isset($GLOBALS['_tome_sync_installing'])) {
      $this->configStorage->rename($event->getOldName(), $event->getConfig()->getName());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['configSave'];
    $events[ConfigEvents::DELETE][] = ['configDelete'];
    $events[ConfigEvents::RENAME][] = ['configRename'];
    return $events;
  }

}
