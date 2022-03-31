<?php

namespace Drupal\tome_sync\EventSubscriber;

use Drupal\Core\Config\FileStorage;
use Drupal\tome_sync\ContentHasherInterface;
use Drupal\tome_sync\Event\ContentCrudEvent;
use Drupal\tome_sync\Event\TomeSyncEvents;
use Drupal\tome_sync\TomeSyncHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that keeps the content hash table up to date.
 *
 * @internal
 */
class ContentHasherEventSubscriber implements EventSubscriberInterface {

  /**
   * The content hasher.
   *
   * @var \Drupal\tome_sync\ContentHasherInterface
   */
  protected $contentHasher;

  /**
   * The target content storage.
   *
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $contentStorage;

  /**
   * Creates a ContentHasherEventSubscriber object.
   *
   * @param \Drupal\tome_sync\ContentHasherInterface $content_hasher
   *   The content hasher.
   * @param \Drupal\Core\Config\FileStorage $content_storage
   *   The target content storage.
   */
  public function __construct(ContentHasherInterface $content_hasher, FileStorage $content_storage) {
    $this->contentHasher = $content_hasher;
    $this->contentStorage = $content_storage;
  }

  /**
   * Maintains a hash of imported content to support partial imports.
   *
   * @param \Drupal\tome_sync\Event\ContentCrudEvent $event
   *   The content CRUD event.
   */
  public function writeHash(ContentCrudEvent $event) {
    $entity = $event->getContent();
    $content_name = TomeSyncHelper::getContentName($entity);
    $file_path = $this->contentStorage->getFilePath($content_name);
    if (file_exists($file_path)) {
      $encoded_content = file_get_contents($file_path);
      $this->contentHasher->writeHash($encoded_content, $content_name);
    }
  }

  /**
   * Maintains a hash of exported content to support partial imports.
   *
   * @param \Drupal\tome_sync\Event\ContentCrudEvent $event
   *   The content CRUD event.
   */
  public function writeSourceHash(ContentCrudEvent $event) {
    $entity = $event->getContent();
    $content_name = TomeSyncHelper::getContentName($entity);
    $file_path = $this->contentStorage->getFilePath($content_name);
    if (file_exists($file_path)) {
      $encoded_content = file_get_contents($file_path);
      $this->contentHasher->writeHash($encoded_content, $content_name);
    }
  }

  /**
   * Maintains a hash of imported content to support partial imports.
   *
   * @param \Drupal\tome_sync\Event\ContentCrudEvent $event
   *   The content CRUD event.
   */
  public function deleteHash(ContentCrudEvent $event) {
    $entity = $event->getContent();
    $content_name = TomeSyncHelper::getContentName($entity);
    $this->contentHasher->deleteHash($content_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TomeSyncEvents::IMPORT_CONTENT][] = ['writeHash'];
    $events[TomeSyncEvents::EXPORT_CONTENT][] = ['writeSourceHash'];
    $events[TomeSyncEvents::DELETE_CONTENT][] = ['deleteHash'];
    return $events;
  }

}
