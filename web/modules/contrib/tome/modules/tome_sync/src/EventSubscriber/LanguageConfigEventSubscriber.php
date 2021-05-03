<?php

namespace Drupal\tome_sync\EventSubscriber;

use Drupal\Core\Config\StorageInterface;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Keeps the config export directory synced with language config operations.
 *
 * @internal
 */
class LanguageConfigEventSubscriber implements EventSubscriberInterface {

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
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The configuration event.
   */
  public function configSave(LanguageConfigOverrideCrudEvent $event) {
    if (!\Drupal::isConfigSyncing()) {
      $override = $event->getLanguageConfigOverride();
      $collection = $this->configStorage->createCollection($override->getStorage()->getCollectionName());
      $name = $override->getName();
      $collection->write($name, $override->get());
    }
  }

  /**
   * Reacts to delete event.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The configuration event.
   */
  public function configDelete(LanguageConfigOverrideCrudEvent $event) {
    if (!\Drupal::isConfigSyncing()) {
      $override = $event->getLanguageConfigOverride();
      $collection = $this->configStorage->createCollection($override->getStorage()->getCollectionName());
      $name = $override->getName();
      $collection->delete($name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LanguageConfigOverrideEvents::SAVE_OVERRIDE][] = ['configSave'];
    $events[LanguageConfigOverrideEvents::DELETE_OVERRIDE][] = ['configDelete'];
    return $events;
  }

}
