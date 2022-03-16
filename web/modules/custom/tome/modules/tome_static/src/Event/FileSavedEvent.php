<?php

namespace Drupal\tome_static\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Allows modules to react to a file save.
 */
class FileSavedEvent extends Event {

  /**
   * The path to the saved file.
   *
   * @var string
   */
  protected $path;

  /**
   * Constructs a FileSavedEvent object.
   *
   * @param string $path
   *   A path.
   */
  public function __construct($path) {
    $this->path = $path;
  }

  /**
   * Gets the path to the saved file.
   *
   * @return string
   *   The path.
   */
  public function getPath() {
    return $this->path;
  }

}
