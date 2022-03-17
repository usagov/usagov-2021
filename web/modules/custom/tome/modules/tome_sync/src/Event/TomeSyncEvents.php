<?php

namespace Drupal\tome_sync\Event;

/**
 * Defines events for Tome Sync.
 */
final class TomeSyncEvents {

  /**
   * Name of the event fired after a single content entity is exported.
   *
   * @Event
   *
   * @see \Drupal\tome_sync\Event\ContentCrudEvent
   *
   * @var string
   */
  const EXPORT_CONTENT = 'tome_sync.export_content';

  /**
   * Name of the event fired after a single content entity is deleted.
   *
   * @Event
   *
   * @see \Drupal\tome_sync\Event\ContentCrudEvent
   *
   * @var string
   */
  const DELETE_CONTENT = 'tome_sync.delete_content';

  /**
   * Name of the event fired after a single content entity is imported.
   *
   * @Event
   *
   * @see \Drupal\tome_sync\Event\ContentCrudEvent
   *
   * @var string
   */
  const IMPORT_CONTENT = 'tome_sync.import_content';

  /**
   * Name of the event fired after the entire export process is complete.
   *
   * @Event
   *
   * @see \Symfony\Component\EventDispatcher\Event
   *
   * @var string
   */
  const EXPORT_ALL = 'tome_sync.export_all';

  /**
   * Name of the event fired after the entire import process is complete.
   *
   * @Event
   *
   * @see \Symfony\Component\EventDispatcher\Event
   *
   * @var string
   */
  const IMPORT_ALL = 'tome_sync.import_all';

}
