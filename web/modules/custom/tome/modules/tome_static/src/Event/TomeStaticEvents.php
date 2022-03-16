<?php

namespace Drupal\tome_static\Event;

/**
 * Defines events for Tome Static.
 */
final class TomeStaticEvents {

  /**
   * Name of the event fired when collecting paths for the static generator.
   *
   * @Event
   *
   * @see \Drupal\tome_static\Event\CollectPathsEvent
   *
   * @var string
   */
  const COLLECT_PATHS = 'tome_static.collect_paths';

  /**
   * Name of the event fired when replacing a path placeholder.
   *
   * @Event
   *
   * @see \Drupal\tome_static\Event\PathPlaceholderEvent
   *
   * @var string
   */
  const PATH_PLACEHOLDER = 'tome_static.path_placeholder';

  /**
   * Name of the event fired when preparing for a new request.
   *
   * @Event
   *
   * @var string
   */
  const REQUEST_PREPARE = 'tome_static.request_prepare';

  /**
   * Name of the event fired when finding paths related to an HTML document.
   *
   * @Event
   *
   * @see \Drupal\tome_static\Event\ModifyHtmlEvent
   *
   * @var string
   */
  const MODIFY_HTML = 'tome_static.modify_html';

  /**
   * Modifies the destination path for a static page.
   *
   * @Event
   *
   * @see \Drupal\tome_static\Event\ModifyDestinationEvent
   *
   * @var string
   */
  const MODIFY_DESTINATION = 'tome_static.modify_destination';

  /**
   * Name of the event fired when a static file is saved.
   *
   * @Event
   *
   * @see \Drupal\tome_static\Event\FileSavedEvent
   *
   * @var string
   */
  const FILE_SAVED = 'tome_static.file_saved';

}
