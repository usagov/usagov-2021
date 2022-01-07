<?php

namespace Drupal\tome_static\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Allows modules that provide placeholder paths to load the real path.
 */
class PathPlaceholderEvent extends Event {

  /**
   * The path.
   *
   * @var string
   */
  protected $path;

  /**
   * If a placeholdered path is invalid.
   *
   * @var bool
   */
  protected $isInvalid;

  /**
   * Constructs a PathPlaceholderEvent object.
   *
   * @param string $path
   *   A path.
   */
  public function __construct($path) {
    $this->path = $path;
    $this->isInvalid = FALSE;
  }

  /**
   * Gets the path.
   *
   * @return string
   *   The path.
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * Sets the path.
   *
   * @param string $path
   *   The path.
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * Marks the path as invalid.
   *
   * Useful if a placeholder is malformed.
   */
  public function setInvalid() {
    $this->isInvalid = TRUE;
  }

  /**
   * Checks if the path was marked as invalid.
   *
   * @return string
   *   Whether or not the path was marked as invalid.
   */
  public function isInvalid() {
    return $this->isInvalid;
  }

}
