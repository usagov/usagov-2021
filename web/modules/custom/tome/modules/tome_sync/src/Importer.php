<?php

namespace Drupal\tome_sync;

use Drupal\Component\Graph\Graph;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\tome_sync\Event\ContentCrudEvent;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\File\FileSystemInterface;

/**
 * Handles importing of content and file entities.
 *
 * @internal
 */
class Importer implements ImporterInterface {

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
   * Are entities being created as part of an import.
   *
   * @var bool
   */
  protected $isImporting;

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
   * Creates an Importer object.
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
    $this->isImporting = FALSE;
    $this->eventDispatcher = $event_dispatcher;
    $this->accountSwitcher = $account_switcher;
    $this->fileSync = $file_sync;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function getChunkedNames() {
    $graph = [];
    $index = $this->getContentIndex();
    if (!$index) {
      throw new \Exception('No index file was found. Check that the content export directory is writable and that content JSON is in the directory.');
    }
    $names = $this->contentStorage->listAll();
    foreach ($index as $name => $edges) {
      if (!in_array($name, $names, TRUE)) {
        continue;
      }
      $graph[$name]['edges'] = [];
      foreach ($edges as $edge) {
        if (in_array($edge, $names, TRUE)) {
          $graph[$name]['edges'][$edge] = TRUE;
        }
      }
    }
    $graph_object = new Graph($graph);
    $graph = $graph_object->searchAndSort();
    uasort($graph, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    $graph = array_reverse($graph);
    // Now we need to chunk the graph into parts we can do concurrently.
    // This is overkill for small sites, but for large migrations we need to
    // import as quickly as possible.
    $all_imported = [];
    $chunked_graph = [];
    while ($graph) {
      $chunk = [];
      foreach ($graph as $i => $node) {
        $edges = array_keys($node['edges']);
        if (count(array_intersect($edges, $all_imported)) === count($edges)) {
          $chunk[] = $i;
          unset($graph[$i]);
        }
      }
      $all_imported = array_merge($all_imported, $chunk);
      if (empty($chunk)) {
        throw new \Exception('Unable to build the content graph, probably due to circular dependencies. Here is the list of entities to review: ' . implode(', ', array_keys($graph)));
      }
      $chunked_graph[] = $chunk;
    }
    return $chunked_graph;
  }

  /**
   * {@inheritdoc}
   */
  public function importFiles() {
    $this->fileSync->importFiles();
  }

  /**
   * {@inheritdoc}
   */
  public function importContent($entity_type_id, $uuid, $langcode = NULL) {
    $this->switchToAdmin();
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $results = $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties([
      $entity_type->getKey('uuid') => $uuid,
    ]);
    $this->isImporting(TRUE);
    $imported_entity = NULL;
    if ($langcode && $results) {
      $original_entity = reset($results);
      if ($original_entity instanceof ContentEntityInterface) {
        $translation = $this->loadEntityFromStorage($entity_type, $uuid, $langcode);
        if ($original_entity->hasTranslation($langcode)) {
          $original_translation = $original_entity->getTranslation($langcode);
          $this->copyFieldValues($translation, $original_translation);
          $original_translation->save();
        }
        else {
          $original_entity->addTranslation($langcode, $translation->toArray());
          $original_entity->save();
        }
        $imported_entity = $original_entity->getTranslation($langcode);
      }
    }
    else {
      $entity = $this->loadEntityFromStorage($entity_type, $uuid);
      if (!empty($results)) {
        $original_entity = reset($results);
        $this->copyFieldValues($entity, $original_entity);
        $original_entity->save();
        $imported_entity = $original_entity;
      }
      else {
        $entity->enforceIsNew();
        $entity->save();
        $imported_entity = $entity;
      }
    }
    $this->isImporting(FALSE);
    if (isset($imported_entity)) {
      $event = new ContentCrudEvent($imported_entity);
      $this->eventDispatcher->dispatch(TomeSyncEvents::IMPORT_CONTENT, $event);
    }
    $this->switchBack();
  }

  /**
   * Copies values from a denormalized entity to the original entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The denormalized entity.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $original_entity
   *   The original entity.
   *
   * @see \Drupal\rest\Plugin\rest\resource\EntityResource::patch
   */
  protected function copyFieldValues(FieldableEntityInterface $entity, FieldableEntityInterface $original_entity) {
    foreach ($entity->_tomeFields as $field_name) {
      $field = $entity->get($field_name);
      if ($entity->getEntityType()->hasKey('langcode') && $field_name === $entity->getEntityType()->getKey('langcode') && $field->isEmpty()) {
        continue;
      }
      $original_entity->set($field_name, $field->getValue());
    }
  }

  /**
   * Loads an entity from the content storage.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type of this entity.
   * @param string $uuid
   *   The entity UUID.
   * @param string $langcode
   *   (optional) The langcode, for translations.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The loaded entity.
   */
  protected function loadEntityFromStorage(EntityTypeInterface $entity_type, $uuid, $langcode = NULL) {
    $contents = $this->contentStorage->read(TomeSyncHelper::getContentNameFromParts($entity_type->id(), $uuid, $langcode));
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->serializer->denormalize($contents, $entity_type->getClass(), 'json');
    $entity->_tomeFields = array_keys($contents);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isImporting($importing = NULL) {
    if (is_bool($importing)) {
      $this->isImporting = $importing;
    }
    return $this->isImporting;
  }

}
