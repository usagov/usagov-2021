<?php

namespace Drupal\tome_sync;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\file\FileInterface;
use Drupal\tome_base\PathTrait;
use Drupal\tome_sync\Event\ContentCrudEvent;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\File\FileSystemInterface;

/**
 * Handles exporting of content and file entities.
 *
 * @internal
 */
class Exporter implements ExporterInterface {

  use PathTrait;
  use ContentIndexerTrait;
  use AccountSwitcherTrait;

  /**
   * The target content storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $contentStorage;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file sync service.
   *
   * @var \Drupal\tome_sync\FileSyncInterface
   */
  protected $fileSync;

  /**
   * An array of excluded entity types.
   *
   * @var string[]
   */
  protected static $excludedTypes = [
    'content_moderation_state',
  ];

  /**
   * Creates an Exporter object.
   *
   * @param \Drupal\Core\Config\StorageInterface $content_storage
   *   The target content storage.
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   * @param \Drupal\tome_sync\FileSyncInterface $file_sync
   *   The file sync service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(StorageInterface $content_storage, Serializer $serializer, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, AccountSwitcherInterface $account_switcher, FileSyncInterface $file_sync, FileSystemInterface $file_system) {
    $this->contentStorage = $content_storage;
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->accountSwitcher = $account_switcher;
    $this->fileSync = $file_sync;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function getContentToExport() {
    $entities = [];
    $definitions = array_diff_key($this->entityTypeManager->getDefinitions(), array_flip(self::$excludedTypes));
    foreach ($definitions as $entity_type) {
      if (is_a($entity_type->getClass(), '\Drupal\Core\Entity\ContentEntityInterface', TRUE)) {
        $storage = $this->entityTypeManager->getStorage($entity_type->id());
        $entities[$entity_type->id()] = $storage->getQuery()->execute();
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteExportDirectories() {
    $this->contentStorage->deleteAll();
    $this->deleteContentIndex();
    if (!$this->fileSync->deleteExportDirectory()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function exportContent(ContentEntityInterface $entity) {
    if (in_array($entity->getEntityTypeId(), self::$excludedTypes, TRUE)) {
      return;
    }
    $this->switchToAdmin();
    $data = $this->serializer->normalize($entity, 'json');
    $this->contentStorage->write(TomeSyncHelper::getContentName($entity), $data);
    $this->indexContent($entity);
    if ($entity instanceof FileInterface) {
      $this->fileSync->exportFile($entity);
    }
    $event = new ContentCrudEvent($entity);
    $this->eventDispatcher->dispatch(TomeSyncEvents::EXPORT_CONTENT, $event);
    $this->switchBack();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteContentExport(ContentEntityInterface $entity) {
    // It would be cool if hook_entity_translation_delete() is invoked for
    // every translation of an entity when it's deleted. But it isn't. :-(.
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $this->contentStorage->delete(TomeSyncHelper::getContentName($entity->getTranslation($langcode)));
      $this->unIndexContent($entity);
    }
    if ($entity instanceof FileInterface) {
      $this->fileSync->deleteFileExport($entity);
    }
    $event = new ContentCrudEvent($entity);
    $this->eventDispatcher->dispatch(TomeSyncEvents::DELETE_CONTENT, $event);
  }

}
