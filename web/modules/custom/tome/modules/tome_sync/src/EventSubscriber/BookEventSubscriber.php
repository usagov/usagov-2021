<?php

namespace Drupal\tome_sync\EventSubscriber;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Event subscriber that keep book outlines in sync with content changes.
 *
 * The book module API isn't used for this because it seems incomplete and is
 * really difficult to work with.
 *
 * @internal
 */
class BookEventSubscriber implements EventSubscriberInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Creates a BookEventSubscriber object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * Exports all book outlines.
   */
  public function exportBookOutlines() {
    $storage = $this->entityTypeManager->getStorage('node');
    $results = $this->connection->select('book')
      ->fields('book')
      ->execute()
      ->fetchAll();
    $id_map = [];
    $rows = [];
    foreach ($results as $result) {
      $row = [];
      foreach ($result as $key => $value) {
        if (!$value || in_array($key, ['has_children', 'weight', 'depth'], TRUE)) {
          $row[$key] = $value;
        }
        else {
          if (!isset($id_map[$value]) && $node = $storage->load($value)) {
            $id_map[$value] = $node->uuid();
          }
          $row[$key] = $id_map[$value];
        }
      }
      $rows[] = $row;
    }
    $directory = $this->getExportDirectory();
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
    file_put_contents("$directory/book_outlines.json", json_encode($rows, JSON_PRETTY_PRINT));
  }

  /**
   * Imports all book outlines.
   */
  public function importBookOutlines() {
    $this->connection->truncate('book')->execute();

    $directory = $this->getExportDirectory();
    $storage = $this->entityTypeManager->getStorage('node');
    if (!file_exists("$directory/book_outlines.json")) {
      return;
    }
    $rows = json_decode(file_get_contents("$directory/book_outlines.json"), TRUE);
    $id_map = [];
    foreach ($rows as $row) {
      foreach ($row as $key => $value) {
        if (!$value || in_array($key, ['has_children', 'weight', 'depth'], TRUE)) {
          $row[$key] = $value;
        }
        else {
          if (!isset($id_map[$value]) && $results = $storage->loadByProperties(['uuid' => $value])) {
            $node = reset($results);
            $id_map[$value] = $node->id();
          }
          $row[$key] = $id_map[$value];
        }
      }
      $this->connection->insert('book')
        ->fields($row)
        ->execute();
    }
  }

  /**
   * Gets the export directory for book outlines.
   *
   * @return string
   *   The book export directory.
   */
  protected function getExportDirectory() {
    return Settings::get('tome_book_outline_directory', '../extra');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeSyncEvents::IMPORT_ALL][] = ['importBookOutlines'];
    $events[TomeSyncEvents::EXPORT_CONTENT][] = ['exportBookOutlines'];
    $events[TomeSyncEvents::EXPORT_ALL][] = ['exportBookOutlines'];
    return $events;
  }

}
