<?php

namespace Drupal\tome_static\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Allows modules to modify the HTML of a static page before save.
 */
class ModifyDestinationEvent extends Event {

  /**
   * The destination path.
   *
   * @var string
   */
  protected $destination;

  /**
   * Constructs a ModifyDestinationEvent object.
   *
   * @param string $destination
   *   The destination path.
   */
  public function __construct($destination) {
    $this->destination = $destination;
  }

  /**
   * Gets the destination path.
   *
   * @return string
   *   The destination path.
   */
  public function getDestination() {
    return $this->destination;
  }

  /**
   * Sets the destination path.
   *
   * @param string $destination
   *   The destination path.
   */
  public function setDestination($destination) {
    $this->destination = $destination;
  }

}
